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
    Route::post('/informes', [CopagosController::class, 'store'])->name('informes.store');
    Route::get('/informes/{informe}', [CopagosController::class, 'show'])->name('informes.show');
    Route::get('/informes/{informe}/descargar', [CopagosController::class, 'export'])->name('informes.export');
    Route::delete('/informes/{informe}', [CopagosController::class, 'destroy'])->name('informes.destroy');
});

// Limpieza de informes vencidos, llamada por Vercel Cron (ver vercel.json).
// Protegida con CRON_SECRET en vez de login.
Route::get('/cron/purgar-informes', [CopagosController::class, 'purgar'])
    ->middleware('throttle:10,1')
    ->name('cron.purgar-informes');
