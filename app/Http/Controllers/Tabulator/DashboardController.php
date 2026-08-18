<?php

namespace App\Http\Controllers\Tabulator;

use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Services\TabulatorWorkQueueReadModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TabulatorWorkQueueReadModel $queue): Response
    {
        $user = $request->user();
        if (! $user->eventRoles()->active()->where('role', EventRole::Tabulator->value)->exists()) {
            throw new AuthorizationException('An active Tabulator event role is required.');
        }

        return Inertia::render('Tabulator/Index', $queue->for($user, $request->integer('event') ?: null));
    }
}
