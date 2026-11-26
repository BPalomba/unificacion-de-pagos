<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsUsers;
use Illuminate\Console\Command;

class ResetUserTwoFactorCommand extends Command
{
    use FindsUsers;

    protected $signature = 'user:reset-2fa {email : Email del usuario}';

    protected $description = 'Desvincula la app autenticadora de un usuario (por ejemplo, si perdió el celular y los códigos de recuperación)';

    public function handle(): int
    {
        if (! $user = $this->findUser()) {
            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'remember_token' => null,
        ])->save();
        $this->logoutEverywhere($user);

        $this->info("Verificación en dos pasos reiniciada para {$user->email}.");
        $this->line('En el próximo ingreso va a tener que volver a escanear el código QR.');

        return self::SUCCESS;
    }
}
