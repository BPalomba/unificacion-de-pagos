<?php

use App\Http\Controllers\CopagosController;
use App\Http\Controllers\TwoFactorSetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web
|--------------------------------------------------------------------------
|
| Login, logout y el desafío de MFA (/two-factor-challenge) los registra
| Laravel Fortify (ver config/fortify.php y FortifyServiceProvider).
|
*/

Route::middleware('auth')->group(function () {
    Route::get('/two-factor/setup', [TwoFactorSetupController::class, 'show'])->name('two-factor.setup');
    Route::get('/two-factor/recovery-codes', [TwoFactorSetupController::class, 'recoveryCodes'])->name('two-factor.setup.recovery-codes');
});

Route::middleware(['auth', '2fa.configured'])->group(function () {
    Route::get('/', [CopagosController::class, 'index'])->name('copagos.index');
    Route::post('/copagos', [CopagosController::class, 'store'])->name('copagos.store');
    Route::get('/copagos/exportar', [CopagosController::class, 'export'])->name('copagos.export');
});
