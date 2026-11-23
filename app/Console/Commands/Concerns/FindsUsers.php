<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;

trait FindsUsers
{
    /**
     * Busca al usuario por email. Si no se pasa, usa el argumento {email}.
     */
    protected function findUser(?string $email = null): ?User
    {
        $email = mb_strtolower(trim((string) ($email ?? $this->argument('email'))));
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No existe un usuario con el email {$email}.");
        }

        return $user;
    }

    /**
     * Cierra las sesiones abiertas del usuario (solo aplica con SESSION_DRIVER=database,
     * que es el que se usa en desarrollo y en Vercel).
     */
    protected function logoutEverywhere(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->delete();
        }
    }
}
