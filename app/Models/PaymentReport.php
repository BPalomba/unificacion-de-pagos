<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Excel subido por un usuario y procesado. Es temporal: deja de verse al
 * llegar a expires_at y la limpieza lo borra junto con sus pagos.
 * Su id se usa como import_batch_id de los pagos que generó.
 */
class PaymentReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'nombre_archivo',
        'filas_leidas',
        'unificados',
        'standalone',
        'revision',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'filas_leidas' => 'integer',
        'unificados' => 'integer',
        'standalone' => 'integer',
        'revision' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Informes que todavía no vencieron.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Duración configurada de los informes, en texto (ej. "2 horas",
     * "90 minutos"), para mostrar en pantalla.
     */
    public static function duracionLegible(): string
    {
        $minutos = (int) config('payments.report_ttl_minutes');

        if ($minutos >= 60 && $minutos % 60 === 0) {
            $horas = intdiv($minutos, 60);

            return $horas === 1 ? '1 hora' : "{$horas} horas";
        }

        return $minutos === 1 ? '1 minuto' : "{$minutos} minutos";
    }

    /**
     * Fecha en la zona horaria de pantalla (los datos se guardan en UTC).
     */
    public static function horaLocal(\DateTimeInterface $fecha, string $formato = 'd/m/Y H:i'): string
    {
        return \Illuminate\Support\Carbon::instance($fecha)
            ->timezone(config('payments.display_timezone'))
            ->format($formato);
    }

    /**
     * Texto con lo que falta para que venza (ej. "1 h 35 min", "12 min").
     */
    protected function tiempoRestante(): Attribute
    {
        return Attribute::get(function (): string {
            $minutos = max(0, (int) ceil(now()->diffInMinutes($this->expires_at, false)));

            if ($minutos < 60) {
                return "{$minutos} min";
            }

            $resto = $minutos % 60;

            return intdiv($minutos, 60) . ' h' . ($resto ? " {$resto} min" : '');
        });
    }

    /**
     * Nombre del Excel procesado para descargar, a partir del original
     * (ej. "Reporte Junio.xlsx" -> "reporte-junio_unificado.xlsx").
     */
    protected function nombreDescarga(): Attribute
    {
        return Attribute::get(function (): string {
            $base = Str::slug(pathinfo($this->nombre_archivo, PATHINFO_FILENAME)) ?: 'copagos';

            return "{$base}_unificado.xlsx";
        });
    }
}
