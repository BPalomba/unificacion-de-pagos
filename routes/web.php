<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentUnificationController;


Route::get('/pagos/unificacion', [PaymentUnificationController::class, 'create'])
    ->name('payments.upload');

Route::post('/pagos/unificacion', [PaymentUnificationController::class, 'store'])
    ->name('payments.upload.store');

Route::get('/pagos/unificacion/exportar', [PaymentUnificationController::class, 'export'])
    ->name('payments.export');