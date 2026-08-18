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

function scheduleTime(startsAt) {
    if (!startsAt) return 'Unscheduled';

    return new Intl.DateTimeFormat(undefined, {
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(startsAt));
}

function scheduleDate(startsAt) {
    if (!startsAt) return 'Date pending';

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    }).format(new Date(startsAt));
}

function actionLabel(status) {
    return {
        not_started: 'Start scorecard',
        in_progress: 'Continue draft',
        needs_correction: 'Correct scorecard',
        submitted: 'Review submission',
        approved: 'View scorecard',
    }[status] ?? 'Open scorecard';
}

function queueItems(contests) {
    return contests.flatMap((contest) => contest.scorecards.map((scorecard) => ({
        ...scorecard,
        contest: contest.name,
        competition: contest.competition,
        division: contest.division,
        schedule: contest.schedule,
        blocker: contest.readiness?.next_blocker,
    }))).sort((left, right) => {
        const leftTime = left.schedule?.starts_at ? new Date(left.schedule.starts_at).getTime() : Number.MAX_SAFE_INTEGER;
        const rightTime = right.schedule?.starts_at ? new Date(right.schedule.starts_at).getTime() : Number.MAX_SAFE_INTEGER;

        return leftTime - rightTime || left.contest.localeCompare(right.contest) || left.entry.localeCompare(right.entry);
    });
}

function ScorecardAction({ item }) {
    if (!item.href) {
        return <span className="inline-flex min-h-11 items-center rounded-lg border border-border bg-surface-muted px-4 text-sm font-bold text-muted" aria-disabled="true">Waiting for readiness</span>;
    }

    return <Link href={item.href} className="inline-flex min-h-11 items-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">{actionLabel(item.status)}</Link>;
}

function AssignmentCard({ item, compact = false }) {
    const venue = item.schedule?.venue;

    return <article className={`rounded-xl border bg-surface ${item.status === 'needs_correction' || item.status === 'blocked' ? 'border-danger/40' : 'border-border'} ${compact ? 'p-4' : 'p-5'}`}>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${statusTone[item.status] ?? statusTone.not_started}`}>{item.status_label}</span>
                    <span className="text-xs font-semibold text-muted">{item.competition} · {item.division}</span>
                </div>
                <h3 className="mt-2 font-serif text-xl font-bold">{item.contest}</h3>
                <p className="mt-1 text-sm font-semibold">{item.entry}</p>
                {item.delegation ? <p className="mt-0.5 text-sm text-muted">{item.delegation}</p> : null}
                <p className="mt-2 text-xs text-muted">{venue ? `${venue.name}${venue.location ? ` · ${venue.location}` : ''}` : 'Venue pending'}</p>
                {item.blocker ? <p className="mt-2 text-sm font-semibold text-danger">{item.blocker}</p> : null}
            </div>
            <ScorecardAction item={item}/>
        </div>
    </article>;
}

export default function Index({ event, summary = {}, contests = [] }) {
    const items = queueItems(contests);
    const attention = items.filter((item) => item.status === 'needs_correction' || item.status === 'blocked');
    const scheduled = items.filter((item) => !attention.includes(item));

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.15em] text-primary">{event?.name ?? 'Scoring operations'} · Judge</p><h1 className="font-serif text-2xl font-bold">My Judging</h1></div>}>
        <Head title="My Judging"/>
        <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8">
            <div className="mx-auto max-w-6xl space-y-7">
                <section aria-label="Judging summary" className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Assigned', summary.assigned ?? items.length],
                        ['Submitted', summary.submitted ?? 0],
                        ['Needs attention', (summary.needs_correction ?? 0) + (summary.blocked ?? 0)],
                    ].map(([label, value]) => <div key={label} className="rounded-xl border border-border bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{label}</p><p className="mt-1 font-serif text-3xl font-bold tabular-nums">{value}</p></div>)}
                </section>

                {attention.length ? <section aria-labelledby="judge-attention-title">
                    <div className="mb-3"><p className="text-xs font-bold uppercase tracking-[0.14em] text-danger">Act first</p><h2 id="judge-attention-title" className="mt-1 font-serif text-2xl font-bold">Needs attention</h2></div>
                    <div className="grid gap-3 lg:grid-cols-2">{attention.map((item) => <AssignmentCard key={item.id} item={item} compact/>)}</div>
                </section> : null}

                <section aria-label="Today's judging schedule">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Schedule</p><h2 className="mt-1 font-serif text-2xl font-bold">Your judging timeline</h2></div><p className="text-sm text-muted">Scheduled work first · unscheduled work last</p></div>
                    {scheduled.length ? <ol className="space-y-3">{scheduled.map((item) => <li key={item.id} className="grid gap-2 sm:grid-cols-[7rem_1fr] sm:gap-4">
                        <div className="pt-3 sm:text-right"><p className="text-sm font-bold tabular-nums">{scheduleTime(item.schedule?.starts_at)}</p><p className="mt-1 text-xs text-muted">{scheduleDate(item.schedule?.starts_at)}</p></div>
                        <AssignmentCard item={item}/>
                    </li>)}</ol> : <div className="rounded-xl border border-dashed border-border bg-surface p-10 text-center"><h2 className="font-serif text-xl font-bold">No judging assignments yet</h2><p className="mt-2 text-sm text-muted">Your role is active. Assigned contests appear here after the Global Admin adds you to a judging panel.</p></div>}
                </section>
            </div>
        </main>
    </AuthenticatedLayout>;
}
