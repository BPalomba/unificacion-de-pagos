<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsUsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordCommand extends Command
{
    use FindsUsers;

    protected $signature = 'user:reset-password
        {email : Email del usuario}
        {--password= : Contraseña nueva (si se omite, se pide de forma oculta)}';

    protected $description = 'Cambia la contraseña de un usuario y cierra sus sesiones abiertas';

    public function handle(): int
    {
        if (! $user = $this->findUser()) {
            return self::FAILURE;
        }

        $password = $this->option('password') ?? $this->secret('Contraseña nueva (mínimo 10 caracteres)');

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(10)],
        ]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password, 'remember_token' => null])->save();
        $this->logoutEverywhere($user);

        $this->info("Contraseña actualizada para {$user->email}.");

        return self::SUCCESS;
    }
}
