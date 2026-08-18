import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { enqueueContestCommand, synchronizePendingCommands } from '@/lib/commandOutbox';
import ObjectiveScoreProfile from './Profiles/ObjectiveScoreProfile';

const stateLabels = {
    scheduled: 'Ready to begin',
    live: 'LIVE',
    completed: 'Final score recorded',
    submitted: 'Submitted for review',
    approved: 'Official',
    suspended: 'Scoring suspended',
    cancelled: 'Contest cancelled',
};

function commandHeaders() {
    return {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };
}

export default function Contest({ contest }) {
    const [home, setHome] = useState(contest.live_payload?.home ?? contest.result_payload?.home ?? 0);
    const [away, setAway] = useState(contest.live_payload?.away ?? contest.result_payload?.away ?? 0);
    const [chessResult, setChessResult] = useState(contest.live_payload?.result ?? contest.result_payload?.result ?? 'home_win');
    const [evidence, setEvidence] = useState(contest.live_payload?.evidence?.data ?? contest.result_payload?.evidence?.data ?? {});
    const [status, setStatus] = useState(stateLabels[contest.state] ?? 'Ready');
    const [revision, setRevision] = useState(contest.revision);
    const [commandPending, setCommandPending] = useState(false);
    const isChess = contest.outcome_profile === 'chess';
    const isLive = contest.state === 'live';
    const homeName = contest.entries?.[0]?.name ?? 'Home';
    const awayName = contest.entries?.[1]?.name ?? 'Away';
    const updateEvidence = (field, value) => ({ home: setHome, away: setAway, result: setChessResult, evidence: setEvidence }[field]?.(value));
    const scorePayload = (phase) => ({ home: Number(home), away: Number(away), result: isChess ? chessResult : undefined, evidence: { profile: contest.outcome_profile, data: evidence }, phase });

    useEffect(() => {
        setHome(contest.live_payload?.home ?? contest.result_payload?.home ?? 0);
        setAway(contest.live_payload?.away ?? contest.result_payload?.away ?? 0);
        setChessResult(contest.live_payload?.result ?? contest.result_payload?.result ?? 'home_win');
        setEvidence(contest.live_payload?.evidence?.data ?? contest.result_payload?.evidence?.data ?? {});
        setRevision(contest.revision);
        setStatus(stateLabels[contest.state] ?? 'Ready');
    }, [contest.state, contest.revision]);

    useEffect(() => {
        async function sync() {
            if (!navigator.onLine) return;
            await synchronizePendingCommands(async (command) => {
                const response = await fetch(route('tabulator.contests.command', contest.id), {
                    method: 'POST', headers: commandHeaders(), body: JSON.stringify(command),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error_code ?? 'sync_failed');
                return data;
            });
            router.reload({ only: ['contest'] });
        }

        window.addEventListener('online', sync);
        if (navigator.onLine) sync();
        return () => window.removeEventListener('online', sync);
    }, [contest.id]);

    async function send(commandType, payload = {}) {
        if (commandPending) return;
        setCommandPending(true);
        const command = {
            command_uuid: crypto.randomUUID(), schema_version: 1, command_type: commandType,
            event_id: contest.event_id, division_id: contest.division_id, contest_id: contest.id,
            base_revision: revision, payload,
        };

        try {
            if (!navigator.onLine) {
                await enqueueContestCommand(command);
                setStatus('Queued offline');
                return;
            }

            setStatus('Syncing');
            const response = await fetch(route('tabulator.contests.command', contest.id), {
                method: 'POST', headers: commandHeaders(), body: JSON.stringify(command),
            });
            const data = await response.json();
            if (!response.ok || data.disposition !== 'applied') {
                setStatus(data.error_code ?? 'Conflict requires review');
                return;
            }

            setStatus('Saved');
            router.reload({ only: ['contest'] });
        } catch (error) {
            setStatus(error instanceof Error ? error.message : 'Command could not be sent');
        } finally {
            setCommandPending(false);
        }
    }

    const actionClass = 'min-h-11 rounded-lg px-5 text-sm font-bold disabled:cursor-not-allowed disabled:opacity-45';

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Objective tabulation</p><h1 className="font-serif text-2xl font-bold text-foreground">{contest.name}</h1></div>}>
        <Head title={`${contest.name} scoring`} />
        <main className="min-h-[calc(100vh-9rem)] bg-background px-4 py-8 text-foreground sm:px-6 lg:px-10">
            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">{contest.competition} / {contest.division}</p><span role="status" aria-live="polite" className={`rounded-full border border-border bg-surface px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] ${contest.state === 'live' ? 'text-danger' : 'text-muted'}`}>{status}</span></div>
                {isLive ? <ObjectiveScoreProfile profile={contest.outcome_profile} homeName={homeName} awayName={awayName} home={home} away={away} result={chessResult} evidence={evidence} onChange={updateEvidence}/> : null}
                <section className="rounded-2xl border border-border bg-surface p-6 shadow-xs sm:p-8">
                    {contest.state === 'scheduled' ? <div><h2 className="font-serif text-2xl font-bold">Ready to begin</h2><p className="mt-2 text-sm text-muted">Start the contest when both sides and the official scoring area are ready.</p><button type="button" disabled={commandPending} onClick={() => send('start_contest')} className={`${actionClass} mt-5 bg-primary text-primary-foreground`}>Start contest</button></div> : null}
                    {isLive ? <div><h2 className="font-serif text-2xl font-bold">Live scoring</h2><p className="mt-2 text-sm text-muted">Record evidence, then complete the contest when the official result is verified.</p><div className="mt-5 flex flex-wrap gap-3"><button type="button" disabled={commandPending} onClick={() => send('record_live_score', scorePayload('live'))} className={`${actionClass} bg-accent text-accent-foreground`}>Save live evidence</button><button type="button" disabled={commandPending} onClick={() => send('complete_contest', { outcome_type: 'played', ...scorePayload('final') })} className={`${actionClass} border border-border text-foreground hover:bg-surface-muted`}>Complete contest</button></div></div> : null}
                    {contest.state === 'completed' ? <div><h2 className="font-serif text-2xl font-bold">Final score recorded</h2><p className="mt-2 text-sm text-muted">Review the authoritative result before submitting it for Global Admin approval.</p><button type="button" disabled={commandPending} onClick={() => send('submit_contest_result')} className={`${actionClass} mt-5 border border-border text-foreground hover:bg-surface-muted`}>Submit for review</button></div> : null}
                    {contest.state === 'submitted' ? <div><h2 className="font-serif text-2xl font-bold">Submitted for review</h2><p className="mt-2 text-sm text-muted">This result is read-only while Global Admin reviews the evidence.</p></div> : null}
                    {contest.state === 'approved' ? <div><h2 className="font-serif text-2xl font-bold">Official</h2><p className="mt-2 text-sm text-muted">The result has been approved and is read-only.</p></div> : null}
                    {['suspended', 'cancelled'].includes(contest.state) ? <div><h2 className="font-serif text-2xl font-bold">{stateLabels[contest.state]}</h2><p className="mt-2 text-sm text-muted">No scoring commands are available until an administrator changes this contest state.</p></div> : null}
                    <p className="mt-5 text-xs text-muted">Server revision {revision}. Replays use command UUIDs; stale revisions are not merged automatically.</p>
                </section>
            </div>
        </main>
    </AuthenticatedLayout>;
}
