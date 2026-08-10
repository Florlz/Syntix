<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\Division;
use App\Models\Event;
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
    public function index(Request $request, Event $event): Response
    {
        $this->assertAdmin($request, $event);
        $event->load(['competitions.divisions' => fn ($q) => $q->with([
            'governingRuleVersion.criteria', 'entries.rosterMembers', 'contests.resultSubmissions',
            'schedules.currentPublication',
        ])]);

        return Inertia::render('Admin/Sports/Index', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name, 'archived' => $event->isArchived()],
            'sports' => $event->competitions->map(function (Competition $sport): array {
                $contests = $sport->divisions->flatMap->contests;
                return [
                    'id' => (string) $sport->getKey(), 'name' => $sport->name, 'slug' => $sport->slug,
                    'active' => (bool) $sport->is_active, 'deactivation_reason' => $sport->deactivation_reason,
                    'has_history' => $contests->isNotEmpty(),
                    'counts' => [
                        'entries' => $sport->divisions->sum(fn ($d) => $d->entries->count()),
                        'players' => $sport->divisions->sum(fn ($d) => $d->entries->sum(fn ($e) => $e->rosterMembers->count())),
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
                            'schedule_published' => $division->schedules->contains(fn ($s) => $s->currentPublication !== null),
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
}
