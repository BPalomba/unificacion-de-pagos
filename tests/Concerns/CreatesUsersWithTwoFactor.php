<?php

namespace Tests\Concerns;

use App\Models\User;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

trait CreatesUsersWithTwoFactor
{
    protected function currentCode(User $user): string
    {
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /**
     * Usuario con MFA ya confirmado (puede usar la app sin pasar por el setup).
     */
    protected function userWithTwoFactor(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(EnableTwoFactorAuthentication::class)($user);
        app(ConfirmTwoFactorAuthentication::class)($user, $this->currentCode($user));

        // El código usado para confirmar queda "quemado" en la cache.
        cache()->flush();

        return $user->fresh();
    }
}
