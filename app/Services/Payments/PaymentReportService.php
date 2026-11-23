<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentReport;
use App\Models\PaymentReviewGroup;
use App\Models\UnifiedPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo de vida de los informes temporales: crearlos a partir de las filas
 * de un Excel (o del endpoint), borrarlos a pedido y purgar los vencidos.
 */
class PaymentReportService
{
    public function __construct(
        protected ?PaymentDeduplicationService $dedup = null,
    ) {
        $this->dedup ??= PaymentDeduplicationService::fromConfig();
    }

    /**
     * Analiza las filas y guarda el resultado como un informe del usuario,
     * que vence a los payments.report_ttl_minutes minutos.
     */
    public function crear(User $user, Collection $rawRows, string $nombreArchivo, string $source = 'excel'): PaymentReport
    {
        $analysis = $this->dedup->analyze($rawRows);

        // Todo en una transacción: la purga nunca ve pagos de un informe
        // que todavía no existe (y los borraría por "huérfanos").
        return DB::transaction(function () use ($user, $rawRows, $nombreArchivo, $source, $analysis) {
            $report = $user->paymentReports()->create([
                'nombre_archivo' => mb_substr($nombreArchivo, 0, 255),
                'filas_leidas' => $rawRows->count(),
                'unificados' => count($analysis['pairs']),
                'standalone' => count($analysis['standalone']),
                'revision' => count($analysis['review']),
                'expires_at' => now()->addMinutes(config('payments.report_ttl_minutes')),
            ]);

            $this->dedup->persist($analysis, $source, $report->id);

            return $report;
        });
    }

    /**
     * Borra un informe y todos sus pagos.
     */
    public function eliminar(PaymentReport $report): void
    {
        DB::transaction(function () use ($report) {
            UnifiedPayment::where('import_batch_id', $report->id)->delete();
            PaymentReviewGroup::where('import_batch_id', $report->id)->delete();
            Payment::where('import_batch_id', $report->id)->delete();
            $report->delete();
        });
    }

    /**
     * Borra los informes vencidos y todo pago que no pertenezca a un informe
     * vigente. Esto último también limpia datos sin informe (cargados antes
     * de que existieran los informes, o de usuarios dados de baja).
     *
     * @return array{informes: int, pagos: int}
     */
    public function purgarVencidos(): array
    {
        return DB::transaction(function () {
            $informes = PaymentReport::where('expires_at', '<=', now())->delete();

            $huerfanos = fn ($query) => $query->where(fn ($q) => $q
                ->whereNull('import_batch_id')
                ->orWhereNotIn('import_batch_id', PaymentReport::query()->select('id')));

            $huerfanos(UnifiedPayment::query())->delete();
            $huerfanos(PaymentReviewGroup::query())->delete();
            $pagos = $huerfanos(Payment::query())->delete();

            return ['informes' => $informes, 'pagos' => $pagos];
        });
    }
}
