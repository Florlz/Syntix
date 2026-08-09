import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { enqueueCommand, synchronizePendingCommands } from '@/lib/commandOutbox';
import { useEffect, useState } from 'react';

export default function Contest({ contest }) {
    const [home, setHome] = useState(contest.live_payload?.home ?? 0);
    const [away, setAway] = useState(contest.live_payload?.away ?? 0);
    const [chessResult, setChessResult] = useState(contest.live_payload?.result ?? 'home_win');
    const [status, setStatus] = useState('Ready');
    const [revision, setRevision] = useState(contest.revision);
    const isChess = contest.outcome_profile === 'chess';
    const seriesProfile = ['best_of_sets', 'team_tie', 'combat_rounds'].includes(contest.outcome_profile);
    const homeName = contest.entries?.[0]?.name ?? 'Home';
    const awayName = contest.entries?.[1]?.name ?? 'Away';
    const scoreLabel = seriesProfile ? 'Match / set wins' : 'Final points';

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
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold tracking-tight text-slate-900">{contest.name}</h1>}>
            <Head title={`${contest.name} scoring`} />
            <main className="min-h-[calc(100vh-9rem)] bg-[#f4f6f8] px-4 py-8 sm:px-6 lg:px-10">
                <div className="mx-auto max-w-3xl space-y-6">
                    <section className="rounded-3xl bg-[#0b2e4f] p-6 text-white shadow-xl sm:p-8">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div>
                                <p className="text-xs uppercase tracking-[0.18em] text-[#d5a21f]">{contest.competition} / {contest.division}</p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight">Live contest entry</h2>
                            </div>
                            <span role="status" aria-live="polite" className="rounded-full border border-white/20 px-3 py-1 text-xs uppercase tracking-[0.14em]">{status}</span>
                        </div>
                        <p className="mt-4 text-sm text-white/65">{isChess ? 'Record the official board result.' : scoreLabel}</p>
                        <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label className="rounded-2xl bg-white/10 p-4">
                                <span className="text-xs uppercase tracking-[0.14em] text-white/55">{homeName}</span>
                                <input name="home_score" inputMode="decimal" value={home} onChange={(event) => setHome(event.target.value)} type="number" min="0" className="mt-3 w-full border-0 border-b border-white/20 bg-transparent px-0 text-5xl font-semibold text-white outline-none ring-0 focus-visible:border-[#d5a21f] focus-visible:ring-2 focus-visible:ring-[#d5a21f]" />
                            </label>
                            <label className="rounded-2xl bg-white/10 p-4">
                                <span className="text-xs uppercase tracking-[0.14em] text-white/55">{awayName}</span>
                                <input name="away_score" inputMode="decimal" value={away} onChange={(event) => setAway(event.target.value)} type="number" min="0" className="mt-3 w-full border-0 border-b border-white/20 bg-transparent px-0 text-5xl font-semibold text-white outline-none ring-0 focus-visible:border-[#d5a21f] focus-visible:ring-2 focus-visible:ring-[#d5a21f]" />
                            </label>
                        </div>
                        {isChess ? (
                            <label className="mt-4 block rounded-2xl bg-white/10 p-4">
                                <span className="text-xs uppercase tracking-[0.14em] text-white/55">Official result</span>
                                <select name="chess_result" value={chessResult} onChange={(event) => setChessResult(event.target.value)} className="mt-3 w-full rounded-xl border-white/20 bg-[#0b2e4f] text-white focus:border-[#d5a21f] focus:ring-[#d5a21f]">
                                    <option value="home_win">{homeName} wins</option>
                                    <option value="draw">Draw</option>
                                    <option value="away_win">{awayName} wins</option>
                                </select>
                            </label>
                        ) : null}
                    </section>
                    <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <div className="flex flex-wrap gap-3">
                            <button type="button" onClick={() => send('start_contest')} className="rounded-full bg-[#0b2e4f] px-5 py-3 text-sm font-semibold text-white transition hover:bg-sky-900">Start contest</button>
                            <button type="button" onClick={() => send('record_live_score', { home: Number(home), away: Number(away), result: isChess ? chessResult : undefined, phase: 'live' })} className="rounded-full bg-[#d5a21f] px-5 py-3 text-sm font-semibold text-[#17212b] transition hover:bg-[#b98c12]">Save live score</button>
                            <button type="button" onClick={() => send('complete_contest', { outcome_type: 'played', home: Number(home), away: Number(away), result: isChess ? chessResult : undefined, phase: 'final' })} className="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Complete</button>
                            <button type="button" onClick={() => send('submit_contest_result')} className="rounded-full border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Submit for review</button>
                        </div>
                        <p className="mt-5 text-sm text-slate-500">Server revision {revision}. Replays use command UUIDs; stale revisions are not merged automatically.</p>
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
