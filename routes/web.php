<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ConfigurationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\ReportController;
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

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/admin/events/create', [EventController::class, 'create'])->name('admin.events.create');
    Route::post('/admin/events', [EventController::class, 'store'])->name('admin.events.store');
    Route::get('/admin/events/{event}/accounts/create', [AccountController::class, 'create'])->name('admin.accounts.create');
    Route::post('/admin/events/{event}/accounts', [AccountController::class, 'store'])->name('admin.accounts.store');
    Route::get('/admin/events/{event}/approvals', [ApprovalController::class, 'index'])->name('admin.approvals.index');
    Route::post('/admin/results/{submission}/approve', [ApprovalController::class, 'approveResult'])->name('admin.results.approve');
    Route::post('/admin/results/{submission}/reject', [ApprovalController::class, 'rejectResult'])->name('admin.results.reject');
    Route::post('/admin/divisions/{division}/placements', [ApprovalController::class, 'submitPlacement'])->name('admin.placements.submit');
    Route::post('/admin/placements/{placement}/approve', [ApprovalController::class, 'approvePlacement'])->name('admin.placements.approve');
    Route::get('/admin/events/{event}/reports/championship.csv', [ReportController::class, 'championship'])->name('admin.reports.championship');
    Route::post('/admin/events/{event}/competitions', [ConfigurationController::class, 'storeCompetition'])->name('admin.competitions.store');
    Route::post('/admin/divisions/{division}/rule-versions', [ConfigurationController::class, 'storeRuleVersion'])->name('admin.rule-versions.store');
    Route::post('/admin/rule-versions/{version}/activate', [ConfigurationController::class, 'activateRuleVersion'])->name('admin.rule-versions.activate');
    Route::post('/admin/events/{event}/assignments', [ConfigurationController::class, 'grantAssignment'])->name('admin.assignments.store');
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
