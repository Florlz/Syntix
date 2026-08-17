import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { enqueueCommand, synchronizePendingCommands } from '@/lib/commandOutbox';
import { useEffect, useState } from 'react';
import ObjectiveScoreProfile from './Profiles/ObjectiveScoreProfile';

export default function Contest({ contest }) {
    const [home, setHome] = useState(contest.live_payload?.home ?? 0);
    const [away, setAway] = useState(contest.live_payload?.away ?? 0);
    const [chessResult, setChessResult] = useState(contest.live_payload?.result ?? 'home_win');
    const [evidence, setEvidence] = useState(contest.live_payload?.evidence?.data ?? {});
    const [status, setStatus] = useState('Ready');
    const [revision, setRevision] = useState(contest.revision);
    const isChess = contest.outcome_profile === 'chess';
    const homeName = contest.entries?.[0]?.name ?? 'Home';
    const awayName = contest.entries?.[1]?.name ?? 'Away';
    const updateEvidence = (field, value) => ({ home: setHome, away: setAway, result: setChessResult, evidence: setEvidence }[field]?.(value));
    const scorePayload = (phase) => ({ home: Number(home), away: Number(away), result: isChess ? chessResult : undefined, evidence: { profile: contest.outcome_profile, data: evidence }, phase });

    useEffect(() => {
        async function sync() {
            await synchronizePendingCommands(async (command) => {
                const response = await fetch(route('tabulator.contests.command', contest.id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify(command),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.error_code ?? 'sync_failed');
                return data;
            });
        }

        window.addEventListener('online', sync);
        if (navigator.onLine) sync();
        return () => window.removeEventListener('online', sync);
    }, [contest.id]);

    async function send(commandType, payload = {}) {
        const command = {
            command_uuid: crypto.randomUUID(),
            schema_version: 1,
            command_type: commandType,
            event_id: contest.event_id,
            division_id: contest.division_id,
            contest_id: contest.id,
            base_revision: revision,
            payload,
        };

        if (!navigator.onLine) {
            await enqueueCommand(command);
            setStatus('Queued offline');
            return;
        }

        setStatus('Syncing');
        const response = await fetch(route('tabulator.contests.command', contest.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(command),
        });
        const data = await response.json();

        if (!response.ok || data.disposition !== 'applied') {
            setStatus(data.error_code ?? 'Conflict requires review');
            return;
        }

        setRevision(data.resulting_revision ?? revision);
        setStatus('Saved');
    }

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Objective tabulation</p><h1 className="font-serif text-2xl font-bold text-foreground">{contest.name}</h1></div>}>
            <Head title={`${contest.name} scoring`} />
            <main className="min-h-[calc(100vh-9rem)] bg-background px-4 py-8 text-foreground sm:px-6 lg:px-10">
                <div className="mx-auto max-w-4xl space-y-6">
                    <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">{contest.competition} / {contest.division}</p><span role="status" aria-live="polite" className="rounded-full border border-border bg-surface px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-muted">{status}</span></div>
                    <ObjectiveScoreProfile profile={contest.outcome_profile} homeName={homeName} awayName={awayName} home={home} away={away} result={chessResult} evidence={evidence} onChange={updateEvidence}/>
                    <section className="rounded-2xl border border-border bg-surface p-6 shadow-xs sm:p-8">
                        <div className="flex flex-wrap gap-3">
                            <button type="button" onClick={() => send('start_contest')} className="min-h-11 rounded-lg bg-primary px-5 text-sm font-bold text-primary-foreground">Start contest</button>
                            <button type="button" onClick={() => send('record_live_score', scorePayload('live'))} className="min-h-11 rounded-lg bg-accent px-5 text-sm font-bold text-accent-foreground">Save live evidence</button>
                            <button type="button" onClick={() => send('complete_contest', { outcome_type: 'played', ...scorePayload('final') })} className="min-h-11 rounded-lg border border-border px-5 text-sm font-bold text-foreground hover:bg-surface-muted">Complete</button>
                            <button type="button" onClick={() => send('submit_contest_result')} className="min-h-11 rounded-lg border border-border px-5 text-sm font-bold text-foreground hover:bg-surface-muted">Submit for review</button>
                        </div>
                        <p className="mt-5 text-sm text-muted">Server revision {revision}. Replays use command UUIDs; stale revisions are not merged automatically.</p>
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
