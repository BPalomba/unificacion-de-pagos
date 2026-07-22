<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'import_batch_id',
        'paciente',
        'dni',
        'fecha',
        'hora',
        'fecha_hora',
        'departamento',
        'especialidad',
        'medico',
        'estado_turno',
        'cobertura',
        'numerobeneficiario',
        'numero',
        'numero_autorizacion',
        'prestacion',
        'detalle',
        'codigo',
        'cantidad',
        'precio',
        'copago',
        'total',
        'estado',
        'tipo',
        'modificado_por',
        'fecha_mod',
        'fac_num',
        'fac_num_manual',
        'rec_nro',
        'centro_costo',
        'business_key',
        'dedup_status',
        'unified_payment_id',
        'payment_review_group_id',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
        'cantidad' => 'decimal:2',
        'precio' => 'decimal:2',
        'copago' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function unifiedPayment(): BelongsTo
    {
        return $this->belongsTo(UnifiedPayment::class);
    }

    public function reviewGroup(): BelongsTo
    {
        return $this->belongsTo(PaymentReviewGroup::class, 'payment_review_group_id');
    }
}
