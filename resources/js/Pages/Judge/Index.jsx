import React from 'react';
import LiveProgress from '@/Components/LiveDesk/LiveProgress';
import OperationalStatus from '@/Components/LiveDesk/OperationalStatus';
import ScheduleTime from '@/Components/LiveDesk/ScheduleTime';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function actionLabel(status) {
    return {
        not_started: 'Start scorecard',
        in_progress: 'Continue draft',
        needs_correction: 'Correct scorecard',
        submitted: 'Review submission',
        approved: 'View scorecard',
    }[status] ?? 'Open scorecard';
}

function statusTone(status) {
    if (status === 'needs_correction' || status === 'blocked') return 'danger';
    if (status === 'in_progress') return 'live';
    if (status === 'submitted' || status === 'approved') return 'ready';
    return 'neutral';
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
        return <span className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border bg-surface-muted px-4 text-sm font-bold text-muted" aria-disabled="true">Waiting for readiness</span>;
    }

    return <Link href={item.href} className="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface">{actionLabel(item.status)}</Link>;
}

function AssignmentCard({ item, compact = false }) {
    const venue = item.schedule?.venue;
    const tone = statusTone(item.status);
    const signal = tone === 'danger' ? 'border-l-danger' : tone === 'live' ? 'border-l-accent' : 'border-l-border';

    return (
        <article className={`rounded-xl border border-border border-l ${signal} bg-surface ${compact ? 'p-4' : 'p-4 sm:p-5'}`}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-start gap-2">
                        <OperationalStatus label={item.status_label} detail={item.blocker} tone={tone} />
                        <span className="pt-1 text-xs font-semibold text-muted">{item.competition} · {item.division}</span>
                    </div>
                    <h3 className="mt-3 text-lg font-bold leading-tight text-foreground">{item.contest}</h3>
                    <p className="mt-1 text-sm font-semibold text-foreground">{item.entry}</p>
                    {item.delegation ? <p className="mt-0.5 text-sm text-muted">{item.delegation}</p> : null}
                    <p className="mt-3 text-xs text-muted">{venue ? `${venue.name}${venue.location ? ` · ${venue.location}` : ''}` : 'Venue pending'}</p>
                </div>
                <ScorecardAction item={item}/>
            </div>
        </article>
    );
}

export default function Index({ event, summary = {}, contests = [] }) {
    const items = queueItems(contests);
    const attention = items.filter((item) => item.status === 'needs_correction' || item.status === 'blocked');
    const scheduled = items.filter((item) => !attention.includes(item));
    const assigned = summary.assigned ?? items.length;
    const completed = (summary.submitted ?? 0) + (summary.approved ?? 0);
    const attentionCount = (summary.needs_correction ?? 0) + (summary.blocked ?? 0);

    return (
        <AuthenticatedLayout header={<div><h1 className="text-xl font-bold text-foreground sm:text-2xl">My Judging</h1><p className="mt-0.5 text-xs font-semibold text-muted">{event?.name ?? 'Scoring operations'} · Judge desk</p></div>}>
            <Head title="My Judging"/>
            <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="grid gap-4 border-y border-border py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <LiveProgress label="Judging progress" value={completed} max={assigned} detail={`${completed} of ${assigned} complete`} />
                        <p className="text-sm text-muted"><span className="font-condensed text-lg font-bold tabular-nums text-foreground">{attentionCount}</span> need attention</p>
                    </div>

                    {attention.length ? (
                        <section aria-labelledby="judge-attention-title">
                            <div className="mb-4">
                                <h2 id="judge-attention-title" className="text-xl font-bold text-foreground sm:text-2xl">Needs attention</h2>
                                <p className="mt-1 text-sm text-muted">Correct these scorecards before continuing scheduled work.</p>
                            </div>
                            <div className="grid gap-3 lg:grid-cols-2">{attention.map((item) => <AssignmentCard key={item.id} item={item} compact/>)}</div>
                        </section>
                    ) : null}

                    <section aria-label="Today's judging schedule">
                        <div className="mb-4">
                            <h2 className="text-xl font-bold text-foreground sm:text-2xl">Your judging timeline</h2>
                            <p className="mt-1 text-sm text-muted">Scheduled work first, unscheduled work last.</p>
                        </div>
                        {scheduled.length ? (
                            <ol className="grid gap-3">
                                {scheduled.map((item) => (
                                    <li key={item.id} className="grid gap-2 sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-4">
                                        <div className="pt-2 sm:pt-4"><ScheduleTime startsAt={item.schedule?.starts_at} align="right" /></div>
                                        <AssignmentCard item={item}/>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <div className="rounded-xl border border-dashed border-border bg-surface px-6 py-10 text-center">
                                <h2 className="text-lg font-bold text-foreground">No judging assignments yet</h2>
                                <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted">Your Judge role is active. Assignments appear here after the Global Admin adds you to a judging panel.</p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
