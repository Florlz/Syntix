<?php

namespace App\Http\Controllers\Judge;

use App\Actions\Scoring\SaveJudgeScorecard;
use App\Actions\Scoring\SubmitJudgeScorecard;
use App\Http\Controllers\Controller;
use App\Models\EntryScorecard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScorecardController extends Controller
{
    public function show(EntryScorecard $scorecard): Response
    {
        Gate::authorize('view', $scorecard);
        $scorecard->load([
            'contest.division.competition',
            'entry.delegation',
            'ruleVersion.criteria',
            'values',
        ]);

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
            'values.*.raw_value' => ['required', 'numeric'],
            'values.*.deduction' => ['nullable', 'numeric', 'min:0'],
            'values.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $save->handle(
                $request->user(),
                $scorecard,
                $data['values'],
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
