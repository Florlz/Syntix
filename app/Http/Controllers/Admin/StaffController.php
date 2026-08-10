<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Assignments\RevokeScoringAssignment;
use App\Actions\Events\GrantEventRole;
use App\Actions\Events\RevokeEventRole;
use App\Actions\Identity\DisableUser;
use App\Actions\Identity\EnableUser;
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
    public function index(Request $request, Event $event): Response
    {
        $this->assertAdmin($request, $event);
        $roles = $event->userRoles()->whereIn('role', [EventRole::Judge->value, EventRole::Tabulator->value])
            ->with(['user', 'user.eventRoles.event', 'user.scoringAssignments' => fn ($q) => $q->where('event_id', $event->getKey())->with(['division.competition', 'contest.division.competition', 'entryScorecard.contest.division.competition']), 'user.userInvitations' => fn ($q) => $q->where('event_id', $event->getKey())->latest('id')])
            ->get()->groupBy('user_id');

        $staff = $roles->map(function ($memberships) use ($event): array {
            $user = $memberships->first()->user;
            $activeRoles = $memberships->whereNull('revoked_at');
            $assignments = $user->scoringAssignments->whereNull('revoked_at');
            $invitation = $user->userInvitations->first();
            return [
                'id' => (string) $user->getKey(), 'name' => $user->name, 'email' => $user->email,
                'account_state' => $user->accountState()->value, 'disabled_reason' => $user->disable_reason,
                'roles' => $activeRoles->map(fn ($r) => ['id' => (string) $r->getKey(), 'role' => $r->role->value])->values(),
                'assignments' => $assignments->map(fn (ScoringAssignment $a) => ['id' => (string) $a->getKey(), 'scope' => $a->scopeType()->value, 'label' => $this->assignmentLabel($a)])->values(),
                'invitation' => $invitation ? ['state' => $invitation->invitationState()->value, 'expires_at' => $invitation->expires_at?->toIso8601String()] : null,
                'event_memberships' => $user->eventRoles->whereNull('revoked_at')->groupBy('event_id')->map(fn ($items) => ['event' => $items->first()->event?->name, 'roles' => $items->pluck('role')->map(fn ($r) => $r->value)->values()])->values(),
                'audit' => AuditLog::query()->where('event_id', $event->getKey())->where('target_id', (string) $user->getKey())->latest()->limit(10)->get()->map(fn ($log) => ['action' => $log->action, 'reason' => $log->reason, 'at' => $log->created_at?->toIso8601String()]),
            ];
        })->sortBy('name')->values();

        return Inertia::render('Admin/Staff/Index', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name, 'archived' => $event->isArchived()],
            'staff' => $staff,
            'targets' => $this->targets($event),
        ]);
    }

    public function reissue(Request $request, Event $event, User $user, AuditLogger $audit): RedirectResponse
    {
        $this->assertWritable($request, $event); $this->assertMember($event, $user);
        $token = DB::transaction(function () use ($request, $event, $user, $audit): string {
            UserInvitation::query()->where('user_id', $user->getKey())->where('event_id', $event->getKey())->whereNull('consumed_at')->update(['expires_at' => now()]);
            $token = Str::random(64);
            $invitation = UserInvitation::create(['user_id' => $user->getKey(), 'event_id' => $event->getKey(), 'token_hash' => hash('sha256', $token), 'invited_by' => $request->user()->getKey(), 'expires_at' => now()->addHours(24)]);
            $audit->record($request->user(), AuditAction::InvitationReissued, $user, $event, after: ['expires_at' => $invitation->expires_at->toIso8601String()]);
            return $token;
        });
        return back()->with('setup_url', route('account.setup', ['token' => $token]))->with('status', 'A new one-time setup link was issued; every earlier unused link is invalid.');
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
            'entry_scorecard' => EntryScorecard::query()->whereHas('contest.division.competition', fn ($q) => $q->where('event_id', $event->getKey())->where('is_active', true))->with(['contest.division.competition', 'entry'])->get()->map(fn ($s) => ['id' => (string) $s->getKey(), 'label' => $s->contest->division->competition->name.' / '.($s->entry?->name ?? 'Scorecard '.$s->getKey())]),
        ];
    }

    private function target(ScoringAssignmentScope $scope, int $id): Model { return match ($scope) { ScoringAssignmentScope::CompetitionDivision => Division::findOrFail($id), ScoringAssignmentScope::Contest => Contest::findOrFail($id), ScoringAssignmentScope::EntryScorecard => EntryScorecard::findOrFail($id) }; }
    private function assignmentLabel(ScoringAssignment $a): string { return match ($a->scopeType()) { ScoringAssignmentScope::CompetitionDivision => ($a->division?->competition?->name ?? 'Sport').' / '.($a->division?->name ?? 'Division'), ScoringAssignmentScope::Contest => ($a->contest?->division?->competition?->name ?? 'Sport').' / '.($a->contest?->name ?? 'Contest'), ScoringAssignmentScope::EntryScorecard => ($a->entryScorecard?->contest?->division?->competition?->name ?? 'Sport').' / '.($a->entryScorecard?->entry?->name ?? 'Scorecard') }; }
    private function assertAdmin(Request $request, Event $event): void { if (! $request->user()->hasAdminAccess($event)) throw new AuthorizationException('Only the Global Admin can manage event staff.'); }
    private function assertWritable(Request $request, Event $event): void { $this->assertAdmin($request, $event); if ($event->isArchived()) throw new AuthorizationException('Archived events are read-only.'); }
    private function assertMember(Event $event, User $user): void { if (! $user->eventRoles()->where('event_id', $event->getKey())->exists()) abort(404); }
}
