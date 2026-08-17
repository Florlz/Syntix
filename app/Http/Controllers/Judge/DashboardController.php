<?php

namespace App\Http\Controllers\Judge;

use App\Enums\EventRole;
use App\Http\Controllers\Controller;
use App\Services\JudgeWorkQueueReadModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, JudgeWorkQueueReadModel $queue): Response
    {
        $user = $request->user();
        if (! $user->eventRoles()->active()->where('role', EventRole::Judge->value)->exists()) {
            throw new AuthorizationException('An active Judge event role is required.');
        }

        return Inertia::render('Judge/Index', $queue->for($user, $request->integer('event') ?: null));
    }
}
