<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\UnifiedPayment;
use Illuminate\Support\Collection;

/**
 * Arma la lista de "pagos ya procesados" (unificados + standalone) en un
 * formato uniforme, una fila por pago real. La usan tanto la tabla web
 * (CopagosController) como el export a Excel (PaymentsUnifiedExport),
 * para no duplicar la lógica de armado de filas en dos lugares.
 */
class UnifiedPaymentsQuery
{
    /**
     * @param  array{medico?: string, paciente?: string, cobertura?: string, periodo?: string}  $filtros
     */
    public function rows(?string $batchId = null, array $filtros = []): Collection
    {
        $unificados = UnifiedPayment::query()
            ->with(['paymentCobertura', 'paymentBonoPersona'])
            ->when($batchId, fn ($q) => $q->where('import_batch_id', $batchId))
            ->when($filtros['medico'] ?? null, fn ($q, $v) => $q->where('medico', 'like', "%{$v}%"))
            ->when($filtros['paciente'] ?? null, fn ($q, $v) => $q->where('paciente', 'like', "%{$v}%"))
            ->when($filtros['cobertura'] ?? null, fn ($q, $v) => $q->where('cobertura', 'like', "%{$v}%"))
            ->when($filtros['periodo'] ?? null, fn ($q, $v) => $q->whereRaw(
                "strftime('%Y-%m', fecha_hora) = ?", [$v]
            ))
            ->get()
            ->map(fn (UnifiedPayment $u) => $this->desdeUnificado($u));

        $standalone = Payment::query()
            ->where('dedup_status', 'standalone')
            ->when($batchId, fn ($q) => $q->where('import_batch_id', $batchId))
            ->when($filtros['medico'] ?? null, fn ($q, $v) => $q->where('medico', 'like', "%{$v}%"))
            ->when($filtros['paciente'] ?? null, fn ($q, $v) => $q->where('paciente', 'like', "%{$v}%"))
            ->when($filtros['cobertura'] ?? null, fn ($q, $v) => $q->where('cobertura', 'like', "%{$v}%"))
            ->when($filtros['periodo'] ?? null, fn ($q, $v) => $q->whereRaw(
                "strftime('%Y-%m', fecha_hora) = ?", [$v]
            ))
            ->get()
            ->map(fn (Payment $p) => $this->desdeStandalone($p));

        return $unificados->concat($standalone)
            ->sortByDesc(fn ($row) => $row['orden'])
            ->values();
    }

    protected function desdeUnificado(UnifiedPayment $u): array
    {
        $cobertura = $u->paymentCobertura;
        $bono = $u->paymentBonoPersona;

        return [
            'paciente' => $u->paciente,
            'dni' => $u->dni,
            'fecha' => $cobertura?->fecha,
            'hora' => $cobertura?->hora,
            'departamento' => $u->departamento,
            'especialidad' => $u->especialidad,
            'medico' => $u->medico,
            'estado_turno' => $cobertura?->estado_turno,
            'cobertura' => $u->cobertura,
            'numerobeneficiario' => $u->numerobeneficiario,
            'numero' => $u->numero_cobertura,
            'numero_autorizacion' => $cobertura?->numero_autorizacion,
            'prestacion' => $u->prestacion,
            'detalle' => $cobertura?->detalle,
            'codigo' => $u->codigo,
            'cantidad' => $u->cantidad,
            'precio' => $cobertura?->precio,
            'copago' => (float) ($cobertura?->copago ?? 0) + (float) ($bono?->copago ?? 0),
            'monto_os' => $u->monto_cobertura,
            'total' => $u->monto_total,
            'estado' => $u->estado_final,
            'tipo' => $cobertura?->tipo,
            'tipo_bono' => $bono ? 'Bono a Persona' : null,
            'modificado_por' => $cobertura?->modificado_por,
            'fecha_mod' => $cobertura?->fecha_mod,
            'fac_num' => $cobertura?->fac_num ?: $bono?->fac_num,
            'fac_num_manual' => $cobertura?->fac_num_manual ?: $bono?->fac_num_manual,
            'rec_nro' => $cobertura?->rec_nro ?: $bono?->rec_nro,
            'centro_costo' => $cobertura?->centro_costo ?: $bono?->centro_costo,
            'numero_bono_persona' => $u->numero_bono_persona,
            'monto_bono_persona' => $u->monto_bono_persona,
            'estado_unificacion' => 'Unificado',
            'orden' => $u->fecha_hora,
        ];
    }

    protected function desdeStandalone(Payment $p): array
    {
        return [
            'paciente' => $p->paciente,
            'dni' => $p->dni,
            'fecha' => $p->fecha,
            'hora' => $p->hora,
            'departamento' => $p->departamento,
            'especialidad' => $p->especialidad,
            'medico' => $p->medico,
            'estado_turno' => $p->estado_turno,
            'cobertura' => $p->cobertura,
            'numerobeneficiario' => $p->numerobeneficiario,
            'numero' => $p->numero,
            'numero_autorizacion' => $p->numero_autorizacion,
            'prestacion' => $p->prestacion,
            'detalle' => $p->detalle,
            'codigo' => $p->codigo,
            'cantidad' => $p->cantidad,
            'precio' => $p->precio,
            'copago' => $p->copago,
            'monto_os' => $p->tipo === 'Bono a Persona' ? 0 : $p->total,
            'total' => $p->total,
            'estado' => $p->estado,
            'tipo' => $p->tipo,
            'tipo_bono' => $p->tipo === 'Bono a Persona' ? 'Bono a Persona' : null,
            'modificado_por' => $p->modificado_por,
            'fecha_mod' => $p->fecha_mod,
            'fac_num' => $p->fac_num,
            'fac_num_manual' => $p->fac_num_manual,
            'rec_nro' => $p->rec_nro,
            'centro_costo' => $p->centro_costo,
            'numero_bono_persona' => null,
            'monto_bono_persona' => null,
            'estado_unificacion' => 'Individual (sin duplicado)',
            'orden' => $p->fecha_hora,
        ];
    }
}