<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\TwoFactorConfirmedResponse as TwoFactorConfirmedResponseContract;

class TwoFactorConfirmedResponse implements TwoFactorConfirmedResponseContract
{
    /**
     * Una vez confirmado el MFA, los códigos de recuperación se muestran una
     * sola vez (el flag de sesión se consume al ver la página).
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 200);
        }

        $request->session()->put('two_factor.show_recovery_codes', true);

        return redirect()->route('two-factor.setup.recovery-codes');
    }
}
