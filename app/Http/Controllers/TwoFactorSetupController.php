<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

class TwoFactorSetupController extends Controller
{
    /**
     * Pantalla obligatoria para vincular la app autenticadora (QR + código).
     */
    public function show(Request $request, EnableTwoFactorAuthentication $enable): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            return redirect('/');
        }

        // Se genera el secreto una sola vez: si el usuario recarga la página
        // el QR sigue siendo el mismo hasta que confirme.
        if (empty($user->two_factor_secret)) {
            $enable($user);
            $user->refresh();
        }

        return view('auth.two-factor-setup', [
            'qrCodeSvg' => $user->twoFactorQrCodeSvg(),
            'secretKey' => Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
        ]);
    }

    /**
     * Muestra los códigos de recuperación una única vez, justo después de confirmar.
     */
    public function recoveryCodes(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('two-factor.setup');
        }

        if (! $request->session()->pull('two_factor.show_recovery_codes')) {
            return redirect('/');
        }

        return view('auth.recovery-codes', [
            'recoveryCodes' => $user->recoveryCodes(),
        ]);
    }
}
