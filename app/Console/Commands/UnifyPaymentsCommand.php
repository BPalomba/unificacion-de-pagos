<?php

namespace App\Console\Commands;

use App\Services\Payments\DataSources\EndpointPaymentSource;
use App\Services\Payments\DataSources\ExcelPaymentSource;
use App\Services\Payments\PaymentDeduplicationService;
use App\Services\Payments\PaymentNormalizer;
use Illuminate\Console\Command;

class UnifyPaymentsCommand extends Command
{
    protected $signature = 'pagos:unificar
        {--source=excel : Origen de datos: excel | endpoint}
        {--file= : Ruta al archivo .xlsx cuando --source=excel}
        {--url= : URL del endpoint cuando --source=endpoint (por defecto, la de config/payments.php)}
        {--dry-run : Analiza y muestra el resultado sin escribir nada en la base}';

    protected $description = 'Detecta y unifica pagos duplicados (cobertura + Bono a Persona) desde Excel o desde el endpoint del hospital';

    public function handle(): int
    {
        $source = $this->option('source');

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

        $normalizer = new PaymentNormalizer(config('payments.business_key_fields'));
        $service = new PaymentDeduplicationService(
            normalizer: $normalizer,
            tipoPaciente: config('payments.tipo_paciente'),
            toleranceSeconds: config('payments.numero_time_tolerance_seconds'),
            estadoPriority: config('payments.estado_priority'),
        );

        $analysis = $service->analyze($rawRows);

        $this->mostrarResumen($analysis);

        if ($this->option('dry-run')) {
            $this->comment('Modo dry-run: no se escribió nada en la base de datos.');

            return self::SUCCESS;
        }

        $resultado = $service->persist($analysis, $source);

        $this->info("Persistido. Batch: {$resultado['batch_id']}");

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
