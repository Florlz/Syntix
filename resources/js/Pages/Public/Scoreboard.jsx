import { Head } from '@inertiajs/react';

function StateLabel({ state }) {
    const official = state === 'approved';

    return (
        <span className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.14em] ${official ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>
            {official ? 'Official' : 'Unofficial'}
        </span>
    );
}

export default function Scoreboard({ event, competitions = [], leaderboard = [], updated_at: updatedAt }) {
    return (
        <>
            <Head title={`${event.name} scoreboard`} />
            <main className="min-h-screen bg-[#eef2f4] text-slate-900">
                <header className="bg-[#0b2e4f] text-white">
                    <div className="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-12">
                        <div className="flex flex-col justify-between gap-8 md:flex-row md:items-end">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#d5a21f]">Public scoreboard</p>
                                <h1 className="mt-3 text-4xl font-semibold tracking-tight sm:text-6xl">{event.name}</h1>
                                <p className="mt-3 max-w-xl text-sm leading-6 text-white/65">Approved results and championship standings, with live performance clearly marked until official review.</p>
                            </div>
                            <div className="text-sm text-white/60">Updated {updatedAt}</div>
                        </div>
                    </div>
                </header>

                <div className="mx-auto grid max-w-7xl gap-8 px-5 py-8 sm:px-8 lg:grid-cols-[1fr_22rem]">
                    <section className="space-y-6">
                        {competitions.length === 0 ? (
                            <div className="rounded-3xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">No published competitions yet.</div>
                        ) : competitions.map((competition) => (
                            <article key={competition.id} className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                <div className="flex items-center justify-between gap-4 border-b border-slate-100 pb-5">
                                    <h2 className="text-xl font-semibold">{competition.name}</h2>
                                    <span className="text-xs uppercase tracking-[0.16em] text-slate-400">Competition family</span>
                                </div>
                                <div className="mt-6 space-y-7">
                                    {competition.divisions.map((division) => (
                                        <div key={division.id}>
                                            <h3 className="text-sm font-semibold uppercase tracking-[0.14em] text-sky-700">{division.name}</h3>
                                            <div className="mt-3 divide-y divide-slate-100 rounded-2xl border border-slate-100">
                                                {division.contests.length === 0 ? (
                                                    <p className="p-4 text-sm text-slate-500">No contests published.</p>
                                                ) : division.contests.map((contest) => (
                                                    <div key={contest.id} className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                                                        <div>
                                                            <p className="font-medium">{contest.name}</p>
                                                            <p className="mt-1 text-xs text-slate-500">Revision {contest.revision} · {contest.state}</p>
                                                        </div>
                                                        <div className="flex items-center gap-3">
                                                            {contest.live && Object.keys(contest.live).length > 0 ? <span className="text-sm font-semibold text-slate-700">{contest.live.home ?? '-'} : {contest.live.away ?? '-'}</span> : null}
                                                            <StateLabel state={contest.official?.state} />
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </article>
                        ))}
                    </section>

                    <aside className="h-fit rounded-3xl bg-white p-5 shadow-sm ring-1 ring-slate-200 sm:p-7 lg:sticky lg:top-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Championship points</p>
                        <h2 className="mt-2 text-2xl font-semibold tracking-tight">Delegation standings</h2>
                        <p className="mt-3 text-sm leading-6 text-slate-500">Derived from committed signed ledger entries. Preliminary wins and live scores never appear here.</p>
                        <div className="mt-6 space-y-3">
                            {leaderboard.length === 0 ? <p className="text-sm text-slate-500">No approved placements yet.</p> : leaderboard.map((delegation, index) => (
                                <div key={delegation.id} className="flex items-center gap-3 rounded-2xl bg-slate-50 p-3">
                                    <span className="grid size-8 place-items-center rounded-full bg-[#0b2e4f] text-xs font-bold text-white">{index + 1}</span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-medium">{delegation.name}</p>
                                        <p className="text-xs text-slate-500">{delegation.abbreviation}</p>
                                    </div>
                                    <strong className="text-lg">{delegation.total}</strong>
                                </div>
                            ))}
                        </div>
                    </aside>
                </div>
            </main>
        </>
    );
}
