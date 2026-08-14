<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DisciplineEntryController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\PublicProgrammeController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SportController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Judge\ScorecardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicArea\BracketController;
use App\Http\Controllers\PublicArea\LandingController;
use App\Http\Controllers\PublicArea\ScoreboardController;
use App\Http\Controllers\Tabulator\ContestController;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing');

Route::get('/events/{event:slug}/scoreboard', ScoreboardController::class)
    ->name('public.scoreboard');
Route::get('/events/{event:slug}/divisions/{division}/bracket', BracketController::class)
    ->name('public.bracket');
Route::get('/events/{event:slug}/divisions/{division}/disciplines/{discipline}/bracket', [BracketController::class, 'discipline'])
    ->name('public.discipline-bracket');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/admin/events/create', [EventController::class, 'create'])->name('admin.events.create');
    Route::post('/admin/events', [EventController::class, 'store'])->name('admin.events.store');
    Route::get('/admin/events/{event}/accounts/create', [AccountController::class, 'create'])->name('admin.accounts.create');
    Route::post('/admin/events/{event}/accounts', [AccountController::class, 'store'])->name('admin.accounts.store');
    Route::get('/admin/events/{event}/approvals', [ApprovalController::class, 'index'])->name('admin.approvals.index');
    Route::get('/admin/events/{event}/sports', [SportController::class, 'index'])->name('admin.sports.index');
    Route::get('/admin/events/{event}/sports/{sport}', [SportController::class, 'show'])->name('admin.sports.show');
    Route::get('/admin/events/{event}/sports-schedules', [PublicProgrammeController::class, 'index'])->name('admin.sports.schedules');
    Route::get('/admin/events/{event}/divisions/{division}/tournament', [TournamentController::class, 'show'])->name('admin.sports.tournament');
    Route::get('/admin/events/{event}/divisions/{division}/disciplines/{discipline}/tournament', [TournamentController::class, 'showDiscipline'])->name('admin.sports.discipline-tournament');
    Route::post('/admin/events/{event}/sports', [SportController::class, 'store'])->name('admin.sports.store');
    Route::patch('/admin/events/{event}/sports/{sport}', [SportController::class, 'update'])->name('admin.sports.update');
    Route::patch('/admin/events/{event}/sports/{sport}/state', [SportController::class, 'state'])->name('admin.sports.state');
    Route::post('/admin/events/{event}/sports/{sport}/divisions', [SportController::class, 'storeDivision'])->name('admin.sports.divisions.store');
    Route::patch('/admin/events/{event}/divisions/{division}', [SportController::class, 'updateDivision'])->name('admin.sports.divisions.update');
    Route::patch('/admin/events/{event}/divisions/{division}/state', [SportController::class, 'divisionState'])->name('admin.sports.divisions.state');
    Route::get('/admin/events/{event}/staff', [StaffController::class, 'index'])->name('admin.staff.index');
    Route::post('/admin/events/{event}/staff/{user}/invitations', [StaffController::class, 'reissue'])->name('admin.staff.invitations.reissue');
    Route::post('/admin/events/{event}/staff/{user}/roles', [StaffController::class, 'grantRole'])->name('admin.staff.roles.store');
    Route::patch('/admin/events/{event}/staff/roles/{membership}/revoke', [StaffController::class, 'revokeRole'])->name('admin.staff.roles.revoke');
    Route::post('/admin/events/{event}/staff/{user}/assignments', [StaffController::class, 'grantAssignment'])->name('admin.staff.assignments.store');
    Route::patch('/admin/events/{event}/staff/assignments/{assignment}/revoke', [StaffController::class, 'revokeAssignment'])->name('admin.staff.assignments.revoke');
    Route::patch('/admin/events/{event}/staff/{user}/disable', [StaffController::class, 'disable'])->name('admin.staff.disable');
    Route::patch('/admin/events/{event}/staff/{user}/enable', [StaffController::class, 'enable'])->name('admin.staff.enable');
    Route::post('/admin/results/{submission}/approve', [ApprovalController::class, 'approveResult'])->name('admin.results.approve');
    Route::post('/admin/results/{submission}/reject', [ApprovalController::class, 'rejectResult'])->name('admin.results.reject');
    Route::post('/admin/divisions/{division}/placements', [ApprovalController::class, 'submitPlacement'])->name('admin.placements.submit');
    Route::post('/admin/placements/{placement}/approve', [ApprovalController::class, 'approvePlacement'])->name('admin.placements.approve');
    Route::get('/admin/events/{event}/reports/championship.csv', [ReportController::class, 'championship'])->name('admin.reports.championship');
    Route::post('/admin/events/{event}/programme/siklab-2025', [ConfigurationController::class, 'applySiklabProgramme'])->name('admin.programme.apply');
    Route::post('/admin/divisions/{division}/draws/random', [ConfigurationController::class, 'generateRandomTournament'])->name('admin.draws.random');
    Route::post('/admin/disciplines/{discipline}/draws/random', [ConfigurationController::class, 'generateRandomDisciplineTournament'])->name('admin.discipline-draws.random');
    Route::post('/admin/brackets/{bracket}/publish', [ConfigurationController::class, 'publishBracket'])->name('admin.brackets.publish');
    Route::post('/admin/events/{event}/competitions', [ConfigurationController::class, 'storeCompetition'])->name('admin.competitions.store');
    Route::post('/admin/divisions/{division}/rule-versions', [ConfigurationController::class, 'storeRuleVersion'])->name('admin.rule-versions.store');
    Route::post('/admin/rule-versions/{version}/activate', [ConfigurationController::class, 'activateRuleVersion'])->name('admin.rule-versions.activate');
    Route::post('/admin/events/{event}/assignments', [ConfigurationController::class, 'grantAssignment'])->name('admin.assignments.store');
    Route::get('/admin/events/{event}/public-programme', [PublicProgrammeController::class, 'index'])->name('admin.public-programme.index');
    Route::get('/admin/events/{event}/departments', [RegistrationController::class, 'departments'])->name('admin.departments.index');
    Route::get('/admin/events/{event}/departments/{department}/rosters', [RegistrationController::class, 'department'])->name('admin.departments.show');
    Route::get('/admin/events/{event}/registrations', [RegistrationController::class, 'index'])->name('admin.registrations.index');
    Route::get('/admin/events/{event}/registrations/directory/preview', [RegistrationController::class, 'directoryPreview'])->name('admin.registrations.directory-preview');
    Route::post('/admin/events/{event}/participants', [RegistrationController::class, 'storeParticipant'])->name('admin.participants.store');
    Route::patch('/admin/events/{event}/participants/{participant}', [RegistrationController::class, 'updateParticipant'])->name('admin.participants.update');
    Route::post('/admin/events/{event}/coaches/{participant}/assignments', [RegistrationController::class, 'saveCoachAssignment'])->name('admin.coach-assignments.store');
    Route::post('/admin/events/{event}/divisions/{division}/departments/{department}/coach-support', [RegistrationController::class, 'storeRosterCoachSupport'])->name('admin.roster-coach-support.store');
    Route::patch('/admin/events/{event}/coach-assignments/{assignment}/deactivate', [RegistrationController::class, 'deactivateCoachAssignment'])->name('admin.coach-assignments.deactivate');
    Route::post('/admin/events/{event}/entries/{entry}/players/{participant}/exceptions', [RegistrationController::class, 'recordParticipationException'])->name('admin.participation-exceptions.store');
    Route::post('/admin/events/{event}/participant-import/inspect', [RegistrationController::class, 'inspectParticipantImport'])->name('admin.participant-import.inspect');
    Route::post('/admin/events/{event}/participant-import/preview', [RegistrationController::class, 'inspectParticipantImport'])->name('admin.participant-import.preview');
    Route::post('/admin/events/{event}/participant-import/confirm', [RegistrationController::class, 'confirmParticipantImport'])->name('admin.participant-import.confirm');
    Route::post('/admin/events/{event}/divisions/{division}/departments/{department}/roster', [RegistrationController::class, 'storeDepartmentRoster'])->name('admin.department-rosters.store');
    Route::post('/admin/events/{event}/entries', [RegistrationController::class, 'storeEntry'])->name('admin.entries.store');
    Route::patch('/admin/events/{event}/entries/{entry}', [RegistrationController::class, 'updateEntry'])->name('admin.entries.update');
    Route::put('/admin/events/{event}/entries/{entry}/members/{participant}', [RegistrationController::class, 'saveMembership'])->name('admin.entry-members.update');
    Route::put('/admin/events/{event}/entries/{entry}/members', [RegistrationController::class, 'saveMembershipBatch'])->name('admin.entry-members.batch');
    Route::put('/admin/events/{event}/entries/{entry}/players/{participant}', [RegistrationController::class, 'updateRosterPlayer'])->name('admin.roster-players.update');
    Route::put('/admin/events/{event}/entries/{entry}/eligibility/{participant}', [RegistrationController::class, 'setEligibility'])->name('admin.eligibility.update');
    Route::put('/admin/events/{event}/entries/{entry}/eligibility', [RegistrationController::class, 'setEligibilityBatch'])->name('admin.eligibility.batch');
    Route::patch('/admin/events/{event}/entries/{entry}/status', [RegistrationController::class, 'transitionEntry'])->name('admin.entries.status');
    Route::patch('/admin/events/{event}/disciplines/{discipline}/entries/{entry}', [DisciplineEntryController::class, 'update'])->name('admin.discipline-entries.update');
    Route::patch('/admin/events/{event}/disciplines/{discipline}/entries/{entry}/state', [DisciplineEntryController::class, 'state'])->name('admin.discipline-entries.state');
    Route::post('/admin/events/{event}/venues', [PublicProgrammeController::class, 'storeVenue'])->name('admin.venues.store');
    Route::patch('/admin/events/{event}/venues/{venue}', [PublicProgrammeController::class, 'updateVenue'])->name('admin.venues.update');
    Route::post('/admin/events/{event}/schedules', [PublicProgrammeController::class, 'storeSchedule'])->name('admin.schedules.store');
    Route::patch('/admin/events/{event}/schedules/{schedule}', [PublicProgrammeController::class, 'updateSchedule'])->name('admin.schedules.update');
    Route::post('/admin/events/{event}/schedules/{schedule}/publish', [PublicProgrammeController::class, 'publishSchedule'])->name('admin.schedules.publish');
    Route::post('/admin/events/{event}/competitions/{competition}/schedules/publish', [PublicProgrammeController::class, 'publishCompetitionSchedules'])->name('admin.schedules.publish-competition');
    Route::post('/admin/events/{event}/schedule-publications/{publication}/withdraw', [PublicProgrammeController::class, 'withdrawSchedule'])->name('admin.schedule-publications.withdraw');
    Route::post('/admin/events/{event}/competitions/{competition}/cover-images', [PublicProgrammeController::class, 'uploadCover'])->name('admin.cover-images.store');
    Route::get('/admin/events/{event}/cover-images/{cover}/preview', [PublicProgrammeController::class, 'previewCover'])->name('admin.cover-images.preview');
    Route::post('/admin/events/{event}/cover-images/{cover}/publish', [PublicProgrammeController::class, 'publishCover'])->name('admin.cover-images.publish');
    Route::post('/admin/events/{event}/cover-images/{cover}/withdraw', [PublicProgrammeController::class, 'withdrawCover'])->name('admin.cover-images.withdraw');
    Route::get('/judge/scorecards/{scorecard}', [ScorecardController::class, 'show'])->name('judge.scorecards.show');
    Route::patch('/judge/scorecards/{scorecard}', [ScorecardController::class, 'update'])->name('judge.scorecards.update');
    Route::post('/judge/scorecards/{scorecard}/submit', [ScorecardController::class, 'submit'])->name('judge.scorecards.submit');
    Route::get('/tabulator/contests/{contest}', [ContestController::class, 'show'])
        ->name('tabulator.contests.show');
    Route::post('/tabulator/contests/{contest}/commands', [ContestController::class, 'command'])
        ->name('tabulator.contests.command');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
