import { describe, expect, test } from 'vitest';
import { selectContestDependency } from '../../resources/js/lib/commandOutbox';

describe('command outbox dependency selection', () => {
    test('chains a new contest command to the latest retryable command for that contest', () => {
        const dependency = selectContestDependency(
            { command_uuid: 'd', contest_id: '9' },
            [
                { command_uuid: 'a', contest_id: '9', state: 'pending', created_at: '2026-08-18T10:00:00Z' },
                { command_uuid: 'b', contest_id: '9', state: 'unknown', created_at: '2026-08-18T10:01:00Z' },
                { command_uuid: 'c', contest_id: '9', state: 'conflicted', created_at: '2026-08-18T10:02:00Z' },
                { command_uuid: 'other', contest_id: '10', state: 'pending', created_at: '2026-08-18T10:03:00Z' },
            ],
        );

        expect(dependency).toBe('b');
    });

    test('does not chain to an applied or conflicted command', () => {
        expect(selectContestDependency(
            { command_uuid: 'c', contest_id: '9' },
            [
                { command_uuid: 'a', contest_id: '9', state: 'applied', created_at: '2026-08-18T10:00:00Z' },
                { command_uuid: 'b', contest_id: '9', state: 'conflicted', created_at: '2026-08-18T10:01:00Z' },
            ],
        )).toBeNull();
    });
});
