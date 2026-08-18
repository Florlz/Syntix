<?php

namespace Database\Seeders;

use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Identity\BootstrapGlobalAdmin;
use App\Enums\AccountState;
use App\Enums\EventState;
use App\Models\Entry;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DevelopmentAdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('is_global_admin', true)->first();

        if ($admin !== null && $admin->email !== 'admin@syntix.test') {
            throw new \DomainException('A different Global Admin already exists; the development account was not created.');
        }

        $admin ??= (new BootstrapGlobalAdmin)->handle([
            'name' => 'SYNTIX Development Admin',
            'email' => 'admin@syntix.test',
            'password' => 'password',
        ], 'local development seeder');

        $admin->forceFill([
            'account_state' => AccountState::Active,
            'is_global_admin' => true,
        ])->save();

        $event = Event::query()->firstOrCreate(
            ['slug' => 'siklab-2026'],
            [
                'name' => 'SIKLAB 2026',
                'state' => EventState::Preparation,
                'created_by' => $admin->getKey(),
            ],
        );

        (new ApplySiklab2025Programme)->handle($admin, $event);
        Entry::query()
            ->whereHas('division.competition', fn ($query) => $query->whereBelongsTo($event))
            ->delete();
        Venue::query()->whereBelongsTo($event)->delete();
    }
}
