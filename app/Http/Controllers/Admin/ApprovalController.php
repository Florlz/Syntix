<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Scoring\ApproveContestOutcome;
use App\Actions\Scoring\ApproveDivisionPlacement;
use App\Actions\Scoring\RejectContestResult;
use App\Actions\Scoring\SubmitDivisionPlacement;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\Event;
use App\Models\ResultSubmission;
use App\Services\SportWorkspaceReadModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request, Event $event, SportWorkspaceReadModel $workspaceReadModel): Response
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the active Global Admin can review approvals.');
        }

        $filters = $request->validate([
            'competition' => ['nullable', 'integer'],
            'division' => ['nullable', 'integer'],
        ]);
        $competition = ($filters['competition'] ?? null) === null
            ? null
            : $event->competitions()->whereKey((int) $filters['competition'])->first();
        $division = ($filters['division'] ?? null) === null
            ? null
            : Division::query()
                ->whereKey((int) $filters['division'])
                ->whereHas('competition', fn ($query) => $query->where('event_id', $event->getKey()))
                ->with('competition')
                ->first();

        if (($filters['competition'] ?? null) !== null && $competition === null) {
            abort(404);
        }
        if (($filters['division'] ?? null) !== null && ($division === null || ($competition !== null && (int) $division->competition_id !== (int) $competition->getKey()))) {
            abort(404);
        }

        $workspaceCompetition = $competition ?? $division?->competition;
        $workspace = $workspaceCompetition === null ? null : $workspaceReadModel->forSport($workspaceCompetition);

        $submissions = ResultSubmission::query()
            ->where('state', 'submitted')
            ->whereHas('contest.division.competition', fn ($query) => $query->where('event_id', $event->getKey()))
            ->when($competition !== null, fn ($query) => $query->whereHas('contest.division', fn ($query) => $query->where('competition_id', $competition->getKey())))
            ->when($division !== null, fn ($query) => $query->whereHas('contest', fn ($query) => $query->where('competition_division_id', $division->getKey())))
            ->with('contest.division.competition', 'contest.entries.entry.delegation', 'submitter')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ResultSubmission $submission) => $this->resultSubmissionData($submission))->values()->all();

        $placements = DivisionPlacement::query()
            ->where('state', 'submitted')
            ->whereHas('division.competition', fn ($query) => $query->where('event_id', $event->getKey()))
            ->when($competition !== null, fn ($query) => $query->whereHas('division', fn ($query) => $query->where('competition_id', $competition->getKey())))
            ->when($division !== null, fn ($query) => $query->where('competition_division_id', $division->getKey()))
            ->with('division.competition', 'submitter', 'items.entry', 'items.delegation')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (DivisionPlacement $placement) => [
                'id' => (string) $placement->getKey(),
                'competition' => $placement->division?->competition?->name,
                'division' => $placement->division?->name,
                'revision' => (int) $placement->revision,
                'submitted_by' => $placement->submitter?->name,
                'submitted_at' => $placement->submitted_at?->toIso8601String(),
                'items' => $placement->items->sortBy('rank')->map(fn ($item) => [
                    'id' => (string) $item->getKey(),
                    'rank' => (int) $item->rank,
                    'entry' => $item->entry?->name,
                    'delegation' => $item->delegation?->name,
                    'placement_key' => $item->placement_key,
                    'points' => (string) $item->championship_points,
                ])->values()->all(),
            ])->values()->all();

        return Inertia::render('Admin/Approvals/Index', [
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name, 'archived' => $event->isArchived()],
            'result_submissions' => $submissions,
            'division_placements' => $placements,
            'filters' => [
                'competition' => $competition === null ? null : (string) $competition->getKey(),
                'division' => $division === null ? null : (string) $division->getKey(),
            ],
            'scope' => [
                'competition' => $competition?->name ?? $division?->competition?->name,
                'division' => $division?->name,
                'competition_id' => $competition === null ? ($division?->competition?->getKey() === null ? null : (string) $division->competition->getKey()) : (string) $competition->getKey(),
                'division_id' => $division === null ? null : (string) $division->getKey(),
            ],
            'workspace' => $workspace,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resultSubmissionData(ResultSubmission $submission): array
    {
        $payload = $submission->payload ?? [];
        $slots = $submission->contest?->entries?->sortBy('slot')->values() ?? collect();
        $home = $slots->firstWhere('slot', 1);
        $away = $slots->firstWhere('slot', 2);
        $entryName = fn ($slot, string $fallback): string => $slot?->entry?->name ?? $fallback;
        $winnerId = isset($payload['winner_entry_id']) ? (int) $payload['winner_entry_id'] : null;

        return [
            'id' => (string) $submission->getKey(),
            'competition' => $submission->contest?->division?->competition?->name,
            'division' => $submission->contest?->division?->name,
            'contest' => $submission->contest?->name,
            'revision' => (int) $submission->contest_revision,
            'submitted_by' => $submission->submitter?->name,
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'outcome_type' => $payload['outcome_type'] ?? null,
            'result' => $payload['result'] ?? null,
            'home' => [
                'id' => $home?->entry_id === null ? null : (string) $home->entry_id,
                'name' => $entryName($home, 'Home side'),
                'score' => $payload['home'] ?? null,
            ],
            'away' => [
                'id' => $away?->entry_id === null ? null : (string) $away->entry_id,
                'name' => $entryName($away, 'Away side'),
                'score' => $payload['away'] ?? null,
            ],
            'winner' => $winnerId === null
                ? null
                : ($slots->firstWhere('entry_id', $winnerId)?->entry?->name ?? 'Winner recorded'),
            'technical_payload' => $payload,
        ];
    }

    public function approveResult(
        Request $request,
        ResultSubmission $submission,
        ApproveContestOutcome $approve,
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        try {
            $approve->handle($request->user(), $submission, $data['reason'] ?? null);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return back()->with('status', 'Contest outcome approved without championship points.');
    }

    public function rejectResult(
        Request $request,
        ResultSubmission $submission,
        RejectContestResult $reject,
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        try {
            $reject->handle($request->user(), $submission, $data['reason']);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return back()->with('status', 'Contest result rejected for correction.');
    }

    public function submitPlacement(
        Request $request,
        Division $division,
        SubmitDivisionPlacement $submit,
    ): RedirectResponse {
        $data = $request->validate([
            'evidence' => ['array'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.entry_id' => ['required', 'integer'],
            'items.*.rank' => ['required', 'integer', 'min:1'],
            'items.*.placement_key' => ['required', 'string', 'max:100'],
            'items.*.participation_eligible' => ['sometimes', 'boolean'],
        ]);
        $submit->handle($request->user(), $division, $data['items'], $data['evidence'] ?? []);

        return back()->with('status', 'Division Placement submitted for separate approval.');
    }

    public function approvePlacement(
        Request $request,
        DivisionPlacement $placement,
        ApproveDivisionPlacement $approve,
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);
        try {
            $approve->handle($request->user(), $placement, $data['reason'] ?? null);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['approval' => $exception->getMessage()]);
        }

        return back()->with('status', 'Final Division Placement approved and ledger entries committed.');
    }
}
