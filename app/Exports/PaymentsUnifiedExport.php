<?php

namespace App\Exports;

use App\Services\Payments\UnifiedPaymentsQuery;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PaymentsUnifiedExport implements FromGenerator, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(protected ?string $batchId = null)
    {
    }

    public function generator(): Generator
    {
        return (new UnifiedPaymentsQuery())->generator($this->batchId);
    }

    public function headings(): array
    {
        return [
            'PACIENTE', 'DNI', 'FECHA', 'HORA', 'DEPARTAMENTO', 'ESPECIALIDAD',
            'MEDICO', 'ESTADO_TURNO', 'COBERTURA', 'NUMEROBENEFICIARIO', 'NUMERO',
            'NUMERO_AUTORIZACION', 'PRESTACION', 'DETALLE', 'CODIGO', 'CANTIDAD',
            'PRECIO', 'COPAGO', 'TOTAL', 'ESTADO', 'TIPO', 'MODICADO POR',
            'FECHA MOD', 'FAC_NUM', 'FAC_NUM_MANUAL', 'REC_NRO', 'CENTRO_COSTO',
            'NUMERO_BONO_PERSONA', 'MONTO_BONO_PERSONA', 'ESTADO_UNIFICACION',
        ];
    }

    /**
     * @param  array  $row
     */
    public function map($row): array
    {
        return [
            $row['paciente'], $row['dni'], $row['fecha'], $row['hora'], $row['departamento'],
            $row['especialidad'], $row['medico'], $row['estado_turno'], $row['cobertura'],
            $row['numerobeneficiario'], $row['numero'], $row['numero_autorizacion'],
            $row['prestacion'], $row['detalle'], $row['codigo'], $row['cantidad'],
            $row['precio'], $row['copago'], $row['total'], $row['estado'], $row['tipo'],
            $row['modificado_por'], $row['fecha_mod'], $row['fac_num'], $row['fac_num_manual'],
            $row['rec_nro'], $row['centro_costo'], $row['numero_bono_persona'],
            $row['monto_bono_persona'], $row['estado_unificacion'],
        ];
    }
}