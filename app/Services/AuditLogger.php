<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    /**
     * Record only the security-relevant state supplied by the caller. The
     * service never serializes a complete model into an audit row.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $context
     */
    public function record(
        ?User $actor,
        AuditAction|string $action,
        Model|string|null $target = null,
        ?Event $event = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        array $context = [],
    ): AuditLog {
        $request = app()->bound('request') ? app('request') : null;
        $sessionId = null;

        if ($request !== null && $request->hasSession()) {
            $sessionId = $request->session()->getId();
        }

        $actionValue = $action instanceof AuditAction ? $action->value : $action;
        $targetType = null;
        $targetId = null;

        if ($target instanceof Model) {
            $targetType = $target->getMorphClass();
            $targetId = $target->getKey() === null ? null : (string) $target->getKey();
        } elseif (is_string($target)) {
            $targetType = $target;
        }

        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'event_id' => $event?->getKey(),
            'action' => $actionValue,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'session_id' => $sessionId,
            'request_id' => $request?->header('X-Request-ID'),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'context' => $context,
        ]);
    }
}
