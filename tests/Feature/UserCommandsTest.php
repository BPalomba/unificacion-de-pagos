<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class UserCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_create_creates_a_user(): void
    {
        $this->artisan('user:create', [
            '--name' => 'Ana Pérez',
            '--email' => 'Ana@Hospital.test',
            '--password' => 'una-clave-segura',
        ])->assertSuccessful();

        $user = User::where('email', 'ana@hospital.test')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('una-clave-segura', $user->password));
    }

    public function test_user_create_rejects_short_passwords_and_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'ana@hospital.test']);

        $this->artisan('user:create', [
            '--name' => 'Ana', '--email' => 'ana@hospital.test', '--password' => 'una-clave-segura',
        ])->assertFailed();

        $this->artisan('user:create', [
            '--name' => 'Juan', '--email' => 'juan@hospital.test', '--password' => 'corta',
        ])->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_user_reset_password(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:reset-password', ['email' => $user->email, '--password' => 'otra-clave-segura'])
            ->assertSuccessful();

        $this->assertTrue(Hash::check('otra-clave-segura', $user->fresh()->password));
    }

    public function test_user_reset_2fa_forces_setup_again(): void
    {
        $user = User::factory()->create();
        app(EnableTwoFactorAuthentication::class)($user);
        $secret = Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret);
        app(ConfirmTwoFactorAuthentication::class)($user, app(Google2FA::class)->getCurrentOtp($secret));
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());

        $this->artisan('user:reset-2fa', ['email' => $user->email])->assertSuccessful();

        $user->refresh();
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());
        $this->assertNull($user->two_factor_secret);
        $this->actingAs($user)->get('/')->assertRedirect(route('two-factor.setup'));
    }

    public function test_user_delete(): void
    {
        $user = User::factory()->create();

        $this->artisan('user:delete', ['email' => $user->email, '--force' => true])->assertSuccessful();

        $this->assertModelMissing($user);
    }

    public function test_commands_fail_for_unknown_email(): void
    {
        $this->artisan('user:reset-2fa', ['email' => 'nadie@hospital.test'])->assertFailed();
    }
}
