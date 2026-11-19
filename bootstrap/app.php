<?php

use App\Http\Middleware\EnsureTwoFactorIsConfigured;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En Vercel la app corre detrás de un proxy que termina HTTPS.
        // Confiar en sus cabeceras X-Forwarded-* hace que Laravel genere
        // URLs https:// (si no, el CSS y los formularios quedan en http://).
        $middleware->trustProxies(at: '*');

        // MFA obligatorio: sin app autenticadora confirmada no se entra.
        $middleware->alias([
            '2fa.configured' => EnsureTwoFactorIsConfigured::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// En Vercel el disco de la app es de solo lectura: solo /tmp es escribible.
// Con APP_STORAGE_PATH (lo setea Dockerfile.vercel) storage/ pasa a ese
// directorio, que se crea al vuelo porque /tmp arranca vacío en cada instancia.
if ($storagePath = getenv('APP_STORAGE_PATH')) {
    foreach (['app/private', 'framework/cache', 'framework/sessions', 'logs'] as $dir) {
        if (! is_dir("{$storagePath}/{$dir}")) {
            @mkdir("{$storagePath}/{$dir}", 0775, true);
        }
    }

    $app->useStoragePath($storagePath);
}

return $app;
