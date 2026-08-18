<?php

namespace App\Console\Commands;

use App\Actions\Events\BackfillSiklabScoringMetadata;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Command;

final class BackfillSiklabScoringMetadataCommand extends Command
{
    protected $signature = 'syntix:backfill-siklab-scoring {--event= : Event id or slug} {--admin= : Global admin user id} {--dry-run}';

    protected $description = 'Safely backfill SIKLAB scoring metadata without changing calculation behavior.';

    public function handle(BackfillSiklabScoringMetadata $backfill): int
    {
        if (! $this->option('event')) {
            $this->error('Pass --event with an Event id or slug.');

            return self::FAILURE;
        }

        $event = Event::query()->whereKey($this->option('event'))->orWhere('slug', $this->option('event'))->first();
        if ($event === null) {
            $this->error('Event not found.');

            return self::FAILURE;
        }

        $admin = User::query()
            ->when($this->option('admin'), fn ($query, $id) => $query->whereKey($id))
            ->where('is_global_admin', true)
            ->first();
        if (! $this->option('dry-run') && $admin === null) {
            $this->error('A global admin is required for a write backfill; pass --admin or create one first.');

            return self::FAILURE;
        }

        $report = $backfill->handle($event, (bool) $this->option('dry-run'), $admin);
        $this->info(($this->option('dry-run') ? 'Dry run: ' : '')."{$report['updated']} rule(s) would be updated; {$report['unchanged']} unchanged; {$report['skipped']} skipped.");

        return self::SUCCESS;
    }
}
