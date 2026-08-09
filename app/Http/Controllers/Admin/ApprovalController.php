<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Scoring\ApproveContestOutcome;
use App\Actions\Scoring\ApproveDivisionPlacement;
use App\Actions\Scoring\RejectContestResult;
use App\Actions\Scoring\SubmitDivisionPlacement;
use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\DivisionPlacement;
use App\Models\Event;
use App\Models\ResultSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request, Event $event): Response
    {
        if (! $request->user()->hasActiveEventRole($event, EventRole::Admin)) {
            throw new AuthorizationException('Only an event Admin can review approvals.');
        }

        $submissions = ResultSubmission::query()
            ->where('state', 'submitted')
            ->whereHas('contest.division.competition', fn ($query) => $query->where('event_id', $event->getKey()))
            ->with('contest.division.competition', 'submitter')
            ->orderBy('submitted_at')
            ->get()
            ->map(fn (ResultSubmission $submission) => [
                'id' => (string) $submission->getKey(),
                'competition' => $submission->contest?->division?->competition?->name,
                'division' => $submission->contest?->division?->name,
                'contest' => $submission->contest?->name,
                'revision' => (int) $submission->contest_revision,
                'submitted_by' => $submission->submitter?->name,
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
                'payload' => $submission->payload ?? [],
            ])->values()->all();

        $placements = DivisionPlacement::query()
            ->where('state', 'submitted')
            ->whereHas('division.competition', fn ($query) => $query->where('event_id', $event->getKey()))
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
            'event' => ['id' => (string) $event->getKey(), 'name' => $event->name],
            'result_submissions' => $submissions,
            'division_placements' => $placements,
        ]);
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
