<?php

use App\Http\Controllers\CopagosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Unificación de Pagos / Sistema de Copago
|--------------------------------------------------------------------------
| Fusionar con tus rutas de autenticación existentes (login/logout,
| dashboard, etc.) — estas son solo las 3 nuevas para este módulo.
| Ajustá el middleware 'auth' si tu setup de autenticación usa otro
| nombre o no corresponde.
*/


    Route::get('/', [CopagosController::class, 'index'])->name('copagos.index');
    Route::post('/copagos', [CopagosController::class, 'store'])->name('copagos.store');
    Route::get('/copagos/exportar', [CopagosController::class, 'export'])->name('copagos.export');
