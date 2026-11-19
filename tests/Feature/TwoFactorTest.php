<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(User $user): string
    {
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);

        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function userWithTwoFactor(): User
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        app(ConfirmTwoFactorAuthentication::class)($user, $this->currentCode($user));

        // El código usado para confirmar queda "quemado" en la cache.
        cache()->flush();

        return $user->fresh();
    }

    public function test_user_without_two_factor_is_forced_to_set_it_up(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($user)->get('/copagos/exportar')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($user)->post('/copagos')->assertRedirect(route('two-factor.setup'));
    }

    public function test_setup_page_shows_qr_and_keeps_the_same_secret_on_reload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/two-factor/setup')
            ->assertOk()
            ->assertSee('<svg', false);

        $secret = $user->fresh()->two_factor_secret;
        $this->assertNotNull($secret);

        $this->actingAs($user)->get('/two-factor/setup')->assertOk();
        $this->assertSame($secret, $user->fresh()->two_factor_secret);
    }

    public function test_confirming_with_valid_code_shows_recovery_codes_once_and_unlocks_the_app(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/two-factor/setup');

        $this->actingAs($user)
            ->post('/user/confirmed-two-factor-authentication', ['code' => $this->currentCode($user)])
            ->assertRedirect(route('two-factor.setup.recovery-codes'));

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);

        $codes = $user->fresh()->recoveryCodes();
        $this->actingAs($user)->get('/two-factor/recovery-codes')
            ->assertOk()
            ->assertSee($codes[0]);

        // Solo se muestran una vez.
        $this->actingAs($user)->get('/two-factor/recovery-codes')->assertRedirect('/');

        // Ya configurado, el setup no se vuelve a mostrar.
        $this->actingAs($user)->get('/two-factor/setup')->assertRedirect('/');
    }

    public function test_confirming_with_invalid_code_fails(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/two-factor/setup');

        $this->actingAs($user)
            ->from('/two-factor/setup')
            ->post('/user/confirmed-two-factor-authentication', ['code' => '000000'])
            ->assertRedirect('/two-factor/setup')
            ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');

        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }

    public function test_login_with_two_factor_requires_the_challenge(): void
    {
        $user = $this->userWithTwoFactor();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/two-factor-challenge');

        $this->assertGuest();
        $this->get('/two-factor-challenge')->assertOk()->assertSee('Verificación en dos pasos');
    }

    public function test_valid_totp_code_completes_login(): void
    {
        $user = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => $this->currentCode($user)])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_totp_code_is_rejected(): void
    {
        $user = $this->userWithTwoFactor();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => '000000'])
            ->assertRedirect('/two-factor-challenge')
            ->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_recovery_code_completes_login_and_is_consumed(): void
    {
        $user = $this->userWithTwoFactor();
        $recoveryCode = $user->recoveryCodes()[0];

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $recoveryCode])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recoveryCode, $user->fresh()->recoveryCodes());
    }
}
