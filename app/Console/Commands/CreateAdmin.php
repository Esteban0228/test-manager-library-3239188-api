<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** T-17 / D-10: el arranque crea el admin inicial con credenciales de entorno (idempotente). */
class CreateAdmin extends Command
{
    protected $signature = 'library:create-admin';
    protected $description = 'Crea el administrador inicial desde ADMIN_USERNAME y ADMIN_PASSWORD si no existe.';

    public function handle(): int
    {
        $username = AuthService::normalize((string) config('library.admin_username'));
        $password = (string) config('library.admin_password');

        if ($username === '' || $password === '') {
            $this->error('Faltan ADMIN_USERNAME o ADMIN_PASSWORD en el entorno.');

            return self::FAILURE;
        }

        if (User::query()->where('username', $username)->exists()) {
            $this->info("El administrador «{$username}» ya existe.");

            return self::SUCCESS;
        }

        User::create(['username' => $username, 'password_hash' => Hash::make($password), 'role' => 'admin']);
        $this->info("Administrador «{$username}» creado.");

        return self::SUCCESS;
    }
}
