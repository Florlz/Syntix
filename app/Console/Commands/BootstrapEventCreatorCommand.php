<?php

namespace App\Console\Commands;

use App\Actions\Identity\BootstrapEventCreator;
use Illuminate\Console\Command;

class BootstrapEventCreatorCommand extends Command
{
    protected $signature = 'syntix:bootstrap-event-creator
        {--name= : Institutional name}
        {--email= : Institutional email}
        {--password= : One-time password used for initial secure setup}';

    protected $description = 'Create the one-time platform event_creator account without creating an event role';

    public function handle(BootstrapEventCreator $bootstrap): int
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

        $this->info("Bootstrapped event creator {$user->email}. No event role was granted.");

        return self::SUCCESS;
    }
}
