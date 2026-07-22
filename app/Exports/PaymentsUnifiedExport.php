<?php

namespace App\Exports;

use App\Models\Payment;
use App\Models\UnifiedPayment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsUnifiedExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * @param  string|null  $batchId  Si se indica, exporta solo ese lote de importación.
     *                                Si es null, exporta todo lo que haya en la base.
     */
    public function __construct(protected ?string $batchId = null)
    {
    }

    /**
     * Junta los pares ya unificados con los pagos que no tenían
     * duplicado (standalone), para entregar un único Excel con el
     * resultado final de la conciliación.
     */
    public function collection(): Collection
    {
        $unificados = UnifiedPayment::query()
            ->when($this->batchId, fn ($q) => $q->where('import_batch_id', $this->batchId))
            ->orderBy('fecha_hora')
            ->get()
            ->map(fn (UnifiedPayment $u) => [
                'tipo_registro' => 'Unificado',
                'paciente' => $u->paciente,
                'dni' => $u->dni,
                'fecha_hora' => $u->fecha_hora,
                'departamento' => $u->departamento,
                'especialidad' => $u->especialidad,
                'medico' => $u->medico,
                'cobertura' => $u->cobertura,
                'prestacion' => $u->prestacion,
                'codigo' => $u->codigo,
                'cantidad' => $u->cantidad,
                'monto_cobertura' => $u->monto_cobertura,
                'monto_bono_persona' => $u->monto_bono_persona,
                'monto_total' => $u->monto_total,
                'numero_cobertura' => $u->numero_cobertura,
                'numero_bono_persona' => $u->numero_bono_persona,
                'estado_final' => $u->estado_final,
                'confianza' => $u->confianza,
            ]);

        $standalone = Payment::query()
            ->where('dedup_status', 'standalone')
            ->when($this->batchId, fn ($q) => $q->where('import_batch_id', $this->batchId))
            ->orderBy('fecha_hora')
            ->get()
            ->map(fn (Payment $p) => [
                'tipo_registro' => 'Individual (sin duplicado)',
                'paciente' => $p->paciente,
                'dni' => $p->dni,
                'fecha_hora' => $p->fecha_hora,
                'departamento' => $p->departamento,
                'especialidad' => $p->especialidad,
                'medico' => $p->medico,
                'cobertura' => $p->cobertura,
                'prestacion' => $p->prestacion,
                'codigo' => $p->codigo,
                'cantidad' => $p->cantidad,
                'monto_cobertura' => $p->tipo === 'Bono a Persona' ? 0 : $p->total,
                'monto_bono_persona' => $p->tipo === 'Bono a Persona' ? $p->total : 0,
                'monto_total' => $p->total,
                'numero_cobertura' => $p->tipo === 'Bono a Persona' ? null : $p->numero,
                'numero_bono_persona' => $p->tipo === 'Bono a Persona' ? $p->numero : null,
                'estado_final' => $p->estado,
                'confianza' => '—',
            ]);

        return $unificados->concat($standalone)->sortBy('fecha_hora')->values();
    }

    public function headings(): array
    {
        return [
            'Tipo de registro',
            'Paciente',
            'DNI',
            'Fecha y hora',
            'Departamento',
            'Especialidad',
            'Médico',
            'Cobertura',
            'Prestación',
            'Código',
            'Cantidad',
            'Monto cobertura',
            'Monto Bono a Persona',
            'Monto total',
            'N° comprobante cobertura',
            'N° comprobante bono',
            'Estado final',
            'Confianza del match',
        ];
    }

    public function map($row): array
    {
        return [
            $row['tipo_registro'],
            $row['paciente'],
            $row['dni'],
            optional($row['fecha_hora'])->format('d/m/Y H:i:s'),
            $row['departamento'],
            $row['especialidad'],
            $row['medico'],
            $row['cobertura'],
            $row['prestacion'],
            $row['codigo'],
            $row['cantidad'],
            $row['monto_cobertura'],
            $row['monto_bono_persona'],
            $row['monto_total'],
            $row['numero_cobertura'],
            $row['numero_bono_persona'],
            $row['estado_final'],
            $row['confianza'],
        ];
    }
}
