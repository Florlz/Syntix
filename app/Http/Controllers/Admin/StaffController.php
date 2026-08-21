<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Assignments\RevokeScoringAssignment;
use App\Actions\Events\GrantEventRole;
use App\Actions\Events\RevokeEventRole;
use App\Actions\Identity\DisableUser;
use App\Actions\Identity\EnableUser;
use App\Actions\Scoring\ConfigureJudgingPanel;
use App\Actions\Scoring\LockJudgingPanel;
use App\Actions\Scoring\PrepareJudgedContest;
use App\Actions\Scoring\ResolveJudgedTie;
use App\Enums\AuditAction;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\EventUserRole;
use App\Models\ScoringAssignment;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use App\Services\ContestScheduleReadModel;
use App\Services\JudgeScoreAggregationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function index(Request $request, Event $event, ContestScheduleReadModel $schedules): Response
    {
        $this->assertAdmin($request, $event);
        $contests = Contest::query()
            ->whereHas('division.competition', fn ($query) => $query->where('event_id', $event->getKey()))
            ->with([
                'division.competition',
                'entries',
                'scorecards.judge',
                'assignments.user',
            ])
            ->get();
        $roles = $event->userRoles()->whereIn('role', [EventRole::Judge->value, EventRole::Tabulator->value])
            ->with([
                'user',
                'user.eventRoles.event',
                'user.scoringAssignments' => fn ($q) => $q
                    ->where('event_id', $event->getKey())
                    ->with([
                        'division.competition',
                        'contest.division.competition',
                        'entryScorecard.entry',
                        'entryScorecard.contest.division.competition',
                    ]),
                'user.userInvitations' => fn ($q) => $q->where('event_id', $event->getKey())->latest('id'),
            ])
            ->get()->groupBy('user_id');

        $staff = $roles->map(function ($memberships) use ($event, $contests): array {
            $user = $memberships->first()->user;
            $activeRoles = $memberships->whereNull('revoked_at');
            $assignments = $user->scoringAssignments->whereNull('revoked_at');
            $invitation = $user->userInvitations->first();
            $coverage = $this->staffCoverage($assignments, $contests);
            $coverage['missing_roles'] = collect([
                $activeRoles->contains(fn ($role): bool => $role->role === EventRole::Judge) && $coverage['judging_panels'] === []
                    ? EventRole::Judge->value
                    : null,
                $activeRoles->contains(fn ($role): bool => $role->role === EventRole::Tabulator) && $coverage['tabulator_targets'] === []
                    ? EventRole::Tabulator->value
                    : null,
            ])->filter()->values()->all();

            return [
                'id' => (string) $user->getKey(), 'name' => $user->name, 'email' => $user->email,
                'account_state' => $user->accountState()->value, 'disabled_reason' => $user->disable_reason,
                'roles' => $activeRoles->map(fn ($r) => ['id' => (string) $r->getKey(), 'role' => $r->role->value])->values(),
                'assignments' => $assignments->map(fn (ScoringAssignment $a) => ['id' => (string) $a->getKey(), 'scope' => $a->scopeType()->value, 'label' => $this->assignmentLabel($a)])->values(),
                'judging_assignments' => collect($coverage['judging_panels'])->map(fn (array $panel): array => [
                    'id' => $panel['contest_id'],
                    'scope' => 'judging_panel',
                    'label' => $panel['label'],
                    'scorecard_count' => $panel['scorecard_count'],
                    'locked' => $panel['locked'],
                ])->values(),
                'tabulator_assignments' => $coverage['tabulator_targets'],
                'coverage' => $coverage,
                'invitation' => $invitation ? ['state' => $invitation->invitationState()->value, 'expires_at' => $invitation->expires_at?->toIso8601String()] : null,
                'event_memberships' => $user->eventRoles->whereNull('revoked_at')->groupBy('event_id')->map(fn ($items) => ['event' => $items->first()->event?->name, 'roles' => $items->pluck('role')->map(fn ($r) => $r->value)->values()])->values(),
                'audit' => AuditLog::query()->where('event_id', $event->getKey())->where('target_id', (string) $user->getKey())->latest()->limit(10)->get()->map(fn ($log) => ['action' => $log->action, 'reason' => $log->reason, 'at' => $log->created_at?->toIso8601String()]),
            ];
        })->sortBy('name')->values();

        $section = in_array($request->query('section'), ['people', 'assignments', 'readiness'], true)
            ? $request->query('section')
            : 'people';

        return Inertia::render('Admin/Staff/Index', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name, 'archived' => $event->isArchived()],
            'section' => $section,
            'staff' => $staff,
            'staff_summary' => [
                'people' => $staff->count(),
                'active' => $staff->where('account_state', 'active')->count(),
                'judges' => $staff->filter(fn (array $person): bool => collect($person['roles'])->contains('role', EventRole::Judge->value))->count(),
                'tabulators' => $staff->filter(fn (array $person): bool => collect($person['roles'])->contains('role', EventRole::Tabulator->value))->count(),
                'needs_assignment' => $staff->filter(fn (array $person): bool => $person['coverage']['missing_roles'] !== [])->count(),
            ],
            'targets' => $this->targets($event),
            'readiness' => $this->scoringReadiness($event, $schedules),
        ]);
    }

    public function prepareJudgedContest(Request $request, Event $event, Division $division, PrepareJudgedContest $prepare): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($division->eventId() !== (int) $event->getKey()) abort(404);
        $prepare->handle($request->user(), $division);

        return back()->with('status', 'Judged Contest prepared.');
    }

    public function storeJudgingPanel(Request $request, Event $event, Contest $contest, ConfigureJudgingPanel $configure): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($contest->eventId() !== (int) $event->getKey()) abort(404);
        $data = $request->validate(['judge_ids' => ['required', 'array', 'min:1'], 'judge_ids.*' => ['integer', 'distinct', 'exists:users,id']]);
        $configure->handle($request->user(), $contest, User::query()->whereKey($data['judge_ids'])->get());

        return back()->with('status', 'Judging panel configured.');
    }

    public function lockJudgingPanel(Request $request, Event $event, Contest $contest, LockJudgingPanel $lock): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($contest->eventId() !== (int) $event->getKey()) abort(404);
        $lock->handle($request->user(), $contest);

        return back()->with('status', 'Judging panel locked.');
    }

    public function confirmAggregation(Request $request, Event $event, Contest $contest): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($contest->eventId() !== (int) $event->getKey()) abort(404);
        $data = $request->validate([
            'method' => ['required', Rule::in(['average'])],
            'reference' => ['required', 'string', 'max:500'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $contest->ruleVersion?->confirmAggregation($request->user(), $data['method'], $data['reference'], $data['reason']);

        return back()->with('status', 'Judge aggregation authority recorded.');
    }

    public function authorizeDeduction(Request $request, Event $event, Contest $contest): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($contest->eventId() !== (int) $event->getKey()) abort(404);
        $data = $request->validate([
            'rounding_policy' => ['nullable', Rule::in(['ceiling', 'floor', 'nearest'])],
            'reference' => ['required', 'string', 'max:500'], 'reason' => ['required', 'string', 'max:2000'],
        ]);
        $contest->ruleVersion?->authorizeDeductionCalculation($request->user(), $data['rounding_policy'] ?? null, $data['reference'], $data['reason']);

        return back()->with('status', 'Deduction calculation authority recorded.');
    }

    public function assignTabulator(Request $request, Event $event, Contest $contest, User $user, GrantScoringAssignment $grant): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($contest->eventId() !== (int) $event->getKey() || ! $user->hasActiveEventRole($event, EventRole::Tabulator)) abort(404);
        $grant->handle($request->user(), $event, $user, ScoringAssignmentScope::Contest, $contest, 'Assigned from Scoring Readiness.');

        return back()->with('status', 'Tabulator assigned.');
    }

    public function resolveJudgedTie(Request $request, Event $event, Contest $contest, ResolveJudgedTie $resolve): RedirectResponse
    {
        $this->assertWritable($request, $event);
        if ($contest->eventId() !== (int) $event->getKey()) abort(404);
        $data = $request->validate([
            'tied_entry_ids' => ['required', 'array', 'min:2'], 'tied_entry_ids.*' => ['integer', 'distinct'],
            'authorized_order' => ['required', 'array', 'min:2'], 'authorized_order.*' => ['integer', 'distinct'],
            'reason' => ['required', 'string', 'max:2000'], 'reference' => ['required', 'string', 'max:500'],
        ]);
        $resolve->handle($request->user(), $contest, $data['tied_entry_ids'], $data['authorized_order'], $data['reason'], $data['reference']);

        return back()->with('status', 'Authorized judged tie order recorded.');
    }

    public function reissue(Request $request, Event $event, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->assertWritable($request, $event); $this->assertMember($event, $user);
        $roleValues = $event->userRoles()
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->pluck('role')
            ->map(fn ($role): string => $role instanceof EventRole ? $role->value : (string) $role)
            ->unique()
            ->values();
        $roleLabel = match (true) {
            $roleValues->contains(EventRole::Judge->value) && $roleValues->contains(EventRole::Tabulator->value) => 'Judge & Tabulator',
            $roleValues->contains(EventRole::Judge->value) => 'Judge',
            $roleValues->contains(EventRole::Tabulator->value) => 'Tabulator',
            default => 'Event staff',
        };
        $invitation = DB::transaction(function () use ($request, $event, $user, $audit): array {
            UserInvitation::query()->where('user_id', $user->getKey())->where('event_id', $event->getKey())->whereNull('consumed_at')->update(['expires_at' => now()]);
            $token = Str::random(64);
            $invitation = UserInvitation::create(['user_id' => $user->getKey(), 'event_id' => $event->getKey(), 'token_hash' => hash('sha256', $token), 'invited_by' => $request->user()->getKey(), 'expires_at' => now()->addHours(24)]);
            $audit->record($request->user(), AuditAction::InvitationReissued, $user, $event, after: ['expires_at' => $invitation->expires_at->toIso8601String()]);
            return ['token' => $token, 'expires_at' => $invitation->expires_at?->toIso8601String()];
        });
        return back()
            ->with('setup_url', route('account.setup', ['token' => $invitation['token']]))
            ->with('setup_invitation', [
                'name' => $user->name,
                'role_label' => $roleLabel,
                'expires_at' => $invitation['expires_at'],
            ])
            ->with('status', 'A new one-time setup link was issued; every earlier unused link is invalid.');
    }

    public function grantRole(Request $request, Event $event, User $user, GrantEventRole $grant): RedirectResponse
    {
        $this->assertWritable($request, $event); $this->assertMember($event, $user);
        $data = $request->validate(['role' => ['required', Rule::in([EventRole::Judge->value, EventRole::Tabulator->value])]]);
        $grant->handle($request->user(), $event, $user, EventRole::from($data['role']), 'Additional role granted from Event Staff.');
        return back()->with('status', 'Event role granted.');
    }

    public function revokeRole(Request $request, Event $event, EventUserRole $membership, RevokeEventRole $revokeRole, RevokeScoringAssignment $revokeAssignment): RedirectResponse
    {
        $this->assertWritable($request, $event); if ((int) $membership->event_id !== (int) $event->getKey()) abort(404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $event, $membership, $data, $revokeRole, $revokeAssignment): void {
            $incompatible = $membership->role === EventRole::Judge ? ['entry_scorecard'] : ['contest'];
            ScoringAssignment::query()->active()->where('event_id', $event->getKey())->where('user_id', $membership->user_id)->whereIn('scope_type', $incompatible)->get()->each(fn ($a) => $revokeAssignment->handle($a, $request->user(), $data['reason']));
            $revokeRole->handle($membership, $request->user(), $data['reason']);
        });
        return back()->with('status', 'Role and incompatible assignments revoked.');
    }

    public function grantAssignment(Request $request, Event $event, User $user, GrantScoringAssignment $grant): RedirectResponse
    {
        $this->assertWritable($request, $event); $this->assertMember($event, $user);
        $data = $request->validate(['scope_type' => ['required', Rule::enum(ScoringAssignmentScope::class)], 'target_id' => ['required', 'integer']]);
        $scope = ScoringAssignmentScope::from($data['scope_type']); $target = $this->target($scope, (int) $data['target_id']);
        if (ScoringAssignment::eventIdForTarget($target) !== (int) $event->getKey()) abort(404);
        $requiredRole = $scope === ScoringAssignmentScope::EntryScorecard ? EventRole::Judge : EventRole::Tabulator;
        if (! $user->hasActiveEventRole($event, $requiredRole)) throw new AuthorizationException('The matching active event role is required.');
        $grant->handle($request->user(), $event, $user, $scope, $target, 'Granted from Event Staff.');
        return back()->with('status', 'Assignment granted.');
    }

    public function revokeAssignment(Request $request, Event $event, ScoringAssignment $assignment, RevokeScoringAssignment $revoke): RedirectResponse
    {
        $this->assertWritable($request, $event); if ((int) $assignment->event_id !== (int) $event->getKey()) abort(404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]); $revoke->handle($assignment, $request->user(), $data['reason']);
        return back()->with('status', 'Assignment revoked.');
    }

    public function disable(Request $request, Event $event, User $user, DisableUser $disable): RedirectResponse
    {
        $this->assertWritable($request, $event); $this->assertMember($event, $user); $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $disable->handle($request->user(), $user, $data['reason'], $event); return back()->with('status', 'Account disabled platform-wide and active sessions revoked.');
    }

    public function enable(Request $request, Event $event, User $user, EnableUser $enable): RedirectResponse
    {
        $this->assertWritable($request, $event); $this->assertMember($event, $user); $enable->handle($request->user(), $user, $event); return back()->with('status', 'Account reactivated. Existing active grants can provide access again.');
    }

    private function targets(Event $event): array
    {
        return [
            'competition_division' => Division::query()->whereHas('competition', fn ($q) => $q->where('event_id', $event->getKey())->where('is_active', true))->where('is_active', true)->with('competition')->get()->map(fn ($d) => ['id' => (string) $d->getKey(), 'label' => $d->competition->name.' / '.$d->name]),
            'contest' => Contest::query()->whereHas('division.competition', fn ($q) => $q->where('event_id', $event->getKey())->where('is_active', true))->whereHas('division', fn ($q) => $q->where('is_active', true))->with('division.competition')->get()->map(fn ($c) => ['id' => (string) $c->getKey(), 'label' => $c->division->competition->name.' / '.$c->name]),
        ];
    }

    /**
     * Shape assignment records for people rather than exposing scorecard rows
     * as if they were separate operational assignments.
     *
     * @param  iterable<ScoringAssignment>  $assignments
     * @param  iterable<Contest>  $contests
     * @return array{judging_panels: list<array<string, mixed>>, tabulator_targets: list<array<string, mixed>>, missing_roles: list<string>, total: int}
     */
    private function staffCoverage(iterable $assignments, iterable $contests): array
    {
        $assignments = collect($assignments)->values();
        $contestIndex = collect($contests)->keyBy(fn (Contest $contest): string => (string) $contest->getKey());
        $judgingPanels = $assignments
            ->filter(fn (ScoringAssignment $assignment): bool => $assignment->scopeType() === ScoringAssignmentScope::EntryScorecard)
            ->groupBy(fn (ScoringAssignment $assignment): string => (string) ($assignment->entryScorecard?->contest_id ?? ''))
            ->reject(fn ($items, string $contestId): bool => $contestId === '')
            ->map(function ($items, string $contestId) use ($contestIndex): array {
                $contest = $contestIndex->get($contestId);
                $division = $contest?->division;
                $competition = $division?->competition;
                $label = collect([$competition?->name, $division?->name, $contest?->name])
                    ->filter(fn ($value): bool => filled($value))
                    ->implode(' / ');

                return [
                    'contest_id' => $contestId,
                    'division_id' => $division === null ? null : (string) $division->getKey(),
                    'competition' => $competition?->name,
                    'division' => $division?->name,
                    'contest' => $contest?->name,
                    'label' => $label !== '' ? $label : 'Judging panel',
                    'entry_count' => $contest?->entries->count() ?? $items->count(),
                    'scorecard_count' => $items->count(),
                    'locked' => (bool) $contest?->isJudgingPanelLocked(),
                ];
            })
            ->sortBy('label')
            ->values()
            ->all();
        $tabulatorTargets = $assignments
            ->filter(fn (ScoringAssignment $assignment): bool => $assignment->scopeType() !== ScoringAssignmentScope::EntryScorecard)
            ->map(fn (ScoringAssignment $assignment): array => [
                'assignment_id' => (string) $assignment->getKey(),
                'scope' => $assignment->scopeType() === ScoringAssignmentScope::CompetitionDivision ? 'division' : 'contest',
                'target_id' => (string) ($assignment->scopeType() === ScoringAssignmentScope::CompetitionDivision
                    ? $assignment->competition_division_id
                    : $assignment->contest_id),
                'competition' => $assignment->scopeType() === ScoringAssignmentScope::CompetitionDivision
                    ? $assignment->division?->competition?->name
                    : $assignment->contest?->division?->competition?->name,
                'division' => $assignment->scopeType() === ScoringAssignmentScope::CompetitionDivision
                    ? $assignment->division?->name
                    : $assignment->contest?->division?->name,
                'contest' => $assignment->scopeType() === ScoringAssignmentScope::Contest ? $assignment->contest?->name : null,
                'label' => $this->assignmentLabel($assignment),
            ])
            ->sortBy('label')
            ->values()
            ->all();
        return [
            'judging_panels' => $judgingPanels,
            'tabulator_targets' => $tabulatorTargets,
            'missing_roles' => [],
            'total' => count($judgingPanels) + count($tabulatorTargets),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function scoringReadiness(Event $event, ContestScheduleReadModel $schedules): array
    {
        $roleOptions = fn (EventRole $role) => User::query()
            ->whereHas('eventRoles', fn ($query) => $query->where('event_id', $event->getKey())->where('role', $role->value)->whereNull('revoked_at'))
            ->where('account_state', 'active')->orderBy('name')->get(['id', 'name']);
        $judges = $roleOptions(EventRole::Judge);
        $tabulators = $roleOptions(EventRole::Tabulator);

        return Division::query()
            ->whereHas('competition', fn ($query) => $query->where('event_id', $event->getKey()))
            ->with(['competition', 'governingRuleVersion', 'ruleVersions' => fn ($query) => $query->latest('version'), 'entries', 'contests.entries', 'contests.scorecards', 'contests.assignments'])
            ->get()
            ->filter(fn (Division $division): bool => ($division->governingRuleVersion ?? $division->ruleVersions->first())?->scoringFamily()?->value === 'criteria_based')
            ->map(function (Division $division) use ($judges, $tabulators, $schedules): array {
                $rule = $division->governingRuleVersion ?? $division->ruleVersions->first();
                $metadata = $rule->metadata();
                $contest = $division->contests->first();
                $judgeCount = $contest?->scorecards->pluck('judge_id')->filter()->unique()->count() ?? 0;
                $tabulatorCount = $contest?->assignments
                    ->where('scope_type', ScoringAssignmentScope::Contest)
                    ->whereNull('revoked_at')
                    ->whereIn('user_id', $tabulators->modelKeys())
                    ->count() ?? 0;
                $sourceBlocker = $metadata->sourceBlocker ?? ($rule->source_status === 'blocked' ? 'The scoring source is blocked.' : null);
                $sourceBlocked = $sourceBlocker !== null;
                $deduction = $rule->deduction_configuration ?? [];
                $deductionAuthorized = ($deduction['code'] ?? null) === null || ($deduction['calculation_status'] ?? null) === 'authorized';
                $aggregation = $contest?->isJudgingPanelLocked() ? (new JudgeScoreAggregationService)->aggregate($contest) : null;
                $aggregationBlocker = $aggregation['readiness']['blocker_codes'][0] ?? null;
                $expectedScores = $contest?->isJudgingPanelLocked() ? $contest->entries->count() * $judgeCount : 0;
                $submittedScores = $contest?->scorecards->whereIn('state', ['submitted', 'approved'])->count() ?? 0;
                $aggregationReady = $aggregation['readiness']['ready'] ?? false;
                $blockerLabels = [
                    'aggregation_confirmation_missing' => 'Confirm the Judge aggregation method and authority.',
                    'missing_scorecards' => 'Waiting for all locked-panel Judge scorecards.',
                    'tie_resolution_required' => 'Authorized tie resolution required.',
                    'adjustment_calculation_unauthorized' => 'Authorize the source deduction calculation policy.',
                    'adjustment_evidence_missing' => 'Waiting for objective adjustment evidence for every entry.',
                    'scorecard_rule_mismatch' => 'A scorecard uses a different rule version and requires correction.',
                ];
                $tie = collect($aggregation['ties'] ?? [])->first();
                $entryNames = collect($aggregation['entries'] ?? [])->keyBy('entry_id');
                $nextActionKey = match (true) {
                    $sourceBlocked => null,
                    $contest === null => 'prepare',
                    $judgeCount === 0 => 'panel',
                    ! $rule->hasConfirmedAggregation() => 'aggregation',
                    ! $deductionAuthorized => 'deduction',
                    $tabulatorCount === 0 => 'tabulator',
                    ! $contest->isJudgingPanelLocked() => 'lock',
                    $tie !== null => 'tie',
                    default => null,
                };
                $next = match ($nextActionKey) {
                    'prepare' => 'Prepare the official judged Contest.',
                    'panel' => 'Configure the judging panel.',
                    'aggregation' => 'Confirm the Judge aggregation method and authority.',
                    'deduction' => 'Authorize the source deduction calculation policy.',
                    'tabulator' => 'Assign an active Tabulator to this activity.',
                    'lock' => 'Lock the judging panel before scoring begins.',
                    'tie' => 'Authorized tie resolution required.',
                    default => $blockerLabels[$aggregationBlocker] ?? null,
                };

                return [
                    'id' => (string) $division->getKey(),
                    'name' => $division->competition->name,
                    'competition' => $division->competition->name,
                    'division' => $division->name,
                    'state' => $sourceBlocked ? 'blocked' : ($next === null ? 'ready' : 'needs_attention'),
                    'next_blocker' => $next,
                    'next_action_key' => $nextActionKey,
                    'tabulator_available' => $tabulators->isNotEmpty(),
                    'current_judge_ids' => $contest?->scorecards->pluck('judge_id')->filter()->unique()->map(fn ($id): string => (string) $id)->sort()->values()->all() ?? [],
                    'source' => [
                        'reliability' => $metadata->reliabilityLabel,
                        'blocker' => $sourceBlocker,
                        'pages' => $metadata->sourcePages,
                    ],
                    'counts' => ['entries' => $division->entries->count(), 'judges' => $judgeCount, 'tabulators' => $tabulatorCount],
                    'readiness_steps' => [
                        ['key' => 'contest', 'label' => 'Contest', 'state' => $contest === null ? 'pending' : 'prepared', 'detail' => $contest === null ? 'Not prepared' : 'Prepared'],
                        ['key' => 'panel', 'label' => 'Judge panel', 'state' => $judgeCount > 0 ? 'configured' : 'pending', 'detail' => $judgeCount > 0 ? $judgeCount.' Judges' : 'Not configured'],
                        ['key' => 'aggregation', 'label' => 'Aggregation', 'state' => $rule->hasConfirmedAggregation() ? 'confirmed' : 'pending', 'detail' => $rule->hasConfirmedAggregation() ? 'Confirmed' : 'Not confirmed'],
                        ['key' => 'deduction', 'label' => 'Deduction rules', 'state' => ($deduction['code'] ?? null) === null || $deductionAuthorized ? 'ready' : 'pending', 'detail' => ($deduction['code'] ?? null) === null ? 'Not required' : ($deductionAuthorized ? 'Authorized' : 'Needs authorization')],
                        ['key' => 'tabulator', 'label' => 'Tabulator', 'state' => $tabulatorCount > 0 ? 'assigned' : 'pending', 'detail' => $tabulatorCount > 0 ? 'Assigned' : 'Not assigned'],
                        ['key' => 'lock', 'label' => 'Panel', 'state' => $contest?->isJudgingPanelLocked() ? 'locked' : 'pending', 'detail' => $contest?->isJudgingPanelLocked() ? 'Locked' : 'Not locked'],
                        ['key' => 'scores', 'label' => 'Judge scores', 'state' => $aggregation === null ? 'pending' : ($aggregationReady ? 'ready' : 'waiting'), 'detail' => $aggregation === null ? 'Not started' : ($submittedScores.' / '.$expectedScores)],
                        ['key' => 'tabulation', 'label' => 'Tabulation', 'state' => $aggregationReady ? 'ready' : 'waiting', 'detail' => $aggregationReady ? 'Ready' : 'Waiting'],
                    ],
                    'schedule' => $contest === null
                        ? $schedules->forDivision($division)
                        : $schedules->forContest($contest),
                    'tie' => $tie === null ? null : [
                        'entry_ids' => $tie['entry_ids'],
                        'entries' => collect($tie['entry_ids'])->map(fn ($id): array => ['id' => $id, 'name' => $entryNames->get((string) $id)['entry'] ?? "Entry {$id}"])->all(),
                        'action' => route('admin.staff.scoring.tie.resolve', [$division->competition->event_id, $contest]),
                    ],
                    'actions' => [
                        'prepare' => $sourceBlocked || $contest !== null ? null : route('admin.staff.scoring.prepare', [$division->competition->event_id, $division]),
                        'panel' => $sourceBlocked || $contest === null || $contest->isJudgingPanelLocked() ? null : route('admin.staff.scoring.panel.store', [$division->competition->event_id, $contest]),
                        'aggregation' => $sourceBlocked || $contest === null || $rule->hasConfirmedAggregation() ? null : route('admin.staff.scoring.aggregation.confirm', [$division->competition->event_id, $contest]),
                        'deduction' => $sourceBlocked || $contest === null || $deductionAuthorized ? null : route('admin.staff.scoring.deduction.authorize', [$division->competition->event_id, $contest]),
                        'tabulator' => $sourceBlocked || $contest === null || $tabulatorCount > 0 || $tabulators->isEmpty() ? null : route('admin.staff.scoring.tabulator.store', [$division->competition->event_id, $contest, $tabulators->first()->getKey()]),
                        'lock' => $sourceBlocked || $contest === null || $contest->isJudgingPanelLocked() || ! $rule->hasConfirmedAggregation() || ! $deductionAuthorized ? null : route('admin.staff.scoring.panel.lock', [$division->competition->event_id, $contest]),
                        'judge_options' => $judges->map(fn (User $user): array => ['id' => (string) $user->getKey(), 'name' => $user->name])->all(),
                        'tabulator_options' => $contest === null ? [] : $tabulators->map(fn (User $user): array => [
                            'id' => (string) $user->getKey(), 'name' => $user->name,
                            'href' => route('admin.staff.scoring.tabulator.store', [$division->competition->event_id, $contest, $user]),
                        ])->all(),
                    ],
                ];
            })->sortBy('name')->values()->all();
    }

    private function target(ScoringAssignmentScope $scope, int $id): Model { return match ($scope) { ScoringAssignmentScope::CompetitionDivision => Division::findOrFail($id), ScoringAssignmentScope::Contest => Contest::findOrFail($id), ScoringAssignmentScope::EntryScorecard => EntryScorecard::findOrFail($id) }; }
    private function assignmentLabel(ScoringAssignment $a): string { return match ($a->scopeType()) { ScoringAssignmentScope::CompetitionDivision => ($a->division?->competition?->name ?? 'Sport').' / '.($a->division?->name ?? 'Division'), ScoringAssignmentScope::Contest => ($a->contest?->division?->competition?->name ?? 'Sport').' / '.($a->contest?->name ?? 'Contest'), ScoringAssignmentScope::EntryScorecard => ($a->entryScorecard?->contest?->division?->competition?->name ?? 'Sport').' / '.($a->entryScorecard?->entry?->name ?? 'Scorecard') }; }
    private function assertAdmin(Request $request, Event $event): void { if (! $request->user()->hasAdminAccess($event)) throw new AuthorizationException('Only the Global Admin can manage event staff.'); }
    private function assertWritable(Request $request, Event $event): void { $this->assertAdmin($request, $event); if ($event->isArchived()) throw new AuthorizationException('Archived events are read-only.'); }
    private function assertMember(Event $event, User $user): void { if (! $user->eventRoles()->where('event_id', $event->getKey())->exists()) abort(404); }
}
