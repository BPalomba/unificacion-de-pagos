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

        $fecha = $this->normalizeFecha($raw['fecha'] ?? null);
        $hora = $this->normalizeHora($raw['hora'] ?? null);

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
            'fecha_mod' => $this->normalizeFechaHoraTexto($raw['fecha_mod'] ?? null),
            'fac_num' => $get('fac_num'),
            'fac_num_manual' => $get('fac_num_manual'),
            'rec_nro' => $get('rec_nro'),
            'centro_costo' => $get('centro_costo'),
        ];

        $row['business_key'] = $this->businessKey($row);

        return $row;
    }

    /**
     * Normaliza la columna FECHA a texto "d/m/Y", sin importar si Excel
     * la entregó como texto ("1/6/2026"), como objeto DateTime/Carbon,
     * como número de serie de Excel, o como texto con formato distinto
     * (ej. "2026-06-01" o "2026-06-01 00:00:00").
     */
    public function normalizeFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('j/n/Y');
        }

        if (is_int($valor) || is_float($valor)) {
            return $this->excelSerialToDateTime((float) $valor)?->format('j/n/Y');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        // Si es puramente numérico como texto (ej. "46174", o "46174,5"
        // con coma decimal como devuelve Excel en configuración regional
        // argentina), es un serial de Excel.
        $textoNumerico = str_replace(',', '.', $texto);
        if (is_numeric($textoNumerico)) {
            $convertido = $this->excelSerialToDateTime((float) $textoNumerico)?->format('j/n/Y');
            if ($convertido) {
                return $convertido;
            }
        }

        // Ya viene en formato d/m/Y o d-m-Y -> se normaliza el separador
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $texto, $m)) {
            return sprintf('%d/%d/%d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // Último intento: dejar que PHP interprete el string como fecha
        // (cubre "2026-06-01", "2026-06-01 00:00:00", etc.)
        $timestamp = strtotime($texto);
        if ($timestamp !== false) {
            return date('j/n/Y', $timestamp);
        }

        // No se pudo interpretar: se devuelve el texto crudo en vez de
        // perderlo silenciosamente como null.
        return $texto;
    }

    /**
     * Igual que normalizeFecha, pero para la columna HORA -> "H:i:s".
     */
    public function normalizeHora(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('H:i:s');
        }

        if (is_int($valor) || is_float($valor)) {
            return $this->horaDesdeSerial((float) $valor);
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        // Serial de Excel guardado como texto (ej. "0.763888889" o, en
        // configuración regional argentina, "0,763888889" con coma)
        $textoNumerico = str_replace(',', '.', $texto);
        if (is_numeric($textoNumerico)) {
            $convertido = $this->horaDesdeSerial((float) $textoNumerico);
            if ($convertido) {
                return $convertido;
            }
        }

        // Ya viene como "18:20:00" o "18:20"
        if (preg_match('/^(\d{1,2}):(\d{2})(:(\d{2}))?$/', $texto, $m)) {
            $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $i = $m[2];
            $s = $m[4] ?? '00';

            return "{$h}:{$i}:{$s}";
        }

        // Viene con fecha y hora juntas (ej. "1899-12-30 18:20:00",
        // "2026-06-01T18:20:00"): se extrae solo la parte de la hora
        if (preg_match('/(\d{1,2}):(\d{2})(:(\d{2}))?/', $texto, $m)) {
            $h = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $i = $m[2];
            $s = $m[4] ?? '00';

            return "{$h}:{$i}:{$s}";
        }

        // Último intento con strtotime
        $timestamp = strtotime($texto);
        if ($timestamp !== false) {
            return date('H:i:s', $timestamp);
        }

        return $texto;
    }

    /**
     * Normaliza columnas que combinan fecha+hora en un solo campo de
     * texto (como "FECHA MOD"), a "d/m/Y H:i:s". Acepta los mismos
     * formatos que normalizeFecha/normalizeHora: texto, DateTime, o
     * serial de Excel (con coma o punto decimal).
     */
    public function normalizeFechaHoraTexto(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y H:i:s');
        }

        if (is_int($valor) || is_float($valor)) {
            return $this->excelSerialToDateTime((float) $valor)?->format('d/m/Y H:i:s');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $textoNumerico = str_replace(',', '.', $texto);
        if (is_numeric($textoNumerico)) {
            $convertido = $this->excelSerialToDateTime((float) $textoNumerico)?->format('d/m/Y H:i:s');
            if ($convertido) {
                return $convertido;
            }
        }

        // Ya viene como texto legible (ej. "12/6/2026 09:07"): se deja tal cual
        return $texto;
    }

    /**
     * Convierte un serial de Excel que representa una HORA a "H:i:s".
     * Los seriales de hora "pura" son una fracción de día (0 a 1),
     * pero si viene combinado con fecha (número grande), se toma
     * igual solo la parte de la hora.
     */
    protected function horaDesdeSerial(float $serial): ?string
    {
        $fraccionDia = $serial - floor($serial);
        $segundos = (int) round($fraccionDia * 86400);

        // Corrige redondeos que empujan a 86400 (medianoche del día siguiente)
        if ($segundos >= 86400) {
            $segundos -= 86400;
        }

        $h = intdiv($segundos, 3600);
        $i = intdiv($segundos % 3600, 60);
        $s = $segundos % 60;

        return sprintf('%02d:%02d:%02d', $h, $i, $s);
    }

    /**
     * Convierte un número de serie de fecha de Excel a DateTime.
     * Excel cuenta los días desde el 30/12/1899.
     */
    protected function excelSerialToDateTime(float $serial): ?\DateTimeImmutable
    {
        try {
            $base = new \DateTimeImmutable('1899-12-30');
            $dias = (int) floor($serial);
            $fraccionDia = $serial - $dias;
            $segundos = (int) round($fraccionDia * 86400);

            return $base->modify("+{$dias} days")->modify("+{$segundos} seconds");
        } catch (\Throwable) {
            return null;
        }
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
     * Devuelve null si no se puede parsear (no frena la importación
     * completa por una fila mal formada).
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
            return ['base' => null, 'timestamp' => null, 'suffix' => $suffix];
        }

        return ['base' => $m[1], 'timestamp' => $m[2], 'suffix' => $suffix];
    }
}