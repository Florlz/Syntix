<?php

namespace Tests\Feature\Offline;

use App\Models\Event;
use App\Models\User;
use App\Services\ScoringCommandProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringCommandProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_retries_return_the_original_receipt_without_reapplying(): void
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $envelope = [
            'command_uuid' => '9b3b7b3a-3e1a-4e50-8e13-18f2a7d7e6af',
            'schema_version' => 1,
            'command_type' => 'record_live_score',
            'event_id' => $event->getKey(),
            'contest_id' => 44,
            'base_revision' => 3,
            'payload' => ['home' => 2, 'away' => 1],
        ];
        $calls = 0;
        $processor = new ScoringCommandProcessor;

        $first = $processor->execute($actor, $event, $envelope, function () use (&$calls): array {
            $calls++;

            return ['response' => ['ok' => true], 'resulting_revision' => 4];
        });
        $second = $processor->execute($actor, $event, $envelope, function () use (&$calls): array {
            $calls++;

            return ['response' => ['ok' => false], 'resulting_revision' => 99];
        });

        $this->assertSame(1, $calls);
        $this->assertSame($first->id, $second->id);
        $this->assertSame('applied', $second->disposition);
        $this->assertSame(4, $second->resulting_revision);
        $this->assertDatabaseCount('scoring_command_receipts', 1);
    }

    public function test_reusing_a_uuid_with_a_different_envelope_is_rejected(): void
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $processor = new ScoringCommandProcessor;
        $envelope = [
            'command_uuid' => 'd2cbd5dd-f350-42e4-93b5-2b73e5f0fba8',
            'schema_version' => 1,
            'command_type' => 'record_live_score',
            'event_id' => $event->getKey(),
            'base_revision' => 1,
            'payload' => ['home' => 1],
        ];

        $processor->execute($actor, $event, $envelope, fn (): array => ['resulting_revision' => 2]);
        $envelope['payload']['home'] = 99;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('idempotency_key_reused');
        $processor->execute($actor, $event, $envelope, fn (): array => ['resulting_revision' => 3]);
    }

    public function test_failed_mutation_keeps_a_terminal_receipt(): void
    {
        $actor = User::factory()->create();
        $event = Event::factory()->create();
        $processor = new ScoringCommandProcessor;
        $envelope = [
            'command_uuid' => '6f7eeb4c-2d6f-4a4c-92dd-97eb32b1b544',
            'schema_version' => 1,
            'command_type' => 'complete_contest',
            'event_id' => $event->getKey(),
            'base_revision' => 1,
            'payload' => [],
        ];

        try {
            $processor->execute($actor, $event, $envelope, function (): array {
                throw new \DomainException('stale_revision');
            });
        } catch (\DomainException) {
            // The failed operation is expected; the receipt is the assertion.
        }

        $this->assertDatabaseHas('scoring_command_receipts', [
            'command_uuid' => $envelope['command_uuid'],
            'disposition' => 'conflicted',
            'error_code' => 'stale_revision',
        ]);
    }
}
