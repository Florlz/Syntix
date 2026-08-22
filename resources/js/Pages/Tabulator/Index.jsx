import React from 'react';
import OperationalStatus from '@/Components/LiveDesk/OperationalStatus';
import ScheduleTime from '@/Components/LiveDesk/ScheduleTime';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function orderedItems(judged, objective) {
    return [...judged, ...objective].sort((left, right) => {
        const leftTime = left.schedule?.starts_at ? new Date(left.schedule.starts_at).getTime() : Number.MAX_SAFE_INTEGER;
        const rightTime = right.schedule?.starts_at ? new Date(right.schedule.starts_at).getTime() : Number.MAX_SAFE_INTEGER;

        return leftTime - rightTime || left.name.localeCompare(right.name);
    });
}

function operationalState(item) {
    if (item.mode === 'judged') {
        if (item.readiness?.ready) return { label: 'Ready to finalize', tone: 'live', urgent: true };
        if (item.readiness?.next_blocker) return { label: item.readiness.next_blocker, tone: 'danger', urgent: true };
        return { label: `${item.completion?.submitted ?? 0} of ${item.completion?.expected ?? 0} scorecards`, tone: 'neutral', urgent: false };
    }

    const urgent = ['live', 'completed'].includes(item.state);
    const tone = item.state === 'live' ? 'danger' : item.state === 'completed' ? 'live' : 'neutral';

    return { label: item.state_label ?? item.state ?? 'Scheduled', tone, urgent };
}

function WorkCard({ item, compact = false }) {
    const state = operationalState(item);
    const venue = item.schedule?.venue;
    const signal = state.tone === 'danger' ? 'border-l-danger' : state.tone === 'live' ? 'border-l-accent' : 'border-l-border';

    return (
        <article className={`rounded-xl border border-border border-l ${signal} bg-surface ${compact ? 'p-4' : 'p-4 sm:p-5'}`}>
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-start gap-2">
                        <OperationalStatus label={state.label} tone={state.tone}/>
                        <span className="pt-1 text-xs font-semibold text-muted">{item.mode === 'judged' ? 'Judged' : 'Objective'}</span>
                    </div>
                    <h3 className="mt-3 text-lg font-bold leading-tight text-foreground">{item.name}</h3>
                    <p className="mt-1 text-sm text-muted">{item.competition}{item.division ? ` · ${item.division}` : ''}</p>
                    <p className="mt-3 text-xs text-muted">{venue ? `${venue.name}${venue.location ? ` · ${venue.location}` : ''}` : 'Venue pending'}</p>
                </div>
                <Link href={item.href} className="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-surface">Open tabulation</Link>
            </div>
        </article>
    );
}

export default function Index({ event, summary = {}, judged = [], objective = [] }) {
    const items = orderedItems(judged, objective);
    const attention = items.filter((item) => operationalState(item).urgent);
    const scheduled = items.filter((item) => !attention.includes(item));
    const judgedCount = summary.judged ?? judged.length;
    const objectiveCount = summary.objective ?? objective.length;
    const assignedCount = judgedCount + objectiveCount;

    return (
        <AuthenticatedLayout header={<div><h1 className="text-xl font-bold text-foreground sm:text-2xl">My Tabulation</h1><p className="mt-0.5 text-xs font-semibold text-muted">{event?.name ?? 'Scoring operations'} · Tabulator desk</p></div>}>
            <Head title="My Tabulation"/>
            <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8">
                <div className="mx-auto max-w-6xl space-y-8">
                    <div className="flex flex-col gap-1 border-y border-border py-4 sm:flex-row sm:items-baseline sm:justify-between">
                        <p className="font-condensed text-xl font-bold tabular-nums text-foreground">{assignedCount} assignments</p>
                        <p className="text-sm font-semibold text-muted">{judgedCount} judged · {objectiveCount} objective</p>
                    </div>

                    {attention.length ? (
                        <section aria-labelledby="tabulator-attention-title">
                            <div className="mb-4">
                                <h2 id="tabulator-attention-title" className="text-xl font-bold text-foreground sm:text-2xl">Needs attention</h2>
                                <p className="mt-1 text-sm text-muted">Handle live contests, blockers, and finalization-ready results first.</p>
                            </div>
                            <div className="grid gap-3 lg:grid-cols-2">{attention.map((item) => <WorkCard key={`attention-${item.href}`} item={item} compact/>)}</div>
                        </section>
                    ) : null}

                    <section aria-label="Today's tabulation schedule">
                        <div className="mb-4">
                            <h2 className="text-xl font-bold text-foreground sm:text-2xl">Your tabulation timeline</h2>
                            <p className="mt-1 text-sm text-muted">Scheduled judged and objective work, with unscheduled assignments last.</p>
                        </div>
                        {scheduled.length ? (
                            <ol className="grid gap-3">
                                {scheduled.map((item) => (
                                    <li key={item.href} className="grid gap-2 sm:grid-cols-[7rem_minmax(0,1fr)] sm:gap-4">
                                        <div className="pt-2 sm:pt-4"><ScheduleTime startsAt={item.schedule?.starts_at} align="right"/></div>
                                        <WorkCard item={item}/>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <div className="rounded-xl border border-dashed border-border bg-surface px-6 py-10 text-center">
                                <h2 className="text-lg font-bold text-foreground">No scheduled assignments</h2>
                                <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted">New judged and objective work appears here after event organizers complete staffing and scheduling.</p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
