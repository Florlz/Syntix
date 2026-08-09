<?php

namespace App\Console\Commands;

use App\Actions\Identity\BootstrapGlobalAdmin;
use Illuminate\Console\Command;

class BootstrapGlobalAdminCommand extends Command
{
    protected $signature = 'syntix:bootstrap-global-admin
        {--name= : Institutional name}
        {--email= : Institutional email}
        {--password= : Initial password}';

    protected $description = 'Create the sole platform-wide Global Admin account';

    public function handle(BootstrapGlobalAdmin $bootstrap): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Institutional name')));
        $email = trim((string) ($this->option('email') ?: $this->ask('Institutional email')));
        $password = (string) ($this->option('password') ?: $this->secret('Initial password'));

        if ($name === '' || $email === '' || $password === '') {
            $this->error('Name, email, and password are required. No account was created.');

            return self::FAILURE;
        }

        $user = $bootstrap->handle([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], 'artisan bootstrap command');

        $this->info("Global Admin {$user->email} is ready.");

        return self::SUCCESS;
    }
}
