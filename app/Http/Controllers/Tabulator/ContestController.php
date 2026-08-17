<?php

namespace App\Http\Controllers\Tabulator;

use App\Actions\Scoring\CompleteContest;
use App\Actions\Scoring\RecordLiveScore;
use App\Actions\Scoring\StartContest;
use App\Actions\Scoring\SubmitContestResult;
use App\Actions\Scoring\FinalizeJudgedContest;
use App\Actions\Scoring\RecordScoringAdjustment;
use App\Actions\Scoring\VoidScoringAdjustment;
use App\Enums\ScoringFamily;
use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\Entry;
use App\Models\ScoringAdjustment;
use App\ReadModels\JudgedTabulationReadModel;
use App\Services\ScoringCommandProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContestController extends Controller
{
    public function show(Contest $contest, JudgedTabulationReadModel $judged): Response
    {
        Gate::authorize('view', $contest);

        $contest->load(['division.competition', 'division.governingRuleVersion', 'entries.entry', 'scorecards']);
        $configuration = $contest->division?->governingRuleVersion?->scoring_configuration ?? [];

        if ($contest->ruleVersion?->scoringFamily() === ScoringFamily::CriteriaBased) {
            $matrix = $judged->forContest($contest);
            $deduction = $contest->ruleVersion->deduction_configuration ?? [];

            return Inertia::render('Tabulator/JudgedContest', [
                'contest' => [
                    'id' => (string) $contest->getKey(),
                    'name' => $contest->name,
                    'competition' => $contest->division?->competition?->name,
                    'division' => $contest->division?->name,
                    'state' => $contest->state->value,
                    'panel_locked' => $contest->isJudgingPanelLocked(),
                ],
                'tabulation' => $matrix,
                'adjustment_configuration' => [
                    'code' => $deduction['code'] ?? null,
                    'type' => $deduction['type'] ?? null,
                    'calculation_status' => $deduction['calculation_status'] ?? null,
                    'rounding_policy' => $deduction['rounding_policy'] ?? null,
                    'input_unit' => str_contains((string) ($deduction['type'] ?? ''), 'interval') ? 'seconds' : 'words',
                ],
            ]);
        }

        return Inertia::render('Tabulator/Contest', [
            'contest' => [
                'id' => (string) $contest->getKey(),
                'event_id' => (string) $contest->division?->competition?->event_id,
                'division_id' => (string) $contest->competition_division_id,
                'name' => $contest->name,
                'state' => $contest->state->value,
                'revision' => $contest->revision,
                'division' => $contest->division?->name,
                'competition' => $contest->division?->competition?->name,
                'live_payload' => $contest->live_payload,
                'result_payload' => $contest->result_payload,
                'outcome_profile' => $configuration['outcome_profile'] ?? 'team_total',
                'scoring_configuration' => $configuration,
                'entries' => $contest->entries->sortBy('slot')->values()->map(fn ($contestEntry): array => [
                    'id' => (string) $contestEntry->entry_id,
                    'name' => $contestEntry->entry?->name ?? 'Entry '.$contestEntry->entry_id,
                    'slot' => $contestEntry->slot,
                ]),
            ],
        ]);
    }

    public function recordAdjustment(Request $request, Contest $contest, Entry $entry, RecordScoringAdjustment $record): RedirectResponse
    {
        Gate::authorize('score', $contest);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'input_value' => ['required', 'integer', 'min:0'],
            'input_unit' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $record->handle($request->user(), $contest, $entry, $data['code'], (string) $data['input_value'], $data['input_unit'], $data['notes'] ?? null);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['adjustment' => $exception->getMessage()]);
        }

        return back()->with('status', 'Official adjustment recorded.');
    }

    public function voidAdjustment(Request $request, Contest $contest, ScoringAdjustment $adjustment, VoidScoringAdjustment $void): RedirectResponse
    {
        Gate::authorize('score', $contest);
        if ((int) $adjustment->contest_id !== (int) $contest->getKey()) abort(404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $void->handle($request->user(), $adjustment, $data['reason']);

        return back()->with('status', 'Adjustment voided; its history was preserved.');
    }

    public function finalize(Request $request, Contest $contest, FinalizeJudgedContest $finalize): RedirectResponse
    {
        Gate::authorize('score', $contest);
        try {
            $finalize->handle($request->user(), $contest);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['tabulation' => $exception->getMessage()]);
        }

        return back()->with('status', 'Judged result finalized and submitted for Global Admin review.');
    }

    public function command(
        Request $request,
        Contest $contest,
        ScoringCommandProcessor $processor,
    ): JsonResponse {
        Gate::authorize('score', $contest);
        $contest->loadMissing('ruleVersion');
        if ($contest->ruleVersion?->scoringFamily() === ScoringFamily::CriteriaBased) {
            return response()->json(['error_code' => 'criteria_based_contests_require_judged_tabulation'], 422);
        }
        $user = $request->user();
        $contest->load('division.competition.event');
        $command = $request->validate([
            'command_uuid' => ['required', 'uuid'],
            'schema_version' => ['required', 'integer', 'min:1'],
            'command_type' => ['required', 'in:start_contest,record_live_score,complete_contest,submit_contest_result'],
            'base_revision' => ['nullable', 'integer', 'min:0'],
            'depends_on_command_uuid' => ['nullable', 'uuid'],
            'payload' => ['array'],
        ]);
        $command['event_id'] = $contest->division->competition->event_id;
        $command['contest_id'] = $contest->getKey();
        $payload = $command['payload'] ?? [];

        try {
            $receipt = $processor->execute($user, $contest->division->competition->event, $command, function () use ($user, $contest, $command, $payload): array {
                $result = match ($command['command_type']) {
                    'start_contest' => (new StartContest)->handle($user, $contest),
                    'record_live_score' => (new RecordLiveScore)->handle(
                        $user,
                        $contest,
                        $payload,
                        (int) ($command['base_revision'] ?? 0),
                    ),
                    'complete_contest' => (new CompleteContest)->handle(
                        $user,
                        $contest,
                        $payload,
                        (int) ($command['base_revision'] ?? 0),
                    ),
                    'submit_contest_result' => (new SubmitContestResult)->handle($user, $contest),
                };

                return [
                    'response' => [
                        'id' => (string) $result->getKey(),
                        'type' => $result::class,
                        'revision' => $result->revision ?? null,
                    ],
                    'resulting_revision' => isset($result->revision) ? (int) $result->revision : null,
                ];
            });
        } catch (\DomainException $exception) {
            $status = in_array($exception->getMessage(), [
                'idempotency_key_reused',
                'command_dependency_not_applied',
            ], true) || str_contains($exception->getMessage(), 'stale') ? 409 : 422;

            return response()->json(['error_code' => $exception->getMessage()], $status);
        }

        return response()->json([
            'command_uuid' => $receipt->command_uuid,
            'disposition' => $receipt->disposition,
            'response' => $receipt->response,
            'resulting_revision' => $receipt->resulting_revision,
            'error_code' => $receipt->error_code,
        ]);
    }
}
