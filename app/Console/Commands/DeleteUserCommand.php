<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\FindsUsers;
use Illuminate\Console\Command;

class DeleteUserCommand extends Command
{
    use FindsUsers;

    protected $signature = 'user:delete
        {email : Email del usuario}
        {--force : No pedir confirmación}';

    protected $description = 'Da de baja a un usuario y cierra sus sesiones abiertas';

    public function handle(): int
    {
        if (! $user = $this->findUser()) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("¿Eliminar al usuario {$user->email}?")) {
            $this->line('Cancelado.');

            return self::SUCCESS;
        }

        $this->logoutEverywhere($user);
        $user->delete();

        $this->info("Usuario {$user->email} eliminado.");

        return self::SUCCESS;
    }
}
