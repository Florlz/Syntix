import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function StatusPill({ children, tone = 'neutral' }) {
    const tones = {
        neutral: 'bg-slate-100 text-slate-600',
        warning: 'bg-amber-100 text-amber-800',
        success: 'bg-emerald-100 text-emerald-800',
        dark: 'bg-slate-800 text-white',
    };

    return (
        <span
            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] ${tones[tone]}`}
        >
            {children}
        </span>
    );
}

function SectionHeading({ eyebrow, title, detail }) {
    return (
        <div className="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">
                    {eyebrow}
                </p>
                <h2 className="mt-1 text-xl font-semibold tracking-tight text-slate-900">
                    {title}
                </h2>
            </div>
            {detail ? <p className="text-sm text-slate-500">{detail}</p> : null}
        </div>
    );
}

export default function Dashboard({ event, readiness, pending_approvals: pendingApprovals = [], live_contests: liveContests = [], capabilities = {} }) {
    const divisions = readiness?.divisions ?? [];
    const blockedDivisions = readiness?.blocked_divisions ?? 0;
    const isAdmin = event?.role === 'admin';

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">
                            CSPC SIKLAB operations
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">
                            Admin overview
                        </h1>
                    </div>
                    {event ? <StatusPill tone="dark">{event.state}</StatusPill> : null}
                </div>
            }
        >
            <Head title="Admin overview" />

            <div className="min-h-[calc(100vh-9rem)] bg-[#f4f6f8] px-4 py-8 sm:px-6 lg:px-10">
                <div className="mx-auto max-w-7xl space-y-8">
                    {!event ? (
                        <section className="relative overflow-hidden rounded-[2rem] bg-[#0b2e4f] p-7 text-white shadow-xl shadow-slate-900/10 sm:p-10">
                            <div className="absolute -right-20 -top-24 size-72 rounded-full border-[28px] border-[#d5a21f]/30" />
                            <div className="relative max-w-2xl">
                                <StatusPill tone="success">Preparation</StatusPill>
                                <h2 className="mt-5 text-3xl font-semibold tracking-tight sm:text-4xl">
                                    No active SIKLAB event
                                </h2>
                                <p className="mt-4 max-w-xl leading-7 text-white/70">
                                    This workspace is ready for a platform event creator to establish an event shell and grant its first event Admin. Configuration starts only after that audited handoff.
                                </p>
                                <div className="mt-7 flex flex-wrap items-center gap-3 text-sm text-white/65">
                                    {capabilities.event_creator ? (
                                        <Link href={route('admin.events.create')} className="rounded-full bg-[#d5a21f] px-4 py-2 font-semibold text-[#17212b] transition hover:bg-[#b98c12]">
                                            Create event shell
                                        </Link>
                                    ) : null}
                                    <span className="rounded-full border border-white/15 px-4 py-2">
                                        {capabilities.event_creator ? 'Event creator capability active' : 'Awaiting event assignment'}
                                    </span>
                                    <span className="rounded-full border border-white/15 px-4 py-2">
                                        Invite-only access
                                    </span>
                                </div>
                            </div>
                        </section>
                    ) : (
                        <section className="grid gap-5 lg:grid-cols-[1fr_0.42fr]">
                            <div className="relative overflow-hidden rounded-[2rem] bg-[#0b2e4f] p-7 text-white shadow-xl shadow-slate-900/10 sm:p-10">
                                <div className="absolute bottom-0 right-0 h-48 w-48 translate-x-12 translate-y-16 rounded-full border-[24px] border-[#d5a21f]/25" />
                                <div className="relative">
                                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-[#d5a21f]">
                                        Active event
                                    </p>
                                    <h2 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                                        {event.name}
                                    </h2>
                                    <p className="mt-3 max-w-xl leading-7 text-white/65">
                                        {isAdmin
                                            ? 'Keep configuration, readiness, live operations, and approvals visible without turning derived standings into editable totals.'
                                            : `Signed in with the ${event.role} event role. Scoring access remains limited to explicit assignments.`}
                                    </p>
                                    <div className="mt-7 flex flex-wrap gap-3">
                                        <StatusPill tone="dark">{event.role}</StatusPill>
                                        <StatusPill tone="dark">{event.state}</StatusPill>
                                        {isAdmin ? <Link href={route('admin.accounts.create', event.id)} className="rounded-full border border-white/20 px-4 py-2 text-sm text-white/80 transition hover:bg-white/10">Provision account</Link> : null}
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm">
                                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">
                                    Readiness
                                </p>
                                <p className="mt-4 text-5xl font-semibold tracking-tight text-slate-900">
                                    {divisions.length}
                                </p>
                                <p className="mt-1 text-sm text-slate-500">score-bearing Divisions</p>
                                <div className="mt-6 border-t border-slate-100 pt-5">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-slate-500">Activation blockers</span>
                                        <strong className={blockedDivisions > 0 ? 'text-amber-700' : 'text-emerald-700'}>
                                            {blockedDivisions}
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </section>
                    )}

                    {event && isAdmin ? (
                        <section>
                            <SectionHeading
                                eyebrow="Configuration"
                                title="Readiness before live operations"
                                detail="Rules stay blocked until their source, precision, tie, participation, and approval data are complete."
                            />
                            {divisions.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">
                                    No Divisions configured yet. This empty state is authoritative, not a sample metric.
                                </div>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    {divisions.map((division) => {
                                        const currentVersion = division.rule_versions?.[division.rule_versions.length - 1];
                                        const blockers = currentVersion?.blocking_errors ?? ['No governing rule version'];

                                        return (
                                            <article key={division.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                                <div className="flex items-start justify-between gap-4">
                                                    <div>
                                                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                                            {division.competition}
                                                        </p>
                                                        <h3 className="mt-1 text-lg font-semibold text-slate-900">{division.name}</h3>
                                                    </div>
                                                    <StatusPill tone={blockers.length ? 'warning' : 'success'}>
                                                        {blockers.length ? 'Blocked' : 'Ready'}
                                                    </StatusPill>
                                                </div>
                                                <p className="mt-4 text-sm text-slate-500">
                                                    {division.contest_count} contest{division.contest_count === 1 ? '' : 's'} configured
                                                </p>
                                                {blockers.length ? (
                                                    <ul className="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm text-amber-800">
                                                        {blockers.slice(0, 3).map((blocker) => <li key={blocker}>- {blocker}</li>)}
                                                    </ul>
                                                ) : null}
                                            </article>
                                        );
                                    })}
                                </div>
                            )}
                        </section>
                    ) : null}

                    {event && isAdmin ? (
                        <section>
                            <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                <SectionHeading eyebrow="Govern" title="Approval queue" />
                                <Link href={route('admin.approvals.index', event.id)} className="text-sm font-semibold text-sky-800 underline decoration-sky-300 underline-offset-4">Open review desk</Link>
                            </div>
                            {pendingApprovals.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">Nothing is waiting for review.</div>
                            ) : (
                                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                    <div className="divide-y divide-slate-100">
                                        {pendingApprovals.map((item) => (
                                            <div key={`${item.kind}-${item.id}`} className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                                                <div>
                                                    <p className="text-xs font-semibold uppercase tracking-[0.14em] text-sky-700">{item.kind}</p>
                                                    <p className="mt-1 font-medium text-slate-900">{item.label}</p>
                                                </div>
                                                <StatusPill tone="warning">{item.status}</StatusPill>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </section>
                    ) : null}

                    <section>
                        <SectionHeading
                            eyebrow="Operations"
                            title="Live contests"
                            detail="Live values are unofficial until a separate Admin outcome approval."
                        />
                        {liveContests.length === 0 ? (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">
                                No live contests right now.
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                {liveContests.map((contest) => (
                                    <Link
                                        key={contest.id}
                                        href={route('tabulator.contests.show', contest.id)}
                                        className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-md"
                                    >
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.14em] text-slate-400">{contest.competition} / {contest.division}</p>
                                                <h3 className="mt-1 font-semibold text-slate-900">{contest.name}</h3>
                                            </div>
                                            <StatusPill tone="warning">Unofficial</StatusPill>
                                        </div>
                                        <p className="mt-5 text-sm text-slate-500">Revision {contest.revision}</p>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
