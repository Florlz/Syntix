<?php

namespace App\Services;

use App\Enums\ScoringCommandDisposition;
use App\Models\Event;
use App\Models\ScoringCommandReceipt;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final class ScoringCommandProcessor
{
    /**
     * @param  array<string, mixed>  $envelope
     * @param  Closure(): array{response?: array<string, mixed>, resulting_revision?: int|null}  $operation
     */
    public function execute(User $actor, Event $event, array $envelope, Closure $operation): ScoringCommandReceipt
    {
        $canonical = self::canonicalEnvelope($envelope);
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        $uuid = (string) ($envelope['command_uuid'] ?? '');

        if ($uuid === '') {
            throw new \InvalidArgumentException('A command_uuid is required.');
        }

        $failure = null;
        $receipt = DB::transaction(function () use ($actor, $event, $envelope, $canonical, $hash, $uuid, $operation, &$failure): ScoringCommandReceipt {
            $existing = ScoringCommandReceipt::query()
                ->where('command_uuid', $uuid)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->actor_id !== $actor->getKey()
                    || $existing->event_id !== $event->getKey()
                    || ! hash_equals((string) $existing->envelope_hash, $hash)) {
                    throw new \DomainException('idempotency_key_reused');
                }

                return $existing;
            }

            $this->assertDependency($actor, $event, $envelope);

            $receipt = ScoringCommandReceipt::create([
                'command_uuid' => $uuid,
                'actor_id' => $actor->getKey(),
                'event_id' => $event->getKey(),
                'schema_version' => (int) ($envelope['schema_version'] ?? 1),
                'command_type' => (string) ($envelope['command_type'] ?? ''),
                'disposition' => ScoringCommandDisposition::Processing,
                'envelope_hash' => $hash,
                'base_revision' => $envelope['base_revision'] ?? null,
                'depends_on_command_uuid' => $envelope['depends_on_command_uuid'] ?? null,
                'canonical_envelope' => $canonical,
            ]);

            try {
                // Keep the receipt transaction committed even when the mutation
                // rolls back, so every terminal command disposition is durable.
                $result = DB::transaction($operation);
                $receipt->update([
                    'disposition' => ScoringCommandDisposition::Applied,
                    'response' => $result['response'] ?? [],
                    'resulting_revision' => $result['resulting_revision'] ?? null,
                    'applied_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                $receipt->update([
                    'disposition' => str_contains(strtolower($exception->getMessage()), 'stale')
                        ? ScoringCommandDisposition::Conflicted
                        : ScoringCommandDisposition::Rejected,
                    'error_code' => $exception->getMessage(),
                ]);
                $failure = $exception;
            }

            return $receipt->fresh();
        });

        if ($failure !== null) {
            throw $failure;
        }

        return $receipt;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    public static function canonicalEnvelope(array $envelope): array
    {
        $canonical = [
            'schema_version' => (int) ($envelope['schema_version'] ?? 1),
            'command_type' => (string) ($envelope['command_type'] ?? ''),
            'event_id' => (int) ($envelope['event_id'] ?? 0),
            'competition_id' => $envelope['competition_id'] ?? null,
            'division_id' => $envelope['division_id'] ?? null,
            'contest_id' => $envelope['contest_id'] ?? null,
            'payload' => $envelope['payload'] ?? [],
            'base_revision' => $envelope['base_revision'] ?? null,
            'depends_on_command_uuid' => $envelope['depends_on_command_uuid'] ?? null,
        ];

        self::sortRecursively($canonical);

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function assertDependency(User $actor, Event $event, array $envelope): void
    {
        $dependency = $envelope['depends_on_command_uuid'] ?? null;

        if ($dependency === null) {
            return;
        }

        $receipt = ScoringCommandReceipt::query()
            ->where('command_uuid', $dependency)
            ->first();

        if ($receipt === null
            || $receipt->actor_id !== $actor->getKey()
            || $receipt->event_id !== $event->getKey()
            || $receipt->disposition !== ScoringCommandDisposition::Applied->value
            || (int) $receipt->resulting_revision !== (int) ($envelope['base_revision'] ?? -1)) {
            throw new \DomainException('command_dependency_not_applied');
        }
    }

    /** @param array<string, mixed> &$value */
    private static function sortRecursively(array &$value): void
    {
        ksort($value);

        foreach ($value as &$child) {
            if (is_array($child)) {
                self::sortRecursively($child);
            }
        }
    }
}
