import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';

const tabs = [
    ['programme', 'Programme Matrix'],
    ['access', 'People & Access'],
    ['tournaments', 'Tournament Desk'],
];

function Pill({ children, tone = 'neutral' }) {
    const tones = {
        neutral: 'border-slate-200 bg-slate-50 text-slate-600',
        ready: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        blocked: 'border-amber-200 bg-amber-50 text-amber-800',
        navy: 'border-white/15 bg-white/10 text-white',
        gold: 'border-[#d5a21f]/40 bg-[#d5a21f]/15 text-[#ffe197]',
    };

    return <span className={`inline-flex rounded-full border px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-[0.12em] ${tones[tone]}`}>{children}</span>;
}

function EventSelector({ events, event, activeTab }) {
    if (events.length < 2) return null;

    return (
        <label className="block min-w-56">
            <span className="sr-only">Selected event</span>
            <select
                value={event?.id ?? ''}
                onChange={(input) => router.get(route('dashboard'), { event: input.target.value, tab: activeTab }, { preserveScroll: true })}
                className="w-full rounded-xl border-white/20 bg-[#123c61] text-sm text-white focus:border-[#d5a21f] focus:ring-[#d5a21f]"
            >
                {events.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
            </select>
        </label>
    );
}

function ReadinessRail({ items }) {
    return (
        <ol aria-label="Event readiness" className="grid gap-2 sm:grid-cols-3 xl:grid-cols-7">
            {items.map((item, index) => (
                <li key={item.key} className={`rounded-2xl border p-3 ${item.complete ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
                    <div className="flex items-center gap-2">
                        <span aria-hidden="true" className={`grid size-6 place-items-center rounded-full text-xs font-bold ${item.complete ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-600'}`}>{item.complete ? '✓' : index + 1}</span>
                        <span className="text-xs font-bold uppercase tracking-[0.1em] text-slate-700">{item.label}</span>
                    </div>
                    <p className="mt-2 pl-8 text-xs text-slate-500">{item.detail}</p>
                </li>
            ))}
        </ol>
    );
}

function Criteria({ criteria }) {
    if (!criteria?.length) return <span className="text-xs text-slate-400">Objective scoring</span>;

    return (
        <details className="group">
            <summary className="cursor-pointer text-xs font-semibold text-[#0b2e4f] underline decoration-[#d5a21f] underline-offset-4">{criteria.length} judging criteria</summary>
            <ol className="mt-3 space-y-1.5 border-l-2 border-[#d5a21f]/40 pl-3 text-xs text-slate-600">
                {criteria.map((criterion) => (
                    <li key={criterion.name} className="flex justify-between gap-4">
                        <span>{criterion.name}</span>
                        <span className="font-mono font-semibold tabular-nums">{criterion.weight === null ? 'Unspecified' : `${criterion.weight}%`}</span>
                    </li>
                ))}
            </ol>
        </details>
    );
}

function ProgrammeMatrix({ programme }) {
    const ready = programme.filter((item) => item.blockers.length === 0).length;

    return (
        <section aria-labelledby="programme-title">
            <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#9a6e00]">Proposal programme</p>
                    <h2 id="programme-title" className="mt-1 font-serif text-2xl font-bold text-[#17212b]">Sports, events, and judging rules</h2>
                </div>
                <p className="text-sm text-slate-500">{ready} ready · {programme.length - ready} source-blocked</p>
            </div>
            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="hidden grid-cols-[1.3fr_.65fr_.8fr_.7fr_1.5fr] gap-4 border-b border-slate-200 bg-[#0b2e4f] px-5 py-3 text-xs font-bold uppercase tracking-[0.12em] text-white lg:grid">
                    <span>Competition</span><span>Division</span><span>Format</span><span>Entries</span><span>Rules / source</span>
                </div>
                <div className="divide-y divide-slate-100">
                    {programme.map((item) => (
                        <article key={item.id} className="grid gap-4 p-5 lg:grid-cols-[1.3fr_.65fr_.8fr_.7fr_1.5fr] lg:items-start">
                            <div>
                                <h3 className="font-semibold text-slate-950">{item.competition}</h3>
                                <p className="mt-1 text-xs text-slate-500">{item.participant_mode?.replaceAll('_', ' ')} · {item.family?.replaceAll('_', ' ')}</p>
                            </div>
                            <p className="text-sm font-medium text-slate-700"><span className="mr-2 text-xs uppercase text-slate-400 lg:hidden">Division</span>{item.division}</p>
                            <p className="font-mono text-xs text-slate-600"><span className="mr-2 font-sans uppercase text-slate-400 lg:hidden">Format</span>{item.format?.replaceAll('_', ' ') ?? 'Not set'}</p>
                            <p className="font-mono text-sm font-semibold tabular-nums text-slate-700"><span className="mr-2 font-sans text-xs uppercase text-slate-400 lg:hidden">Entries</span>{item.entry_count}</p>
                            <div className="space-y-3">
                                <div className="flex flex-wrap gap-2">
                                    <Pill tone={item.blockers.length ? 'blocked' : 'ready'}>{item.blockers.length ? 'Blocked' : 'Ready'}</Pill>
                                    <Pill>{item.source_status}</Pill>
                                </div>
                                <Criteria criteria={item.criteria} />
                                {item.blockers.length ? (
                                    <ul className="space-y-1 text-xs leading-5 text-amber-800">
                                        {item.blockers.slice(0, 3).map((blocker) => <li key={blocker}>• {blocker}</li>)}
                                    </ul>
                                ) : null}
                                <p className="text-[0.7rem] text-slate-400">{item.source_reference}</p>
                            </div>
                        </article>
                    ))}
                </div>
            </div>
        </section>
    );
}

function PeopleAccess({ event, globalAdmin, people }) {
    return (
        <section aria-labelledby="access-title">
            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#9a6e00]">Identity boundary</p>
                    <h2 id="access-title" className="mt-1 font-serif text-2xl font-bold text-[#17212b]">One Global Admin, scoped event workers</h2>
                </div>
                <Link href={route('admin.accounts.create', event.id)} className="rounded-full bg-[#0b2e4f] px-5 py-2.5 text-center text-sm font-semibold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d5a21f] focus-visible:ring-offset-2">Provision Judge or Tabulator</Link>
            </div>
            <div className="grid gap-4 lg:grid-cols-3">
                <article className="rounded-2xl bg-[#0b2e4f] p-5 text-white shadow-sm">
                    <Pill tone="gold">Global Admin</Pill>
                    <h3 className="mt-4 font-serif text-xl font-bold">{globalAdmin?.name}</h3>
                    <p className="mt-1 break-all text-sm text-white/65">{globalAdmin?.email}</p>
                    <p className="mt-5 text-xs leading-5 text-white/55">Platform-wide authority. This role is not duplicated per event.</p>
                </article>
                {people.map((person) => (
                    <article key={`${person.id}-${person.role}`} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-3">
                            <div><h3 className="font-semibold text-slate-950">{person.name}</h3><p className="mt-1 break-all text-xs text-slate-500">{person.email}</p></div>
                            <Pill>{person.role}</Pill>
                        </div>
                        <div className="mt-5 border-t border-slate-100 pt-4">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Exact assignments</p>
                            {person.assignments.length ? <ul className="mt-3 space-y-2 text-sm text-slate-700">{person.assignments.map((assignment) => <li key={assignment.id}><span className="font-mono text-[0.68rem] uppercase text-slate-400">{assignment.scope.replaceAll('_', ' ')}</span><br />{assignment.label}</li>)}</ul> : <p className="mt-3 text-sm text-amber-700">No active assignment</p>}
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

function Standings({ rows }) {
    if (!rows?.length || !rows.some((row) => row.played > 0)) return null;

    return (
        <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200">
            <table className="min-w-full text-left text-xs">
                <thead className="bg-slate-50 text-slate-500"><tr><th className="px-3 py-2">Team</th><th className="px-3 py-2">W</th><th className="px-3 py-2">D</th><th className="px-3 py-2">L</th><th className="px-3 py-2">Pts</th></tr></thead>
                <tbody className="divide-y divide-slate-100">{rows.map((row) => <tr key={row.entry_id}><td className="px-3 py-2 font-medium">{row.delegation ?? row.entry_name}</td><td className="px-3 py-2 tabular-nums">{row.wins}</td><td className="px-3 py-2 tabular-nums">{row.draws}</td><td className="px-3 py-2 tabular-nums">{row.losses}</td><td className="px-3 py-2 font-bold tabular-nums">{row.match_points}</td></tr>)}</tbody>
            </table>
        </div>
    );
}

function TournamentDesk({ programme }) {
    const divisions = programme.filter((item) => item.can_draw);

    function draw(item, redraw = false) {
        if (redraw && !window.confirm(`Replace the unpublished ${item.competition} — ${item.division} draw? The old preview will remain in the audit history.`)) return;
        router.post(route('admin.draws.random', item.id), { command_uuid: crypto.randomUUID(), redraw }, { preserveScroll: true });
    }

    function publish(item) {
        if (!window.confirm(`Publish the ${item.competition} — ${item.division} bracket? The draw becomes immutable after publication.`)) return;
        router.post(route('admin.brackets.publish', item.tournament.bracket_id), {}, { preserveScroll: true });
    }

    return (
        <section aria-labelledby="tournament-title">
            <div className="mb-5">
                <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#9a6e00]">Recorded randomization</p>
                <h2 id="tournament-title" className="mt-1 font-serif text-2xl font-bold text-[#17212b]">Tournament Desk</h2>
                <p className="mt-2 text-sm text-slate-500">Random draws are seed-backed and reproducible. BYEs do not count as wins, losses, or points.</p>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                {divisions.map((item) => (
                    <article key={item.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex items-start justify-between gap-4">
                            <div><p className="text-xs font-bold uppercase tracking-[0.12em] text-[#9a6e00]">{item.format.replaceAll('_', ' ')}</p><h3 className="mt-1 font-serif text-xl font-bold text-slate-950">{item.competition} — {item.division}</h3></div>
                            <Pill tone={item.tournament?.state === 'published' ? 'ready' : 'neutral'}>{item.tournament?.state ?? 'No draw'}</Pill>
                        </div>
                        {item.tournament ? (
                            <div className="mt-5">
                                <p className="text-xs text-slate-500">{item.tournament.source?.replaceAll('_', ' ')} · {item.tournament.algorithm_version ?? 'legacy algorithm'}</p>
                                <ol className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">{item.tournament.draw_order.map((draw, index) => <li key={draw.entry_id} className="rounded-lg bg-slate-50 px-3 py-2 text-xs"><span className="mr-2 font-mono text-slate-400">{index + 1}</span>{draw.label}</li>)}</ol>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {item.tournament.state === 'preview' ? <button type="button" onClick={() => publish(item)} className="rounded-full bg-[#0b2e4f] px-4 py-2 text-xs font-bold text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d5a21f] focus-visible:ring-offset-2">Publish Bracket</button> : null}
                                    {item.tournament.state === 'preview' ? <button type="button" onClick={() => draw(item, true)} className="rounded-full border border-amber-300 px-4 py-2 text-xs font-bold text-amber-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">Redraw</button> : null}
                                </div>
                            </div>
                        ) : <button type="button" onClick={() => draw(item)} className="mt-5 rounded-full bg-[#d5a21f] px-5 py-2.5 text-sm font-bold text-[#17212b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b2e4f] focus-visible:ring-offset-2">Generate Random Draw</button>}
                        <Standings rows={item.standings} />
                    </article>
                ))}
            </div>
        </section>
    );
}

function TeamBoard({ teams }) {
    return (
        <section aria-labelledby="teams-title" className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-[0.18em] text-[#9a6e00]">Department teams</p>
            <h2 id="teams-title" className="mt-1 font-serif text-2xl font-bold">Championship table</h2>
            <div className="mt-5 overflow-x-auto"><table className="min-w-full text-left text-sm"><thead className="border-b border-slate-200 text-xs uppercase tracking-[0.1em] text-slate-400"><tr><th className="pb-3">Team</th><th className="pb-3 text-right">Official points</th></tr></thead><tbody className="divide-y divide-slate-100">{teams.map((team, index) => <tr key={team.id}><td className="py-3"><span className="mr-3 font-mono text-xs text-slate-400">{index + 1}</span><strong>{team.abbreviation}</strong><span className="ml-2 text-xs text-slate-500">{team.name}</span></td><td className="py-3 text-right font-mono text-lg font-bold tabular-nums">{team.championship_total}</td></tr>)}</tbody></table></div>
        </section>
    );
}

function WorkerDashboard({ event, events, workQueue, activeTab }) {
    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-[#9a6e00]">Scoped event access</p><h1 className="mt-1 font-serif text-2xl font-bold">My scoring assignments</h1></div>}>
            <Head title="My scoring assignments" />
            <main className="min-h-[calc(100vh-9rem)] bg-[#f4f1e8] px-4 py-8 sm:px-6 lg:px-10"><div className="mx-auto max-w-5xl">
                <section className="rounded-3xl bg-[#0b2e4f] p-6 text-white sm:p-8"><div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><Pill tone="gold">{event.roles.join(' + ')}</Pill><h2 className="mt-4 font-serif text-3xl font-bold">{event.name}</h2><p className="mt-2 text-sm text-white/65">Access is limited to the exact assignments below.</p></div><EventSelector events={events} event={event} activeTab={activeTab} /></div></section>
                <section className="mt-6 grid gap-4 sm:grid-cols-2">{workQueue.length ? workQueue.map((work) => <article key={work.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><Pill>{work.scope.replaceAll('_', ' ')}</Pill><h3 className="mt-4 font-semibold text-slate-950">{work.label}</h3>{work.url ? <Link href={work.url} className="mt-5 inline-flex rounded-full bg-[#0b2e4f] px-4 py-2 text-sm font-semibold text-white">Open assignment</Link> : <p className="mt-4 text-sm text-slate-500">This division assignment includes its current and future work items.</p>}</article>) : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">Your role is active, but no scoring target is assigned yet.</div>}</section>
            </div></main>
        </AuthenticatedLayout>
    );
}

export default function Dashboard({ events = [], event, active_tab: activeTab = 'programme', readiness = [], programme = [], teams = [], people = [], global_admin: globalAdmin, pending_approvals: pendingApprovals = [], live_contests: liveContests = [], work_queue: workQueue = [], capabilities = {} }) {
    const flash = usePage().props.flash?.status;

    if (event && !capabilities.global_admin) return <WorkerDashboard event={event} events={events} workQueue={workQueue} activeTab={activeTab} />;

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-[#9a6e00]">CSPC SIKLAB control room</p><h1 className="mt-1 font-serif text-2xl font-bold text-[#17212b]">Global administration</h1></div>}>
            <Head title="Global administration" />
            <main className="min-h-[calc(100vh-9rem)] bg-[#f4f1e8] px-4 py-8 sm:px-6 lg:px-10">
                <div className="mx-auto max-w-[90rem] space-y-7">
                    {flash ? <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{flash}</div> : null}
                    {!event ? (
                        <section className="overflow-hidden rounded-3xl bg-[#0b2e4f] p-7 text-white sm:p-10"><Pill tone="gold">Global Admin ready</Pill><h2 className="mt-5 max-w-2xl font-serif text-4xl font-bold">Create the first event workspace</h2><p className="mt-3 max-w-xl text-white/65">The Global Admin is the sole platform authority. Event workers are added later as scoped Judges or Tabulators.</p><Link href={route('admin.events.create')} className="mt-7 inline-flex rounded-full bg-[#d5a21f] px-5 py-3 text-sm font-bold text-[#17212b]">Create event</Link></section>
                    ) : (
                        <>
                            <section className="relative overflow-hidden rounded-3xl bg-[#0b2e4f] p-6 text-white shadow-xl shadow-slate-900/10 sm:p-9"><div className="absolute -right-16 -top-20 size-64 rounded-full border-[24px] border-[#d5a21f]/20" /><div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"><div><div className="flex flex-wrap gap-2"><Pill tone="gold">Global Admin</Pill><Pill tone="navy">{event.state}</Pill></div><h2 className="mt-4 font-serif text-4xl font-bold">{event.name}</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-white/65">Proposal rules, scoped accounts, random draws, results, and official championship points in one auditable workspace.</p></div><div className="flex flex-col gap-3 sm:flex-row sm:items-center"><EventSelector events={events} event={event} activeTab={activeTab} /><Link href={route('admin.registrations.index', event.id)} className="rounded-xl bg-[#d5a21f] px-4 py-2.5 text-center text-sm font-bold text-[#17212b] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white">Open Registration Desk</Link><Link href={route('admin.events.create')} className="rounded-xl border border-white/20 px-4 py-2.5 text-center text-sm font-semibold text-white">New Event</Link></div></div></section>
                            <ReadinessRail items={readiness} />
                            <nav aria-label="Dashboard work areas" className="flex gap-2 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">{tabs.map(([key, label]) => <button key={key} type="button" onClick={() => router.get(route('dashboard'), { event: event.id, tab: key }, { preserveScroll: true, replace: true })} aria-pressed={activeTab === key} className={`whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d5a21f] ${activeTab === key ? 'bg-[#0b2e4f] text-white' : 'text-slate-600 hover:bg-slate-50'}`}>{label}</button>)}</nav>
                            {programme.length === 0 ? <section className="rounded-2xl border border-dashed border-slate-300 bg-white p-8"><h2 className="font-serif text-2xl font-bold">Programme not imported</h2><p className="mt-2 text-sm text-slate-500">Apply the approved 2025 proposal to create the seven department teams, sports, formats, criteria, and known source blockers.</p><button type="button" onClick={() => router.post(route('admin.programme.apply', event.id), {}, { preserveScroll: true })} className="mt-5 rounded-full bg-[#d5a21f] px-5 py-2.5 text-sm font-bold text-[#17212b]">Apply approved proposal</button></section> : null}
                            {programme.length > 0 && activeTab === 'programme' ? <div className="grid gap-7 xl:grid-cols-[1fr_22rem]"><ProgrammeMatrix programme={programme} /><TeamBoard teams={teams} /></div> : null}
                            {programme.length > 0 && activeTab === 'access' ? <PeopleAccess event={event} globalAdmin={globalAdmin} people={people} /> : null}
                            {programme.length > 0 && activeTab === 'tournaments' ? <TournamentDesk programme={programme} /> : null}
                            <section className="grid gap-5 lg:grid-cols-2"><article className="rounded-2xl border border-slate-200 bg-white p-5"><div className="flex items-center justify-between"><h2 className="font-serif text-xl font-bold">Approval queue</h2><Link href={route('admin.approvals.index', event.id)} className="text-sm font-semibold text-[#0b2e4f] underline decoration-[#d5a21f] underline-offset-4">Review</Link></div><p className="mt-4 text-4xl font-bold tabular-nums">{pendingApprovals.length}</p><p className="mt-1 text-sm text-slate-500">submitted outcomes and final placements</p></article><article className="rounded-2xl border border-slate-200 bg-white p-5"><h2 className="font-serif text-xl font-bold">Live contests</h2><p className="mt-4 text-4xl font-bold tabular-nums">{liveContests.length}</p><p className="mt-1 text-sm text-slate-500">unofficial until Global Admin approval</p></article></section>
                        </>
                    )}
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
