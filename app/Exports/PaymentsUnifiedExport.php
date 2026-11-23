<?php

namespace App\Exports;

use App\Services\Payments\UnifiedPaymentsQuery;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PaymentsUnifiedExport implements FromGenerator, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    public function __construct(protected string $batchId)
    {
    }

    public function generator(): Generator
    {
        return (new UnifiedPaymentsQuery())->generator($this->batchId);
    }

    protected function desdeRevision(Payment $p): array
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
            'estado_unificacion' => 'A revisar',
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestDataRow();
                $highestColumn = $sheet->getHighestDataColumn();
                $range = 'A1:' . $highestColumn . $highestRow;

                $sheet->setAutoFilter($range);

                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9EAF7'],
                    ],
                ]);

                $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle('thin');
            },
        ];
    }
}