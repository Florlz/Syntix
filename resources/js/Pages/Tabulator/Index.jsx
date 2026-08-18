import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

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

function orderedItems(judged, objective) {
    return [...judged, ...objective].sort((left, right) => {
        const leftTime = left.schedule?.starts_at ? new Date(left.schedule.starts_at).getTime() : Number.MAX_SAFE_INTEGER;
        const rightTime = right.schedule?.starts_at ? new Date(right.schedule.starts_at).getTime() : Number.MAX_SAFE_INTEGER;

        return leftTime - rightTime || left.name.localeCompare(right.name);
    });
}

function operationalState(item) {
    if (item.mode === 'judged') {
        if (item.readiness?.ready) return { label: 'Ready to finalize', tone: 'bg-accent/20 text-foreground', urgent: true };
        if (item.readiness?.next_blocker) return { label: item.readiness.next_blocker, tone: 'bg-danger-surface text-danger', urgent: true };
        return { label: `${item.completion?.submitted ?? 0} / ${item.completion?.expected ?? 0} Judge scorecards`, tone: 'bg-surface-muted text-muted', urgent: false };
    }

    const urgent = ['live', 'completed'].includes(item.state);
    const tone = item.state === 'live'
        ? 'bg-danger-surface text-danger'
        : item.state === 'completed'
            ? 'bg-accent/20 text-foreground'
            : 'bg-surface-muted text-muted';

    return { label: item.state_label ?? item.state ?? 'Scheduled', tone, urgent };
}

function WorkCard({ item, compact = false }) {
    const state = operationalState(item);
    const venue = item.schedule?.venue;

    return <article className={`rounded-xl border border-border bg-surface ${compact ? 'p-4' : 'p-5'}`}>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold capitalize text-primary">{item.mode}</span>
                    <span className={`rounded-full px-2.5 py-1 text-xs font-bold ${state.tone}`}>{state.label}</span>
                </div>
                <h3 className="mt-2 font-serif text-xl font-bold">{item.name}</h3>
                <p className="mt-1 text-sm text-muted">{item.competition}{item.division ? ` · ${item.division}` : ''}</p>
                <p className="mt-2 text-xs text-muted">{venue ? `${venue.name}${venue.location ? ` · ${venue.location}` : ''}` : 'Venue pending'}</p>
            </div>
            <Link href={item.href} className="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Open tabulation</Link>
        </div>
    </article>;
}

export default function Index({ event, summary = {}, judged = [], objective = [] }) {
    const items = orderedItems(judged, objective);
    const attention = items.filter((item) => operationalState(item).urgent);

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.15em] text-primary">{event?.name ?? 'Scoring operations'} · Tabulator</p><h1 className="font-serif text-2xl font-bold">My Tabulation</h1></div>}>
        <Head title="My Tabulation"/>
        <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8">
            <div className="mx-auto max-w-6xl space-y-7">
                <section aria-label="Tabulation summary" className="grid gap-3 sm:grid-cols-3">
                    {[
                        ['Assigned', (summary.judged ?? judged.length) + (summary.objective ?? objective.length)],
                        ['Judged', summary.judged ?? judged.length],
                        ['Objective', summary.objective ?? objective.length],
                    ].map(([label, value]) => <div key={label} className="rounded-xl border border-border bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{label}</p><p className="mt-1 font-serif text-3xl font-bold tabular-nums">{value}</p></div>)}
                </section>

                {attention.length ? <section aria-labelledby="tabulator-attention-title">
                    <div className="mb-3"><p className="text-xs font-bold uppercase tracking-[0.14em] text-danger">Act first</p><h2 id="tabulator-attention-title" className="mt-1 font-serif text-2xl font-bold">Needs attention</h2></div>
                    <div className="grid gap-3 lg:grid-cols-2">{attention.map((item) => <WorkCard key={`attention-${item.href}`} item={item} compact/>)}</div>
                </section> : null}

                <section aria-label="Today's tabulation schedule">
                    <div className="mb-4 flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Schedule</p><h2 className="mt-1 font-serif text-2xl font-bold">Your tabulation timeline</h2></div><p className="text-sm text-muted">Judged and objective work in one timeline</p></div>
                    {items.length ? <ol className="space-y-3">{items.map((item) => <li key={item.href} className="grid gap-2 sm:grid-cols-[7rem_1fr] sm:gap-4">
                        <div className="pt-3 sm:text-right"><p className="text-sm font-bold tabular-nums">{scheduleTime(item.schedule?.starts_at)}</p><p className="mt-1 text-xs text-muted">{scheduleDate(item.schedule?.starts_at)}</p></div>
                        <WorkCard item={item}/>
                    </li>)}</ol> : <div className="rounded-xl border border-dashed border-border bg-surface p-10 text-center"><h2 className="font-serif text-xl font-bold">No tabulation assignments yet</h2><p className="mt-2 text-sm text-muted">Your role is active. Assigned divisions and contests appear here after the Global Admin completes staffing.</p></div>}
                </section>
            </div>
        </main>
    </AuthenticatedLayout>;
}
