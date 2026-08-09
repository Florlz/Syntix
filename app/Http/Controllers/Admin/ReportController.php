<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\StandingCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function championship(Request $request, Event $event, StandingCalculator $standings): StreamedResponse
    {
        if (! $request->user()->hasAdminAccess($event)) {
            throw new AuthorizationException('Only the active Global Admin can download private reports.');
        }

        $rows = $standings->forEvent($event);

        return response()->streamDownload(function () use ($event, $rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Event', 'Delegation', 'Abbreviation', 'Championship points', 'Official as of']);

            foreach ($rows as $delegation) {
                fputcsv($handle, [
                    $event->name,
                    $delegation->name,
                    $delegation->abbreviation,
                    (string) ($delegation->championship_total ?? '0'),
                    now()->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, "{$event->slug}-championship-points.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
