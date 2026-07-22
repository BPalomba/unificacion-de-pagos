<?php

namespace App\Services\Payments\DataSources;

use Illuminate\Support\Collection;

interface PaymentSourceInterface
{
    /**
     * Devuelve una colección de arrays asociativos con claves en
     * snake_case (paciente, dni, fecha, hora, ..., total, estado, tipo,
     * etc.), sin normalizar todavía. La normalización de tipos y el
     * cálculo de la clave de negocio los hace PaymentNormalizer.
     *
     * @return Collection<int, array>
     */
    public function fetch(): Collection;
}
