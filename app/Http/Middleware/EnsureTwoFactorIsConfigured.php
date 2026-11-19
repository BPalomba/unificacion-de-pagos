<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El MFA es obligatorio: un usuario logueado que todavía no confirmó su app
 * autenticadora no puede usar el sistema hasta configurarla.
 */
class EnsureTwoFactorIsConfigured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasEnabledTwoFactorAuthentication()) {
            abort_if($request->expectsJson(), 403, 'Tenés que configurar la autenticación en dos pasos.');

            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
