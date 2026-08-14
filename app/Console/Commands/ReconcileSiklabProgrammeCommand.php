<?php

namespace App\Console\Commands;

use App\Actions\Events\ReconcileSiklabProgramme;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;

class ReconcileSiklabProgrammeCommand extends Command
{
    protected $signature = 'siklab:reconcile-programme {event : Event id or slug} {--admin= : Global admin user id}';

    protected $description = 'Reconcile proposal sports and combat disciplines for a SIKLAB event.';

    public function handle(ReconcileSiklabProgramme $reconcile): int
    {
        $event = Event::query()
            ->whereKey($this->argument('event'))
            ->orWhere('slug', $this->argument('event'))
            ->first();

        if ($event === null) {
            $this->error('Event not found.');

            return self::FAILURE;
        }

        $admin = User::query()
            ->when($this->option('admin'), fn ($query, $id) => $query->whereKey($id))
            ->where('is_global_admin', true)
            ->first();

        if ($admin === null) {
            $this->error('A global admin is required; pass --admin or create one first.');

            return self::FAILURE;
        }

        $reconcile->handle($admin, $event);
        $this->info('SIKLAB proposal sports and disciplines reconciled.');

        return self::SUCCESS;
    }
}
