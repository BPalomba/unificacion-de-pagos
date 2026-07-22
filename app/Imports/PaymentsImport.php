<?php

namespace App\Imports;

use Illuminate\Support\Collection as SupportCollection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class PaymentsImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    protected SupportCollection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    /**
     * WithHeadingRow toma la primera fila como encabezado y normaliza
     * cada nombre de columna a snake_case (ej. "MODICADO POR" ->
     * "modicado_por", "NUMEROBENEFICIARIO" -> "numerobeneficiario").
     */
    public function collection(SupportCollection $rows): void
    {
        $filtered = $rows
            ->filter(fn ($row) => filled($row['paciente'] ?? null))
            ->map(fn ($row) => $row->toArray())
            ->values();

        // Con WithChunkReading, collection() se invoca una vez por cada
        // chunk leído del archivo; hay que acumular, no sobreescribir.
        $this->rows = $this->rows->concat($filtered);
    }

    public function rows(): SupportCollection
    {
        return $this->rows;
    }

    public function chunkSize(): int
    {
        return 500;
    }
}
