<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Identity\ProvisionEventScorer;
use App\Enums\EventRole;
use App\Enums\ScoringAssignmentScope;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function create(Request $request, Event $event): Response
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the active Global Admin can provision a scorer.');
        }

        return Inertia::render('Admin/Accounts/Create', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name],
            'targets' => [
                'competition_division' => Division::query()
                    ->whereHas('competition', fn ($query) => $query->whereBelongsTo($event))
                    ->with('competition:id,name')
                    ->orderBy('competition_id')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Division $division): array => [
                        'id' => (string) $division->getKey(),
                        'label' => $division->competition->name.' — '.$division->name,
                    ]),
                'contest' => Contest::query()
                    ->whereHas('division.competition', fn ($query) => $query->whereBelongsTo($event))
                    ->with('division.competition:id,name')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Contest $contest): array => [
                        'id' => (string) $contest->getKey(),
                        'label' => $contest->division->competition->name.' — '.$contest->name,
                    ]),
                'entry_scorecard' => EntryScorecard::query()
                    ->whereHas('contest.division.competition', fn ($query) => $query->whereBelongsTo($event))
                    ->with(['contest.division.competition:id,name', 'entry:id,name'])
                    ->orderBy('id')
                    ->get()
                    ->map(fn (EntryScorecard $scorecard): array => [
                        'id' => (string) $scorecard->getKey(),
                        'label' => $scorecard->contest->division->competition->name.' — '
                            .($scorecard->entry?->name ?? $scorecard->entry_reference ?? 'Scorecard #'.$scorecard->getKey()),
                    ]),
            ],
        ]);
    }

    public function store(Request $request, Event $event, ProvisionEventScorer $provision): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'lowercase', 'max:255'],
            'role' => ['required', Rule::in([EventRole::Judge->value, EventRole::Tabulator->value])],
            'scope_type' => ['required', Rule::enum(ScoringAssignmentScope::class)],
            'target_id' => ['required', 'integer'],
        ]);
        $role = EventRole::from($data['role']);
        $scope = ScoringAssignmentScope::from($data['scope_type']);
        $target = $this->target($scope, (int) $data['target_id']);
        $result = $provision->handle($request->user(), $event, $data, $role, $scope, $target);

        // The raw token is returned only to the initiating Admin response. It
        // is never persisted, logged, or included in the shared auth DTO.
        return back()->with('setup_url', route('account.setup', ['token' => $result['token']]));
    }

    private function target(ScoringAssignmentScope $scope, int $id): Model
    {
        return match ($scope) {
            ScoringAssignmentScope::CompetitionDivision => Division::query()->findOrFail($id),
            ScoringAssignmentScope::Contest => Contest::query()->findOrFail($id),
            ScoringAssignmentScope::EntryScorecard => EntryScorecard::query()->findOrFail($id),
        };
    }
}
