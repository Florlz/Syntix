<?php

namespace App\Http\Controllers\Judge;

use App\Actions\Scoring\SaveJudgeScorecard;
use App\Actions\Scoring\SubmitJudgeScorecard;
use App\Http\Controllers\Controller;
use App\Models\EntryScorecard;
use App\Models\ScoringAdjustment;
use App\Services\ContestScheduleReadModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScorecardController extends Controller
{
    public function show(Request $request, EntryScorecard $scorecard, ContestScheduleReadModel $schedules): Response
    {
        Gate::authorize('view', $scorecard);
        $scorecard->load([
            'contest.division.competition',
            'entry.delegation',
            'ruleVersion.criteria',
            'values',
        ]);

        $rule = $scorecard->ruleVersion;
        $metadata = $rule?->metadata();
        $schedule = $scorecard->contest === null ? null : $schedules->findForContest($scorecard->contest);

        $assignedScorecards = EntryScorecard::query()
            ->where('contest_id', $scorecard->contest_id)
            ->where('judge_id', $request->user()->getKey())
            ->whereIn('id', $request->user()->scoringAssignments()
                ->active()
                ->where('scope_type', 'entry_scorecard')
                ->select('entry_scorecard_id'))
            ->orderBy('entry_id')
            ->get(['id']);
        $position = $assignedScorecards->search(fn (EntryScorecard $item): bool => $item->is($scorecard));
        $previous = $position !== false && $position > 0 ? $assignedScorecards[$position - 1] : null;
        $next = $position !== false && $position < $assignedScorecards->count() - 1 ? $assignedScorecards[$position + 1] : null;
        $adjustments = ScoringAdjustment::query()
            ->with('recorder')
            ->where('contest_id', $scorecard->contest_id)
            ->where('entry_id', $scorecard->entry_id)
            ->orderBy('recorded_at')
            ->get();

        return Inertia::render('Judge/Scorecard', [
            'scorecard' => [
                'id' => (string) $scorecard->getKey(),
                'state' => $scorecard->scorecardState()->value,
                'revision' => (int) $scorecard->revision,
                'calculated_total' => (string) ($scorecard->calculated_total ?? '0'),
                'rejection_reason' => $scorecard->rejection_reason,
                'entry' => $scorecard->entry?->name ?? $scorecard->entry_reference,
                'delegation' => $scorecard->entry?->delegation?->name,
                'contest' => $scorecard->contest?->name,
                'division' => $scorecard->contest?->division?->name,
                'competition' => $scorecard->contest?->division?->competition?->name,
                'source' => [
                    'reference' => $rule?->source_reference,
                    'pages' => $metadata?->sourcePages ?? [],
                    'reliability' => $metadata?->reliabilityLabel ?? 'unresolved',
                    'blocker' => $metadata?->sourceBlocker,
                ],
                'instructions' => $metadata?->eventControls ?? [],
                'schedule' => [
                    'venue' => $schedule?->venue?->name,
                    'starts_at' => $schedule?->starts_at?->toIso8601String(),
                    'ends_at' => $schedule?->ends_at?->toIso8601String(),
                    'fallback' => $schedule === null ? 'Venue not scheduled yet' : null,
                ],
                'official_adjustments' => $adjustments->map(fn ($adjustment) => [
                    'id' => (string) $adjustment->getKey(),
                    'label' => $adjustment->label,
                    'points' => (string) $adjustment->points,
                    'input' => (string) $adjustment->input_value.' '.$adjustment->input_unit,
                    'recorded_by' => $adjustment->recorder?->name,
                    'recorded_at' => $adjustment->recorded_at?->toIso8601String(),
                ])->values()->all(),
                'official_deduction_total' => (string) $adjustments->sum('points'),
                'navigation' => [
                    'previous_id' => $previous === null ? null : (string) $previous->getKey(),
                    'next_id' => $next === null ? null : (string) $next->getKey(),
                    'position' => $position === false ? null : $position + 1,
                    'total' => $assignedScorecards->count(),
                ],
                'criteria' => $scorecard->ruleVersion?->criteria
                    ->sortBy('display_order')
                    ->map(fn ($criterion) => [
                        'id' => (string) $criterion->getKey(),
                        'name' => $criterion->name,
                        'source_label' => $criterion->source_label,
                        'weight' => $criterion->weight === null ? null : (string) $criterion->weight,
                        'maximum' => $criterion->raw_maximum === null ? null : (string) $criterion->raw_maximum,
                        'minimum' => $criterion->raw_minimum === null ? null : (string) $criterion->raw_minimum,
                        'input_scale' => (int) ($criterion->input_scale ?? $scorecard->ruleVersion?->input_scale ?? 0),
                        'required' => (bool) $criterion->is_required,
                    ])->values()->all() ?? [],
                'values' => $scorecard->values->mapWithKeys(fn ($value) => [
                    (string) $value->scoring_criterion_id => [
                        'raw_value' => (string) $value->raw_value,
                        'deduction' => (string) $value->deduction,
                        'weighted_value' => (string) $value->weighted_value,
                        'notes' => $value->notes,
                    ],
                ])->all(),
            ],
        ]);
    }

    public function update(
        Request $request,
        EntryScorecard $scorecard,
        SaveJudgeScorecard $save,
    ): RedirectResponse {
        Gate::authorize('score', $scorecard);
        $data = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:0'],
            'values' => ['required', 'array'],
            'values.*.criterion_id' => ['required', 'integer'],
            'values.*.raw_value' => ['nullable', 'numeric'],
            'values.*.deduction' => ['nullable', 'numeric', 'min:0'],
            'values.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $draftValues = array_values(array_filter(
            $data['values'],
            fn (array $value): bool => array_key_exists('raw_value', $value) && $value['raw_value'] !== null,
        ));

        try {
            $save->handle(
                $request->user(),
                $scorecard,
                $draftValues,
                (int) $data['expected_revision'],
            );
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['scorecard' => $exception->getMessage()]);
        }

        return back()->with('status', 'Scorecard draft saved to the authoritative server.');
    }

    public function submit(
        Request $request,
        EntryScorecard $scorecard,
        SubmitJudgeScorecard $submit,
    ): RedirectResponse {
        Gate::authorize('score', $scorecard);

        try {
            $submit->handle($request->user(), $scorecard);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['scorecard' => $exception->getMessage()]);
        }

        return back()->with('status', 'Scorecard submitted for Admin review.');
    }
}
