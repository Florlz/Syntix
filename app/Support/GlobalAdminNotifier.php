<?php

namespace App\Support;

use App\Enums\AccountState;
use App\Models\DivisionPlacement;
use App\Models\ResultSubmission;
use App\Models\User;
use App\Notifications\AdminActivityNotification;

final class GlobalAdminNotifier
{
    public static function resultSubmitted(ResultSubmission $submission): void
    {
        $submission->loadMissing('contest.division.competition.event');

        $contest = $submission->contest;
        $division = $contest?->division;
        $competition = $division?->competition;
        $event = $competition?->event;
        $subject = self::subjectLabel($contest?->name, $competition?->name, $division?->name);

        self::sendApprovalNotification([
            'kind' => 'approval_result',
            'title' => 'Result ready for review',
            'message' => $subject.' was submitted.',
            'event_id' => $event?->getKey() === null ? null : (string) $event->getKey(),
            'action' => self::approvalAction($event?->getKey()),
        ]);
    }

    public static function placementSubmitted(DivisionPlacement $placement): void
    {
        $placement->loadMissing('division.competition.event');

        $division = $placement->division;
        $competition = $division?->competition;
        $event = $competition?->event;
        $subject = self::subjectLabel(null, $competition?->name, $division?->name);

        self::sendApprovalNotification([
            'kind' => 'approval_placement',
            'title' => 'Final placement ready for review',
            'message' => $subject.' was submitted.',
            'event_id' => $event?->getKey() === null ? null : (string) $event->getKey(),
            'action' => self::approvalAction($event?->getKey()),
        ]);
    }

    public static function administratorLogin(string $browser, string $platform): void
    {
        self::send([
            'kind' => 'security_login',
            'title' => 'New administrator sign-in',
            'message' => $browser.' · '.$platform,
            'action' => [
                'label' => 'Review sessions',
                'route' => 'settings.edit',
                'params' => ['section' => 'security'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function sendApprovalNotification(array $payload): void
    {
        self::send($payload, approvalsOnly: true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function send(array $payload, bool $approvalsOnly = false): void
    {
        User::query()
            ->where('is_global_admin', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('account_state')
                    ->orWhere('account_state', AccountState::Active->value);
            })
            ->get()
            ->filter(fn (User $admin): bool => ! $approvalsOnly || (bool) ($admin->normalizedPreferences()['notifications']['approvals'] ?? true))
            ->each(fn (User $admin) => $admin->notify(new AdminActivityNotification($payload)));
    }

    /**
     * @return array{label: string, route: string, params?: array<string, string>}
     */
    private static function approvalAction(int|string|null $eventId): array
    {
        $action = [
            'label' => 'Review result',
            'route' => 'admin.approvals.index',
        ];

        if ($eventId !== null) {
            $action['params'] = ['event' => (string) $eventId];
        }

        return $action;
    }

    private static function subjectLabel(?string $name, ?string $competition, ?string $division): string
    {
        if ($name !== null && trim($name) !== '') {
            return trim($name);
        }

        return collect([$competition, $division])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->implode(' · ') ?: 'An approval item';
    }
}
