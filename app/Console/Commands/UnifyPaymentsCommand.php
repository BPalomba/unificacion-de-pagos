<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsUsers;
use App\Models\PaymentReport;
use App\Services\Payments\DataSources\EndpointPaymentSource;
use App\Services\Payments\DataSources\ExcelPaymentSource;
use App\Services\Payments\PaymentDeduplicationService;
use App\Services\Payments\PaymentReportService;
use Illuminate\Console\Command;

class UnifyPaymentsCommand extends Command
{
    use FindsUsers;

    protected $signature = 'pagos:unificar
        {--source=excel : Origen de datos: excel | endpoint}
        {--file= : Ruta al archivo .xlsx cuando --source=excel}
        {--url= : URL del endpoint cuando --source=endpoint (por defecto, la de config/payments.php)}
        {--user= : Email del usuario dueño del informe (obligatorio salvo con --dry-run)}
        {--dry-run : Analiza y muestra el resultado sin escribir nada en la base}';

    protected $description = 'Detecta y unifica pagos duplicados (cobertura + Bono a Persona) y los guarda como un informe temporal del usuario';

    public function handle(PaymentReportService $reports): int
    {
        $source = $this->option('source');

        // El usuario se valida antes de leer el archivo, para fallar rápido
        $user = null;
        if (! $this->option('dry-run')) {
            if (! $this->option('user')) {
                $this->error('Falta indicar --user=email (el informe queda a nombre de ese usuario), o usar --dry-run.');

                return self::FAILURE;
            }

            if (! $user = $this->findUser($this->option('user'))) {
                return self::FAILURE;
            }
        }

        try {
            $rawRows = match ($source) {
                'excel' => $this->fromExcel(),
                'endpoint' => $this->fromEndpoint(),
                default => throw new \InvalidArgumentException("--source debe ser 'excel' o 'endpoint', recibido: {$source}"),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Filas leídas: {$rawRows->count()}");

        if ($this->option('dry-run')) {
            $this->mostrarResumen(PaymentDeduplicationService::fromConfig()->analyze($rawRows));
            $this->comment('Modo dry-run: no se escribió nada en la base de datos.');

            return self::SUCCESS;
        }

        $nombre = $source === 'excel' ? basename($this->option('file')) : 'endpoint';
        $informe = $reports->crear($user, $rawRows, $nombre, $source);

        $this->table(
            ['Unificados', 'Sin duplicado', 'Para revisión manual'],
            [[$informe->unificados, $informe->standalone, $informe->revision]]
        );

        $vence = PaymentReport::horaLocal($informe->expires_at);
        $this->info("Informe {$informe->id} creado para {$user->email}. Vence el {$vence}.");

        return self::SUCCESS;
    }

    protected function fromExcel(): \Illuminate\Support\Collection
    {
        $path = $this->option('file');

        if (! $path) {
            throw new \InvalidArgumentException('Falta indicar --file=ruta/al/archivo.xlsx');
        }

        if (! file_exists($path)) {
            throw new \InvalidArgumentException("No se encontró el archivo: {$path}");
        }

        return (new ExcelPaymentSource($path))->fetch();
    }

    protected function fromEndpoint(): \Illuminate\Support\Collection
    {
        $url = $this->option('url') ?: config('payments.endpoint_url');

        if (! $url) {
            throw new \InvalidArgumentException(
                'Falta indicar --url=... o configurar PAYMENTS_ENDPOINT_URL en el .env'
            );
        }

        return (new EndpointPaymentSource($url, config('payments.endpoint_token')))->fetch();
    }

    protected function mostrarResumen(array $analysis): void
    {
        $this->table(
            ['Unificados', 'Sin duplicado', 'Para revisión manual'],
            [[
                count($analysis['pairs']),
                count($analysis['standalone']),
                count($analysis['review']),
            ]]
        );

        if (count($analysis['review']) > 0) {
            $this->warn('Grupos para revisión manual:');
            foreach ($analysis['review'] as $grupo) {
                $paciente = $grupo['filas'][0]['paciente'] ?? '(sin paciente)';
                $cantidad = count($grupo['filas']);
                $this->line("  - {$paciente}: {$grupo['motivo']} ({$cantidad} filas)");
            }
        }
    }
}
