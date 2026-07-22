<?php

namespace App\Services\Payments\DataSources;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EndpointPaymentSource implements PaymentSourceInterface
{
    public function __construct(
        protected string $url,
        protected ?string $token = null,
    ) {
    }

    public function fetch(): Collection
    {
        $request = Http::acceptJson();

        if ($this->token) {
            $request = $request->withToken($this->token);
        }

        $response = $request->get($this->url);

        if ($response->failed()) {
            throw new RuntimeException(
                "No se pudo obtener la lista de pagos desde el endpoint (HTTP {$response->status()})"
            );
        }

        $data = $response->json();

        // Soporta tanto una respuesta que es directamente un array de
        // pagos, como una envuelta en {"data": [...]}.
        $items = collect($data['data'] ?? $data);

        return $items->map(fn (array $item) => $this->mapKeys($item));
    }

    /**
     * El endpoint devuelve las columnas con los mismos nombres que el
     * Excel (PACIENTE, DNI, FECHA, ..., "MODICADO POR", "FECHA MOD").
     * Acá se mapean a las mismas claves snake_case que usa el resto del
     * sistema, para que ambas fuentes terminen en un formato uniforme.
     */
    protected function mapKeys(array $item): array
    {
        $map = [
            'PACIENTE' => 'paciente',
            'DNI' => 'dni',
            'FECHA' => 'fecha',
            'HORA' => 'hora',
            'DEPARTAMENTO' => 'departamento',
            'ESPECIALIDAD' => 'especialidad',
            'MEDICO' => 'medico',
            'ESTADO_TURNO' => 'estado_turno',
            'COBERTURA' => 'cobertura',
            'NUMEROBENEFICIARIO' => 'numerobeneficiario',
            'NUMERO' => 'numero',
            'NUMERO_AUTORIZACION' => 'numero_autorizacion',
            'PRESTACION' => 'prestacion',
            'DETALLE' => 'detalle',
            'CODIGO' => 'codigo',
            'CANTIDAD' => 'cantidad',
            'PRECIO' => 'precio',
            'COPAGO' => 'copago',
            'TOTAL' => 'total',
            'ESTADO' => 'estado',
            'TIPO' => 'tipo',
            'MODICADO POR' => 'modicado_por',
            'MODIFICADOPOR' => 'modicado_por',
            'FECHA MOD' => 'fecha_mod',
            'FECHAMOD' => 'fecha_mod',
            'FAC_NUM' => 'fac_num',
            'FAC_NUM_MANUAL' => 'fac_num_manual',
            'REC_NRO' => 'rec_nro',
            'CENTRO_COSTO' => 'centro_costo',
        ];

        $mapped = [];
        foreach ($item as $key => $value) {
            $normalizedKey = $map[strtoupper(trim($key))] ?? strtolower(trim($key));
            $mapped[$normalizedKey] = $value;
        }

        return $mapped;
    }
}
