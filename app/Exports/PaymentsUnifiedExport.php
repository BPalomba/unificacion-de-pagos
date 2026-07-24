<?php

namespace App\Exports;

use App\Models\Payment;
use App\Models\UnifiedPayment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exporta UNA fila por cada pago real: los pares unificados quedan en
 * una sola fila (con los datos del lado "Bono a Persona" agregados como
 * columnas extra, no como fila aparte), y los pagos sin duplicado
 * quedan tal cual. Mantiene las 27 columnas originales del Excel del
 * hospital, más algunas columnas propias al final para ver el
 * resultado de la unificación sin perder trazabilidad.
 */
class PaymentsUnifiedExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected ?string $batchId = null)
    {
    }

    public function collection(): Collection
    {
        $unificados = UnifiedPayment::query()
            ->with(['paymentCobertura', 'paymentBonoPersona'])
            ->when($this->batchId, fn ($q) => $q->where('import_batch_id', $this->batchId))
            ->get()
            ->map(fn (UnifiedPayment $u) => $this->desdeUnificado($u));

        $standalone = Payment::query()
            ->where('dedup_status', 'standalone')
            ->when($this->batchId, fn ($q) => $q->where('import_batch_id', $this->batchId))
            ->get()
            ->map(fn (Payment $p) => $this->desdeStandalone($p));

        return $unificados->concat($standalone)
            ->sortBy(fn ($row) => $row['orden'])
            ->values();
    }

    /**
     * Arma UNA fila para un par ya unificado. Los campos que describen
     * el turno (paciente, fecha, hora, prestación, etc.) se toman de la
     * fila de cobertura. TOTAL es la suma real (cobertura + bono). Los
     * datos propios del lado "Bono a Persona" (su número de comprobante
     * y su monto) van en columnas aparte al final, para no perderlos
     * sin duplicar la fila.
     */
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
            // COPAGO puede venir cargado del lado cobertura o del lado
            // bono según el caso; se suman ambos para no perder ninguno.
            'copago' => (float) ($cobertura?->copago ?? 0) + (float) ($bono?->copago ?? 0),
            'total' => $u->monto_total,
            'estado' => $u->estado_final,
            'tipo' => $cobertura?->tipo,
            'modificado_por' => $cobertura?->modificado_por,
            'fecha_mod' => $cobertura?->fecha_mod,
            // FAC_NUM / REC_NRO / CENTRO_COSTO suelen venir cargados del
            // lado del comprobante que se facturó (a veces cobertura, a
            // veces bono): se toma el que tenga valor.
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
            'total' => $p->total,
            'estado' => $p->estado,
            'tipo' => $p->tipo,
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

    public function headings(): array
    {
        return [
            'PACIENTE', 'DNI', 'FECHA', 'HORA', 'DEPARTAMENTO', 'ESPECIALIDAD',
            'MEDICO', 'ESTADO_TURNO', 'COBERTURA', 'NUMEROBENEFICIARIO', 'NUMERO',
            'NUMERO_AUTORIZACION', 'PRESTACION', 'DETALLE', 'CODIGO', 'CANTIDAD',
            'PRECIO', 'COPAGO', 'TOTAL', 'ESTADO', 'TIPO', 'MODICADO POR',
            'FECHA MOD', 'FAC_NUM', 'FAC_NUM_MANUAL', 'REC_NRO', 'CENTRO_COSTO',
            // Columnas propias, al final, para no romper el orden original
            'NUMERO_BONO_PERSONA',
            'MONTO_BONO_PERSONA',
            'ESTADO_UNIFICACION',
        ];
    }

    /**
     * @param  array  $row
     */
    public function map($row): array
    {
        return [
            $row['paciente'],
            $row['dni'],
            $row['fecha'],
            $row['hora'],
            $row['departamento'],
            $row['especialidad'],
            $row['medico'],
            $row['estado_turno'],
            $row['cobertura'],
            $row['numerobeneficiario'],
            $row['numero'],
            $row['numero_autorizacion'],
            $row['prestacion'],
            $row['detalle'],
            $row['codigo'],
            $row['cantidad'],
            $row['precio'],
            $row['copago'],
            $row['total'],
            $row['estado'],
            $row['tipo'],
            $row['modificado_por'],
            $row['fecha_mod'],
            $row['fac_num'],
            $row['fac_num_manual'],
            $row['rec_nro'],
            $row['centro_costo'],
            $row['numero_bono_persona'],
            $row['monto_bono_persona'],
            $row['estado_unificacion'],
        ];
    }
}