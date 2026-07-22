<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentReviewGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_batch_id',
        'business_key',
        'motivo',
        'cantidad_filas',
        'resuelto',
    ];

    protected $casts = [
        'resuelto' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_review_group_id');
    }
}
