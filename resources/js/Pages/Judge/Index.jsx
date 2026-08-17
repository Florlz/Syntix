import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

const statusTone = {
    approved: 'bg-primary/10 text-primary', submitted: 'bg-primary/10 text-primary',
    needs_correction: 'bg-danger-surface text-danger', blocked: 'bg-danger-surface text-danger',
    in_progress: 'bg-accent/20 text-foreground', not_started: 'bg-surface-muted text-muted',
};

export default function Index({ event, summary = {}, contests = [] }) {
    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.15em] text-primary">{event?.name ?? 'Scoring operations'}</p><h1 className="font-serif text-2xl font-bold">My Judging</h1></div>}>
        <Head title="My Judging" />
        <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8"><div className="mx-auto max-w-6xl space-y-6">
            <section aria-label="Judging summary" className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-3">
                {[['Assigned', summary.assigned ?? 0], ['Submitted', summary.submitted ?? 0], ['Needs correction', summary.needs_correction ?? 0]].map(([label, value]) => <div key={label} className="bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{label}</p><p className="mt-1 font-serif text-3xl font-bold tabular-nums">{value}</p></div>)}
            </section>
            {contests.length ? <div className="space-y-5">{contests.map((contest) => <section key={`${contest.name}-${contest.division}`} className="overflow-hidden rounded-xl border border-border bg-surface">
                <header className="flex flex-wrap items-end justify-between gap-4 border-b border-border p-5"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{contest.schedule?.venue?.name ?? 'Venue not scheduled yet'}</p><h2 className="mt-1 font-serif text-2xl font-bold">{contest.name}</h2><p className="mt-1 text-sm text-muted">{contest.scorecard_count} assigned entries</p></div><p className="text-sm font-semibold text-muted">{contest.counts.submitted + contest.counts.approved} / {contest.scorecard_count} submitted</p></header>
                {contest.readiness?.next_blocker ? <p className="border-b border-border bg-danger-surface px-5 py-3 text-sm font-semibold text-danger">{contest.readiness.next_blocker}</p> : null}
                <ul className="divide-y divide-border">{contest.scorecards.map((scorecard) => <li key={scorecard.href} className="flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5"><div><strong>{scorecard.entry}</strong>{scorecard.delegation ? <p className="mt-0.5 text-sm text-muted">{scorecard.delegation}</p> : null}</div><div className="flex items-center gap-3"><span className={`rounded-full px-2.5 py-1 text-xs font-bold ${statusTone[scorecard.status] ?? statusTone.not_started}`}>{scorecard.status_label}</span><Link href={scorecard.href} className="min-h-11 rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-primary-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{scorecard.status === 'not_started' ? 'Start' : 'Open'}</Link></div></li>)}</ul>
            </section>)}</div> : <div className="rounded-xl border border-dashed border-border bg-surface p-10 text-center"><h2 className="font-serif text-xl font-bold">No judging assignments yet</h2><p className="mt-2 text-sm text-muted">Your assigned contests and entries will appear here.</p></div>}
        </div></main>
    </AuthenticatedLayout>;
}
