<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Assignments\GrantScoringAssignment;
use App\Actions\Brackets\GenerateRandomTournament;
use App\Actions\Brackets\PublishBracket;
use App\Actions\Events\ApplySiklab2025Programme;
use App\Actions\Scoring\ActivateRuleVersion;
use App\Enums\AuditAction;
use App\Enums\CompetitionFormat;
use App\Enums\ParticipantMode;
use App\Enums\ScoringAssignmentScope;
use App\Enums\ScoringFamily;
use App\Http\Controllers\Controller;
use App\Models\BracketVersion;
use App\Models\CompetitionRuleVersion;
use App\Models\Contest;
use App\Models\Division;
use App\Models\EntryScorecard;
use App\Models\Event;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfigurationController extends Controller
{
    public function applySiklabProgramme(
        Request $request,
        Event $event,
        ApplySiklab2025Programme $apply,
    ): RedirectResponse {
        $apply->handle($request->user(), $event);

        return back()->with('status', 'The SIKLAB proposal programme is ready for review.');
    }

    public function generateRandomTournament(
        Request $request,
        Division $division,
        GenerateRandomTournament $generate,
    ): RedirectResponse {
        $data = $request->validate([
            'command_uuid' => ['nullable', 'uuid'],
            'redraw' => ['sometimes', 'boolean'],
        ]);
        $generate->handle(
            $request->user(),
            $division,
            $data['command_uuid'] ?? null,
            (bool) ($data['redraw'] ?? false),
        );

        return back()->with('status', ($data['redraw'] ?? false)
            ? 'The unpublished draw was replaced with a new recorded random draw.'
            : 'A recorded random draw is ready for review.');
    }

    public function publishBracket(
        Request $request,
        BracketVersion $bracket,
        PublishBracket $publish,
    ): RedirectResponse {
        $publish->handle($request->user(), $bracket);

        return back()->with('status', 'The bracket is published and its playable contests are assigned.');
    }

    public function storeCompetition(Request $request, Event $event, AuditLogger $audit): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'division_name' => ['required', 'string', 'max:255'],
            'division_slug' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $event, $data, $audit): void {
            $competition = $event->competitions()->create([
                'name' => $data['name'],
                'slug' => Str::slug($data['slug'] ?? $data['name']),
            ]);
            $division = $competition->divisions()->create([
                'name' => $data['division_name'],
                'slug' => Str::slug($data['division_slug'] ?? $data['division_name']),
            ]);
            $audit->record($request->user(), AuditAction::CompetitionCreated, $competition, $event, after: ['name' => $competition->name]);
            $audit->record($request->user(), AuditAction::DivisionCreated, $division, $event, after: ['name' => $division->name]);
        });

        return back()->with('status', 'Competition family and score-bearing Division created.');
    }

    public function storeRuleVersion(Request $request, Division $division, AuditLogger $audit): RedirectResponse
    {
        $division->loadMissing('competition.event');
        $event = $division->competition?->event;
        $this->assertAdmin($request, $event);
        $data = $request->validate([
            'scoring_family' => ['required', 'in:objective,criteria_based,aggregate,custom_series'],
            'format' => ['required', 'in:single_elimination,double_elimination,round_robin,series,placement,criteria_based,aggregate,custom'],
            'participant_mode' => ['required', 'in:team,individual,pair,relay,mixed'],
            'placement_point_template_id' => ['nullable', 'integer', 'exists:placement_point_templates,id'],
            'configuration' => ['array'],
        ]);

        $version = $division->ruleVersions()->create([
            'placement_point_template_id' => $data['placement_point_template_id'] ?? null,
            'version' => ((int) $division->ruleVersions()->max('version')) + 1,
            'scoring_family' => ScoringFamily::from($data['scoring_family']),
            'format' => CompetitionFormat::from($data['format']),
            'participant_mode' => ParticipantMode::from($data['participant_mode']),
            'scoring_configuration' => $data['configuration'] ?? [],
            'created_by' => $request->user()->getKey(),
        ]);
        $audit->record($request->user(), AuditAction::RuleVersionCreated, $version, $event, after: ['version' => $version->version]);

        return back()->with('status', 'Rule version created as a draft.');
    }

    public function activateRuleVersion(Request $request, CompetitionRuleVersion $version, ActivateRuleVersion $activate): RedirectResponse
    {
        $activate->handle($request->user(), $version);

        return back()->with('status', 'Rule version activated or blocked with explicit readiness errors.');
    }

    public function grantAssignment(Request $request, Event $event, GrantScoringAssignment $grant): RedirectResponse
    {
        $this->assertAdmin($request, $event);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'scope_type' => ['required', 'in:competition_division,contest,entry_scorecard'],
            'target_id' => ['required', 'integer'],
        ]);
        $assignee = User::query()->findOrFail($data['user_id']);
        $scope = ScoringAssignmentScope::from($data['scope_type']);
        $target = match ($scope) {
            ScoringAssignmentScope::CompetitionDivision => Division::query()->findOrFail($data['target_id']),
            ScoringAssignmentScope::Contest => Contest::query()->findOrFail($data['target_id']),
            ScoringAssignmentScope::EntryScorecard => EntryScorecard::query()->findOrFail($data['target_id']),
        };
        $grant->handle($request->user(), $event, $assignee, $scope, $target);

        return back()->with('status', 'Exact scoring assignment granted.');
    }

    private function assertAdmin(Request $request, ?Event $event): void
    {
        if ($event === null || ! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('The active Global Admin is required.');
        }
    }
}
