<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnifiedPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch_id',
        'paciente',
        'dni',
        'fecha_hora',
        'departamento',
        'especialidad',
        'medico',
        'cobertura',
        'numerobeneficiario',
        'prestacion',
        'codigo',
        'cantidad',
        'monto_cobertura',
        'monto_bono_persona',
        'monto_total',
        'numero_cobertura',
        'numero_bono_persona',
        'payment_cobertura_id',
        'payment_bono_persona_id',
        'estado_final',
        'confianza',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'cantidad' => 'decimal:2',
        'monto_cobertura' => 'decimal:2',
        'monto_bono_persona' => 'decimal:2',
        'monto_total' => 'decimal:2',
    ];

    public function paymentCobertura(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_cobertura_id');
    }

    public function paymentBonoPersona(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_bono_persona_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'unified_payment_id');
    }
}
