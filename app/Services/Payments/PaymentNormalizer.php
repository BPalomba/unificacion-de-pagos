<?php

namespace App\Services\Payments;

use Carbon\Carbon;

class PaymentNormalizer
{
    public function __construct(
        protected array $businessKeyFields,
    ) {
    }

    /**
     * Normaliza una fila cruda (con claves en snake_case, ya sea que
     * venga del Excel o del endpoint) a un array con tipos consistentes
     * y la clave de negocio ya calculada.
     */
    public function normalizeRow(array $raw): array
    {
        $get = fn (string $key) => isset($raw[$key]) ? trim((string) $raw[$key]) : null;

        $fecha = $get('fecha');
        $hora = $get('hora');

        $row = [
            'paciente' => $get('paciente'),
            'dni' => $get('dni'),
            'fecha' => $fecha,
            'hora' => $hora,
            'fecha_hora' => $this->parseFechaHora($fecha, $hora),
            'departamento' => $get('departamento'),
            'especialidad' => $get('especialidad'),
            'medico' => $get('medico'),
            'estado_turno' => $get('estado_turno'),
            'cobertura' => $get('cobertura'),
            'numerobeneficiario' => $get('numerobeneficiario'),
            'numero' => $get('numero'),
            'numero_autorizacion' => $get('numero_autorizacion'),
            'prestacion' => $get('prestacion'),
            'detalle' => $get('detalle'),
            'codigo' => $get('codigo'),
            'cantidad' => $this->parseMonto($raw['cantidad'] ?? null),
            'precio' => $this->parseMonto($raw['precio'] ?? null),
            'copago' => $this->parseMonto($raw['copago'] ?? null),
            'total' => $this->parseMonto($raw['total'] ?? null),
            'estado' => $get('estado'),
            'tipo' => $get('tipo'),
            // El header original del hospital trae el typo "MODICADO POR",
            // que Laravel-Excel normaliza a "modicado_por". Aceptamos ambas
            // variantes por si la fuente cambia el nombre de la columna.
            'modificado_por' => $get('modicado_por') ?? $get('modificado_por'),
            'fecha_mod' => $get('fecha_mod'),
            'fac_num' => $get('fac_num'),
            'fac_num_manual' => $get('fac_num_manual'),
            'rec_nro' => $get('rec_nro'),
            'centro_costo' => $get('centro_costo'),
        ];

        $row['business_key'] = $this->businessKey($row);

        return $row;
    }

    /**
     * Convierte un monto en formato es-AR ("29.053,49") a float.
     * También acepta valores ya numéricos (por ejemplo, si Excel entrega
     * la celda como número en vez de texto).
     */
    public function parseMonto(mixed $valor): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $valor = trim((string) $valor);
        if ($valor === '') {
            return 0.0;
        }

        // Quita separador de miles "." y convierte coma decimal a punto
        $limpio = str_replace('.', '', $valor);
        $limpio = str_replace(',', '.', $limpio);

        return is_numeric($limpio) ? (float) $limpio : 0.0;
    }

    /**
     * Combina FECHA ("1/6/2026") y HORA ("18:20:00") en un Carbon.
     * Devuelve null si no se puede parsear (se loggea aparte para no
     * frenar la importación completa por una fila mal formada).
     */
    public function parseFechaHora(?string $fecha, ?string $hora): ?Carbon
    {
        if (! $fecha) {
            return null;
        }

        $hora = $hora ?: '00:00:00';

        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', "{$fecha} {$hora}");
        } catch (\Throwable) {
            try {
                // Fallback por si la fecha viene en formato Y-m-d (excel numérico
                // convertido, u otra fuente distinta al Excel del hospital)
                return Carbon::parse("{$fecha} {$hora}");
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * Genera la clave de negocio: identifica que dos filas corresponden
     * al mismo turno/prestación (candidatas a ser el par duplicado).
     */
    public function businessKey(array $row): string
    {
        $partes = [];
        foreach ($this->businessKeyFields as $campo) {
            $valor = $row[$campo] ?? '';
            $partes[] = mb_strtoupper(trim((string) $valor));
        }

        return implode('|', $partes);
    }

    /**
     * Parsea el campo NUMERO buscando el patrón <prefijo><timestamp de
     * 14 dígitos><sufijo C|P|S>. Devuelve null en 'base'/'timestamp' si
     * el NUMERO no sigue ese formato (por ejemplo, datos históricos).
     */
    public function parseNumero(?string $numero): array
    {
        $numero = trim((string) $numero);

        if ($numero === '') {
            return ['base' => null, 'timestamp' => null, 'suffix' => null];
        }

        $suffix = strtoupper(substr($numero, -1));
        if (! in_array($suffix, ['C', 'P', 'S'], true)) {
            return ['base' => null, 'timestamp' => null, 'suffix' => null];
        }

        $sinSufijo = substr($numero, 0, -1);

        if (! preg_match('/^(.*?)(\d{14})$/', $sinSufijo, $m)) {
            // Tiene sufijo C/P/S pero no el patrón de timestamp de 14
            // dígitos delante (formato antiguo/otro módulo)
            return ['base' => null, 'timestamp' => null, 'suffix' => $suffix];
        }

        return ['base' => $m[1], 'timestamp' => $m[2], 'suffix' => $suffix];
    }
}
