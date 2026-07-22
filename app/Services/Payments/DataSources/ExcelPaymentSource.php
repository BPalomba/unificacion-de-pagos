<?php

namespace App\Services\Payments\DataSources;

use App\Imports\PaymentsImport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

class ExcelPaymentSource implements PaymentSourceInterface
{
    /**
     * @param  string  $path  Ruta absoluta (o relativa a un disco) del archivo .xlsx/.xls
     */
    public function __construct(protected string $path)
    {
    }

    public function fetch(): Collection
    {
        $import = new PaymentsImport();

        Excel::import($import, $this->path);

        // WithHeadingRow ya deja cada fila como array asociativo con
        // claves en snake_case (por ejemplo "MODICADO POR" -> "modicado_por").
        return $import->rows();
    }
}
