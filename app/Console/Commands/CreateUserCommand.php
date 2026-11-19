<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
        {--name= : Nombre y apellido}
        {--email= : Email con el que va a ingresar}
        {--password= : Contraseña (si se omite, se pide de forma oculta)}';

    protected $description = 'Crea un usuario para ingresar al sistema (no hay registro público)';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? $this->ask('Nombre y apellido'),
            'email' => mb_strtolower(trim((string) ($this->option('email') ?? $this->ask('Email')))),
            'password' => $this->option('password') ?? $this->secret('Contraseña (mínimo 10 caracteres)'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(10)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create($data);

        $this->info("Usuario creado: {$user->email}");
        $this->line('En el primer ingreso se le va a pedir configurar la verificación en dos pasos.');

        return self::SUCCESS;
    }
}
