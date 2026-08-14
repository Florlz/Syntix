<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\EligibilityStatus;
use App\Enums\EntryStatus;
use App\Enums\RosterMemberRole;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Event;
use App\Models\EventDelegation;
use App\Services\RosterReadModel;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SportController extends Controller
{
    public function show(Request $request, Event $event, Competition $sport, RosterReadModel $rosters): Response
    {
        $this->assertAdmin($request, $event);
        $this->assertContained($event, $sport);
        $sport->load([
            'draftCoverImage',
            'publishedCoverImage',
            'divisions.governingRuleVersion.criteria',
            'divisions.entries.delegation',
            'divisions.entries.rosterMembers.participant',
            'divisions.entries.eligibilityRecords',
            'divisions.contests.resultSubmissions',
            'divisions.tournaments',
            'divisions.schedules.currentPublication',
            'divisions.schedules.venue',
        ]);

        $tab = $request->string('tab')->toString();
        $tab = in_array($tab, ['overview', 'rosters', 'matches', 'schedule', 'results'], true) ? $tab : 'overview';
        $divisionId = $request->integer('division');
        $selectedDivision = $divisionId > 0 ? $sport->divisions->firstWhere('id', $divisionId) : null;
        if ($divisionId > 0 && $selectedDivision === null) {
            abort(404);
        }

        $departmentId = $request->integer('department');
        $selectedDepartment = null;
        if ($departmentId > 0) {
            $selectedDepartment = $event->delegations()
                ->where('is_active', true)
                ->whereKey($departmentId)
                ->first();
            if ($selectedDepartment === null) {
                abort(404);
            }
        }

        $rosterWorkspace = $tab === 'rosters' && $selectedDivision !== null
            ? $rosters->forDivision($event, $sport, $selectedDivision, $selectedDepartment)
            : null;

        return Inertia::render('Admin/Sports/Workspace', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name, 'archived' => $event->isArchived()],
            'sport' => $this->sportWorkspacePayload($event, $sport),
            'divisions' => $sport->divisions->map(fn (Division $division): array => $this->divisionWorkspacePayload($division))->values(),
            'selected_division' => $selectedDivision === null ? null : (string) $selectedDivision->getKey(),
            'active_tab' => $tab,
            'selected_department' => $selectedDepartment === null ? null : (string) $selectedDepartment->getKey(),
            'roster_workspace' => $rosterWorkspace,
            'roster_options' => [
                'roster_roles' => $this->enumOptions(RosterMemberRole::cases()),
                'eligibility_statuses' => $this->enumOptions(EligibilityStatus::cases()),
            ],
        ]);
    }

    public function index(Request $request, Event $event): Response
    {
        $this->assertAdmin($request, $event);
        $event->load(['competitions' => fn ($q) => $q->with([
            'draftCoverImage',
            'publishedCoverImage',
            'divisions.governingRuleVersion.criteria',
            'divisions.entries.rosterMembers',
            'divisions.entries.eligibilityRecords',
            'divisions.contests.resultSubmissions',
            'divisions.placements',
            'divisions.schedules.currentPublication',
        ])]);

        return Inertia::render('Admin/Sports/Index', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name, 'archived' => $event->isArchived()],
            'sports' => $event->competitions->map(function (Competition $sport) use ($event): array {
            $contests = $sport->divisions->flatMap->contests;
            $entries = $sport->divisions->flatMap->entries;
            $schedules = $sport->divisions->flatMap->schedules->sortBy('starts_at')->values();
            $draftCover = $sport->draftCoverImage;
            $publishedCover = $sport->publishedCoverImage;
            $cover = $draftCover ?? $publishedCover;
            $pendingResults = $contests->flatMap->resultSubmissions->where('state', 'submitted')->count()
                + $sport->divisions->flatMap->placements->where('state', 'submitted')->count();
            $unlockedRosters = $entries->filter(fn ($entry): bool => $entry->entryStatus() !== EntryStatus::Locked)->count();
            $scheduleChanges = $schedules->filter(fn ($schedule): bool => $schedule->hasUnpublishedChanges())->count();
            $nextSchedule = $schedules->first(fn ($schedule): bool => $schedule->starts_at?->isFuture() ?? false);
            if ($pendingResults > 0) {
                $attention = ['kind' => 'results', 'label' => $pendingResults.' result'.($pendingResults === 1 ? '' : 's').' need review', 'count' => $pendingResults];
            } elseif ($unlockedRosters > 0) {
                $attention = ['kind' => 'rosters', 'label' => $unlockedRosters.' roster'.($unlockedRosters === 1 ? '' : 's').' awaiting approval', 'count' => $unlockedRosters];
            } elseif ($scheduleChanges > 0) {
                $attention = ['kind' => 'schedule', 'label' => $scheduleChanges.' schedule change'.($scheduleChanges === 1 ? '' : 's').' not public', 'count' => $scheduleChanges];
            } elseif ($cover === null) {
                $attention = ['kind' => 'cover', 'label' => 'Add a sport image', 'count' => 0];
            } else {
                $attention = ['kind' => 'none', 'label' => 'No issues', 'count' => 0];
            }
            return [
                'id' => (string) $sport->getKey(), 'name' => $sport->name, 'slug' => $sport->slug,
                'active' => (bool) $sport->is_active, 'deactivation_reason' => $sport->deactivation_reason,
                'has_history' => $contests->isNotEmpty(),
                'cover' => $cover === null ? null : [
                    'url' => $draftCover !== null
                        ? route('admin.cover-images.preview', [$event, $draftCover])
                        : ($publishedCover?->public_path === null ? null : \Illuminate\Support\Facades\Storage::disk('public')->url($publishedCover->public_path)),
                    'alt' => $cover->alt_text,
                    'state' => $draftCover !== null ? 'draft' : 'published',
                ],
                'draft_cover' => $this->coverPayload($event, $draftCover),
                'published_cover' => $this->coverPayload($event, $publishedCover),
                'cover_state' => $draftCover !== null ? 'Draft image' : ($publishedCover !== null ? 'Public image' : 'No image'),
                'division_count' => $sport->divisions->where('is_active', true)->count(),
                'locked_entries' => $entries->filter(fn ($entry): bool => $entry->entryStatus() === EntryStatus::Locked)->count(),
                'player_count' => $entries->flatMap->rosterMembers->where('is_active', true)->pluck('participant_id')->unique()->count(),
                'attention' => $attention,
                'next_activity' => $nextSchedule === null ? null : [
                    'title' => $nextSchedule->title,
                    'starts_at' => $nextSchedule->starts_at?->toIso8601String(),
                    'division' => $nextSchedule->division?->name,
                    'public' => $nextSchedule->currentPublication !== null && ! $nextSchedule->hasUnpublishedChanges(),
                ],
                'counts' => [
                    'entries' => $entries->count(),
                    'players' => $entries->flatMap->rosterMembers->where('is_active', true)->pluck('participant_id')->unique()->count(),
                    'scheduled' => $contests->where('state', 'scheduled')->count(),
                        'live' => $contests->where('state', 'live')->count(),
                        'completed' => $contests->where('state', 'completed')->count(),
                        'official' => $contests->filter(fn ($c) => $c->resultSubmissions->where('state', 'approved')->isNotEmpty())->count(),
                    ],
                    'divisions' => $sport->divisions->map(function (Division $division): array {
                        $rule = $division->governingRuleVersion ?? $division->ruleVersions()->latest('version')->first();
                        return [
                            'id' => (string) $division->getKey(), 'name' => $division->name, 'slug' => $division->slug,
                            'active' => (bool) $division->is_active, 'deactivation_reason' => $division->deactivation_reason,
                            'scoring_started' => $division->hasScoringStarted(),
                            'format' => $rule?->format()?->value, 'participant_mode' => $rule?->participantMode()?->value,
                            'scoring_family' => $rule?->scoringFamily()?->value, 'rule_state' => $rule?->lifecycleState()->value ?? 'missing',
                            'roster_min' => $rule?->min_roster_size, 'roster_max' => $rule?->max_roster_size,
                            'blockers' => $rule?->readinessErrors() ?? ['No rule version is configured.'],
                            'schedule_published' => $division->schedules->contains(fn ($s) => $s->currentPublication !== null && ! $s->hasUnpublishedChanges()),
                        ];
                    })->values(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->assertWritable($request, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255'], 'division_name' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($request, $event, $data, $audit): void {
            $sport = $event->competitions()->create(['name' => $data['name'], 'slug' => Str::slug($data['slug'] ?: $data['name']), 'is_active' => true]);
            $division = $sport->divisions()->create(['name' => $data['division_name'], 'slug' => Str::slug($data['division_name']), 'is_active' => true]);
            $audit->record($request->user(), AuditAction::CompetitionCreated, $sport, $event, after: ['name' => $sport->name]);
            $audit->record($request->user(), AuditAction::DivisionCreated, $division, $event, after: ['name' => $division->name]);
        });
        return back()->with('status', 'Sport and first division created.');
    }

    public function update(Request $request, Event $event, Competition $sport, AuditLogger $audit): RedirectResponse
    {
        $this->assertContained($event, $sport); $this->assertWritable($request, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255']]);
        if ($sport->divisions()->whereHas('contests')->exists() && $data['slug'] !== $sport->slug) throw ValidationException::withMessages(['slug' => 'The slug is locked after public history exists.']);
        $before = $sport->only('name', 'slug'); $sport->update(['name' => $data['name'], 'slug' => Str::slug($data['slug'])]);
        $audit->record($request->user(), AuditAction::CompetitionUpdated, $sport, $event, $before, $sport->only('name', 'slug'));
        return back()->with('status', 'Sport details updated.');
    }

    public function state(Request $request, Event $event, Competition $sport, AuditLogger $audit): RedirectResponse
    {
        $this->assertContained($event, $sport); $this->assertWritable($request, $event);
        $data = $request->validate(['active' => ['required', 'boolean'], 'reason' => ['required_if:active,false', 'nullable', 'string', 'max:2000']]);
        if (! $data['active'] && $sport->divisions()->whereHas('contests', fn ($q) => $q->where('state', 'live'))->exists()) throw ValidationException::withMessages(['reason' => 'A sport cannot be deactivated while a contest is live.']);
        $before = ['active' => (bool) $sport->is_active]; $sport->update(['is_active' => $data['active'], 'deactivation_reason' => $data['active'] ? null : $data['reason'], 'deactivated_at' => $data['active'] ? null : now()]);
        $audit->record($request->user(), AuditAction::CompetitionStateChanged, $sport, $event, $before, ['active' => (bool) $sport->is_active], $data['reason'] ?? null);
        return back()->with('status', $data['active'] ? 'Sport reactivated.' : 'Sport deactivated; history was preserved.');
    }

    public function storeDivision(Request $request, Event $event, Competition $sport, AuditLogger $audit): RedirectResponse
    {
        $this->assertContained($event, $sport); $this->assertWritable($request, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255']]);
        $division = $sport->divisions()->create(['name' => $data['name'], 'slug' => Str::slug($data['slug'] ?: $data['name']), 'is_active' => true]);
        $audit->record($request->user(), AuditAction::DivisionCreated, $division, $event, after: ['name' => $division->name]);
        return back()->with('status', 'Division created.');
    }

    public function updateDivision(Request $request, Event $event, Division $division, AuditLogger $audit): RedirectResponse
    {
        $this->assertDivisionContained($event, $division); $this->assertWritable($request, $event);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'string', 'max:255']]);
        if ($division->contests()->exists() && $data['slug'] !== $division->slug) throw ValidationException::withMessages(['slug' => 'The slug is locked after contest history exists.']);
        $before = $division->only('name', 'slug'); $division->update(['name' => $data['name'], 'slug' => Str::slug($data['slug'])]);
        $audit->record($request->user(), AuditAction::DivisionUpdated, $division, $event, $before, $division->only('name', 'slug'));
        return back()->with('status', 'Division updated.');
    }

    public function divisionState(Request $request, Event $event, Division $division, AuditLogger $audit): RedirectResponse
    {
        $this->assertDivisionContained($event, $division); $this->assertWritable($request, $event);
        $data = $request->validate(['active' => ['required', 'boolean'], 'reason' => ['required_if:active,false', 'nullable', 'string', 'max:2000']]);
        if (! $data['active'] && $division->contests()->where('state', 'live')->exists()) throw ValidationException::withMessages(['reason' => 'A division cannot be deactivated while a contest is live.']);
        $before = ['active' => (bool) $division->is_active]; $division->update(['is_active' => $data['active'], 'deactivation_reason' => $data['active'] ? null : $data['reason'], 'deactivated_at' => $data['active'] ? null : now()]);
        $audit->record($request->user(), AuditAction::DivisionStateChanged, $division, $event, $before, ['active' => (bool) $division->is_active], $data['reason'] ?? null);
        return back()->with('status', $data['active'] ? 'Division reactivated.' : 'Division deactivated; history was preserved.');
    }

    private function assertAdmin(Request $request, Event $event): void { if (! $request->user()->hasAdminAccess($event)) throw new AuthorizationException('Only the Global Admin can manage sports.'); }
    private function assertWritable(Request $request, Event $event): void { $this->assertAdmin($request, $event); if ($event->isArchived()) throw new AuthorizationException('Archived events are read-only.'); }
    private function assertContained(Event $event, Competition $sport): void { if ((int) $sport->event_id !== (int) $event->getKey()) abort(404); }
    private function assertDivisionContained(Event $event, Division $division): void { $division->loadMissing('competition'); if ((int) $division->competition?->event_id !== (int) $event->getKey()) abort(404); }

    private function coverPayload(Event $event, $cover): ?array
    {
        if ($cover === null) return null;
        return [
            'id' => (string) $cover->getKey(),
            'revision' => (int) $cover->revision,
            'alt_text' => $cover->alt_text,
            'state' => $cover->state instanceof \BackedEnum ? $cover->state->value : (string) $cover->state,
            'preview_url' => route('admin.cover-images.preview', [$event, $cover]),
            'public_url' => $cover->public_path === null ? null : \Illuminate\Support\Facades\Storage::disk('public')->url($cover->public_path),
            'published_at' => $cover->published_at?->toIso8601String(),
        ];
    }

    /** @param array<int, \BackedEnum> $cases */
    private function enumOptions(array $cases): array
    {
        return array_map(static fn ($case): array => [
            'value' => $case->value,
            'label' => Str::headline($case->value),
        ], $cases);
    }

    /** @return array<string, mixed> */
    private function sportWorkspacePayload(Event $event, Competition $sport): array
    {
        $entries = $sport->divisions->flatMap->entries;
        $draft = $sport->draftCoverImage;
        $published = $sport->publishedCoverImage;
        $cover = $draft ?? $published;

        return [
            'id' => (string) $sport->getKey(),
            'name' => $sport->name,
            'slug' => $sport->slug,
            'active' => (bool) $sport->is_active,
            'deactivation_reason' => $sport->deactivation_reason,
            'cover' => $cover === null ? null : [
                'url' => $draft !== null
                    ? route('admin.cover-images.preview', [$event, $draft])
                    : ($published?->public_path === null ? null : \Illuminate\Support\Facades\Storage::disk('public')->url($published->public_path)),
                'alt' => $cover->alt_text,
            ],
            'draft_cover' => $this->coverPayload($event, $draft),
            'published_cover' => $this->coverPayload($event, $published),
            'division_count' => $sport->divisions->where('is_active', true)->count(),
            'entry_count' => $entries->count(),
            'player_count' => $entries->flatMap->rosterMembers->where('is_active', true)->pluck('participant_id')->unique()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function divisionWorkspacePayload(Division $division): array
    {
        $rule = $division->governingRuleVersion ?? $division->ruleVersions()->latest('version')->first();
        $entries = $division->entries;
        $schedule = $division->schedules->sortBy('starts_at')->first(fn ($item): bool => $item->starts_at?->isFuture() ?? false);
        $tournament = $division->tournaments->sortByDesc('id')->first(fn ($item): bool => in_array($item->tournamentState()->value, ['preview', 'published', 'uncontested'], true));

        return [
            'id' => (string) $division->getKey(),
            'name' => $division->name,
            'active' => (bool) $division->is_active,
            'entry_count' => $entries->count(),
            'locked_entry_count' => $entries->filter(fn ($entry): bool => $entry->entryStatus() === EntryStatus::Locked)->count(),
            'player_count' => $entries->flatMap->rosterMembers->where('is_active', true)->pluck('participant_id')->unique()->count(),
            'unlocked_entry_count' => $entries->filter(fn ($entry): bool => $entry->entryStatus() !== EntryStatus::Locked)->count(),
            'format' => $rule?->format()?->value,
            'participant_mode' => $rule?->participantMode()?->value,
            'rule_state' => $rule?->lifecycleState()->value ?? 'missing',
            'blockers' => $rule?->readinessErrors() ?? ['No rule version is configured.'],
            'bracket_state' => $tournament?->tournamentState()->value ?? 'not_generated',
            'schedule_state' => $schedule === null ? 'not_scheduled' : (($schedule->currentPublication !== null && ! $schedule->hasUnpublishedChanges()) ? 'published' : 'draft'),
            'next_schedule' => $schedule === null ? null : ['title' => $schedule->title, 'starts_at' => $schedule->starts_at?->toIso8601String(), 'venue' => $schedule->venue?->name],
        ];
    }
}
