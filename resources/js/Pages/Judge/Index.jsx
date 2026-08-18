import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const statusTone = {
    approved: 'bg-primary/10 text-primary',
    submitted: 'bg-primary/10 text-primary',
    needs_correction: 'bg-danger-surface text-danger',
    blocked: 'bg-danger-surface text-danger',
    in_progress: 'bg-accent/20 text-foreground',
    not_started: 'bg-surface-muted text-muted',
};

function formatSchedule(startsAt) {
    if (!startsAt) return 'Schedule pending';

    return new Intl.DateTimeFormat(undefined, {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(new Date(startsAt));
}

function remainingScorecards(contest) {
    return Math.max(0, contest.scorecard_count - (contest.counts.submitted ?? 0) - (contest.counts.approved ?? 0));
}

function nextStep(contest, remaining) {
    if (contest.readiness?.next_blocker) return contest.readiness.next_blocker;
    if ((contest.counts.needs_correction ?? 0) > 0) return 'Correct returned scorecards.';
    if (remaining > 0) return 'Open the next assigned scorecard.';
    return 'Await scorecard review.';
}

export default function Index({ event, summary = {}, contests = [] }) {
    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.15em] text-primary">{event?.name ?? 'Scoring operations'}</p><h1 className="font-serif text-2xl font-bold">My Judging</h1></div>}>
        <Head title="My Judging" />
        <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8"><div className="mx-auto max-w-6xl space-y-6">
            <section aria-label="Judging summary" className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-4">
                {[
                    ['Assigned', summary.assigned ?? 0],
                    ['Submitted', summary.submitted ?? 0],
                    ['Needs correction', summary.needs_correction ?? 0],
                    ['Blocked', summary.blocked ?? 0],
                ].map(([label, value]) => <div key={label} className="bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{label}</p><p className="mt-1 font-serif text-3xl font-bold tabular-nums">{value}</p></div>)}
            </section>

            {contests.length ? <div className="space-y-5">{contests.map((contest) => {
                const remaining = remainingScorecards(contest);
                const venue = contest.schedule?.venue;

                return <section key={`${contest.name}-${contest.division}`} className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                    <header className="flex flex-wrap items-end justify-between gap-4 border-b border-border p-5">
                        <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Assigned contest</p><h2 className="mt-1 font-serif text-2xl font-bold">{contest.name}</h2><p className="mt-1 text-sm text-muted">{contest.scorecard_count} assigned entries</p></div>
                        <p className="text-sm font-semibold text-muted">{(contest.counts.submitted ?? 0) + (contest.counts.approved ?? 0)} / {contest.scorecard_count} submitted</p>
                    </header>

                    <dl className="grid gap-px border-b border-border bg-border sm:grid-cols-2 lg:grid-cols-5">
                        <div className="bg-surface-muted p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">What</dt><dd className="mt-1 text-sm font-semibold text-foreground">{contest.competition} / {contest.division}</dd></div>
                        <div className="bg-surface-muted p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">When</dt><dd className="mt-1 text-sm font-semibold text-foreground">{formatSchedule(contest.schedule?.starts_at)}</dd></div>
                        <div className="bg-surface-muted p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Where</dt><dd className="mt-1 text-sm font-semibold text-foreground">{venue ? `${venue.name}${venue.location ? ` · ${venue.location}` : ''}` : 'Venue not scheduled yet'}</dd></div>
                        <div className="bg-surface-muted p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Next</dt><dd className={`mt-1 text-sm font-semibold ${contest.readiness?.ready ? 'text-foreground' : 'text-danger'}`}>{nextStep(contest, remaining)}</dd></div>
                        <div className="bg-surface-muted p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Remain</dt><dd className="mt-1 text-sm font-semibold text-foreground">{remaining} scorecards</dd></div>
                    </dl>

                    <ul className="divide-y divide-border">{contest.scorecards.map((scorecard) => <li key={scorecard.id} className="flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5">
                        <div><strong>{scorecard.entry}</strong>{scorecard.delegation ? <p className="mt-0.5 text-sm text-muted">{scorecard.delegation}</p> : null}</div>
                        <div className="flex flex-wrap items-center justify-end gap-3">
                            <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${statusTone[scorecard.status] ?? statusTone.not_started}`}>{scorecard.status_label}</span>
                            {scorecard.href ? <Link href={scorecard.href} className="min-h-11 rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-primary-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{scorecard.status === 'not_started' ? 'Start' : 'Open'}</Link> : <span className="min-h-11 rounded-lg border border-border bg-surface-muted px-4 py-2.5 text-sm font-bold text-muted" aria-disabled="true">Unavailable until ready</span>}
                        </div>
                    </li>)}</ul>
                </section>;
            })}</div> : <div className="rounded-xl border border-dashed border-border bg-surface p-10 text-center"><h2 className="font-serif text-xl font-bold">No judging assignments yet</h2><p className="mt-2 text-sm text-muted">Your assigned contests and entries will appear here.</p></div>}
        </div></main>
    </AuthenticatedLayout>;
}
