import React from 'react';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';

const panel = 'rounded-2xl border border-[#D8DEDC] bg-white shadow-[0_10px_28px_rgba(23,33,43,0.05)]';

const statusStyles = {
    good: 'bg-emerald-50 text-emerald-800',
    warning: 'bg-amber-50 text-amber-900',
    urgent: 'bg-rose-50 text-rose-800',
    neutral: 'bg-slate-100 text-slate-700',
};

function Empty({ title, detail, href, action }) {
    return <div className="rounded-xl border border-dashed border-[#C8D0CD] bg-white p-7"><h3 className="font-serif text-lg font-bold">{title}</h3><p className="mt-2 text-sm text-[#68767E]">{detail}</p>{href ? <Link href={href} className="mt-4 inline-flex text-sm font-bold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4">{action}</Link> : null}</div>;
}

function StatusPill({ tone = 'neutral', children }) {
    return <span className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] ${statusStyles[tone] ?? statusStyles.neutral}`}>{children}</span>;
}

function EventSelector({ events, event }) {
    if (!events.length) return null;

    return <label className="block w-full sm:w-auto sm:min-w-44"><span className="sr-only">Switch event</span><select aria-label="Switch event" value={event.id} onChange={(input) => router.get(route('dashboard'), { event: input.target.value }, { preserveScroll: true, replace: true })} className="w-full rounded-lg border-white/25 bg-white/10 text-sm font-semibold text-white focus:border-[#E7C865] focus:ring-[#E7C865] [&>option]:text-[#17212B]"><option value={event.id}>{event.name}</option>{events.filter((item) => item.id !== event.id).map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label>;
}

function eventStateLabel(state) {
    return {
        preparation: 'Getting ready',
        configuration: 'Getting ready',
        live: 'Live',
        closed: 'Finished',
        archived: 'Archived',
    }[state] ?? 'In progress';
}

function eventStateTone(state) {
    if (state === 'live') return 'good';
    if (state === 'closed' || state === 'archived') return 'neutral';
    return 'warning';
}

function AttentionRail({ summary }) {
    const pendingResults = (summary.pending_results || 0) + (summary.pending_placements || 0);
    const items = [];

    if ((summary.divisions || 0) === 0) {
        items.push({ icon: 'trophy', text: 'Add your first sport or activity to get started.' });
    } else if ((summary.blocked_divisions || 0) > 0) {
        items.push({ icon: 'trophy', text: `${summary.blocked_divisions} ${summary.blocked_divisions === 1 ? 'activity still needs' : 'activities still need'} setup.` });
    }

    if ((summary.event_staff || 0) === 0) {
        items.push({ icon: 'badge', text: 'Add event staff to share the work.' });
    } else if ((summary.unassigned_staff || 0) > 0) {
        items.push({ icon: 'badge', text: `${summary.unassigned_staff} event staff ${summary.unassigned_staff === 1 ? 'still needs' : 'still need'} a task.` });
    }

    if (pendingResults > 0) {
        items.push({ icon: 'clipboard-check', text: `${pendingResults} ${pendingResults === 1 ? 'result is' : 'results are'} ready for review.` });
    }

    return <section aria-labelledby="attention-title" className="relative overflow-hidden rounded-xl border border-[#E7C865]/35 bg-[#0B2E4F]/70 p-4 sm:p-5">
        <div className="absolute inset-y-0 right-0 w-1/3 bg-[radial-gradient(circle_at_center,rgba(213,162,31,0.16),transparent_68%)]" aria-hidden="true" />
        <div className="relative flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-5">
            <div className="flex items-center gap-2 text-[#E7C865]"><span className="grid size-7 shrink-0 place-items-center rounded-full bg-[#D5A21F]/20"><AppIcon name={items.length ? 'warning' : 'check'} className="size-4" /></span><h2 id="attention-title" className="text-xs font-bold uppercase tracking-[0.14em]">What needs attention</h2></div>
            <div className="relative min-w-0 flex-1 text-sm leading-6 text-white/80">{items.length ? <ul className="space-y-1.5">{items.map((item) => <li key={item.text} className="flex items-start gap-2"><AppIcon name={item.icon} className="mt-1 size-4 shrink-0 text-[#E7C865]" /><span>{item.text}</span></li>)}</ul> : <p>Your event workspace is up to date.</p>}</div>
        </div>
    </section>;
}

function AtAGlance({ summary, departmentCount }) {
    const schedules = summary.schedules || 0;
    const publishedSchedules = summary.published_schedules || 0;
    const pendingResults = (summary.pending_results || 0) + (summary.pending_placements || 0);

    const metrics = [
        { label: 'Sports', value: summary.competitions || 0 },
        { label: 'Activities', value: summary.divisions || 0 },
        { label: 'Departments', value: departmentCount },
        { label: 'Players', value: summary.participants || 0 },
        { label: 'Schedule ready', value: schedules ? `${publishedSchedules}/${schedules}` : '0' },
        { label: 'Results to review', value: pendingResults },
    ];

    return <section aria-label="Event at a glance" className={`${panel} overflow-hidden`}><dl className="grid grid-cols-2 divide-x divide-y divide-[#E6EAE8] sm:grid-cols-3 lg:grid-cols-6 lg:divide-y-0">{metrics.map((metric) => <div key={metric.label} className="min-w-0 px-4 py-4 sm:px-5"><dt className="truncate text-[0.62rem] font-bold uppercase tracking-[0.1em] text-[#78858A]">{metric.label}</dt><dd className="mt-1 font-mono text-2xl font-bold tabular-nums text-[#0B2E4F]">{metric.value}</dd></div>)}</dl></section>;
}

function EventMasthead({ event, events, summary, departmentCount }) {
    return <section className="relative overflow-hidden rounded-2xl bg-[#0B2E4F] px-5 py-7 text-white shadow-[0_16px_34px_rgba(11,46,79,0.18)] sm:px-8 sm:py-9">
        <div className="absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,rgba(213,162,31,0.2),transparent_58%)]" aria-hidden="true" />
        <div className="relative space-y-6">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0 max-w-3xl"><p className="text-xs font-bold uppercase tracking-[0.16em] text-[#E7C865]">Event workspace</p><h2 className="mt-2 break-words font-serif text-3xl font-bold sm:text-4xl">{event.name}</h2><p className="mt-3 max-w-2xl text-sm leading-6 text-white/75">{event.name} has {summary.competitions || 0} sports and {summary.divisions || 0} activities, {departmentCount} departments, and {summary.participants || 0} registered players.</p></div>
                <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center"><EventSelector events={events} event={event}/><StatusPill tone={eventStateTone(event.state)}>{eventStateLabel(event.state)}</StatusPill></div>
            </div>
            <AttentionRail summary={summary}/>
        </div>
    </section>;
}

function WorkspaceCard({ icon, title, detail, status, tone, value, valueLabel, action, href, span = 'regular' }) {
    const spanClass = span === 'featured' ? 'lg:col-span-4 border-t-4 border-t-[#D5A21F] sm:p-6' : 'lg:col-span-2';

    return <Link href={href} className={`${panel} group flex min-w-0 flex-col p-5 transition duration-200 hover:-translate-y-0.5 hover:border-[#0B536D] hover:shadow-[0_18px_38px_rgba(23,33,43,0.09)] motion-reduce:transform-none motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 ${spanClass}`}>
        <div className="flex items-start justify-between gap-4"><div className="flex min-w-0 items-start gap-3"><span className="grid size-11 shrink-0 place-items-center rounded-xl bg-[#EAF1F5] text-[#0B536D] transition group-hover:bg-[#0B536D] group-hover:text-white motion-reduce:transition-none"><AppIcon name={icon} className="size-5" /></span><div className="min-w-0"><h3 className="font-serif text-xl font-bold leading-tight text-[#17212B]">{title}</h3><p className="mt-1 text-sm leading-5 text-[#68767E]">{detail}</p></div></div><StatusPill tone={tone}>{status}</StatusPill></div>
        <div className="mt-auto flex items-end justify-between gap-4 border-t border-[#E6EAE8] pt-5"><p className="min-w-0"><span className="font-mono text-3xl font-bold tabular-nums text-[#0B2E4F]">{value}</span><span className="ml-2 text-xs font-semibold uppercase tracking-[0.08em] text-[#68767E]">{valueLabel}</span></p><span className="inline-flex shrink-0 items-center gap-1 text-sm font-bold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4">{action}<AppIcon name="arrow-right" className="size-4 no-underline" /></span></div>
    </Link>;
}

function WorkspaceCards({ event, summary, departmentCount }) {
    const pendingResults = (summary.pending_results || 0) + (summary.pending_placements || 0);
    const schedules = summary.schedules || 0;
    const publishedSchedules = summary.published_schedules || 0;
    const sportsStatus = (summary.blocked_divisions || 0) > 0 ? { label: 'Needs setup', tone: 'warning' } : { label: 'Ready', tone: 'good' };
    const departmentStatus = departmentCount > 0 ? { label: 'Ready', tone: 'good' } : { label: 'Get started', tone: 'warning' };
    const staffStatus = (summary.event_staff || 0) === 0 || (summary.unassigned_staff || 0) > 0 ? { label: 'Needs tasks', tone: 'warning' } : { label: 'Ready', tone: 'good' };
    const resultsStatus = pendingResults > 0 ? { label: 'Review', tone: 'urgent' } : { label: 'All clear', tone: 'neutral' };
    const scheduleStatus = schedules === 0 ? { label: 'Get started', tone: 'warning' } : publishedSchedules === schedules ? { label: 'Ready', tone: 'good' } : { label: 'In progress', tone: 'warning' };

    return <section aria-labelledby="workspace-title"><div className="mb-4 flex flex-wrap items-end justify-between gap-3"><div><p className="text-[0.68rem] font-bold uppercase tracking-[0.15em] text-[#0B536D]">Your event toolkit</p><h2 id="workspace-title" className="mt-1 font-serif text-2xl font-bold text-[#17212B]">Pick up where you left off</h2></div><p className="text-sm text-[#68767E]">Five simple places to keep things moving.</p></div><div className="grid auto-rows-fr gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <WorkspaceCard span="featured" icon="trophy" title="Sports & Activities" detail="Set up the sports, divisions, and activities your event will run." value={summary.divisions || 0} valueLabel="activities" status={sportsStatus.label} tone={sportsStatus.tone} action="Open activities" href={route('admin.sports.index', event.id)}/>
        <WorkspaceCard icon="users" title="Departments & Teams" detail="Keep each department's players, coaches, and team sheets together." value={departmentCount} valueLabel="departments" status={departmentStatus.label} tone={departmentStatus.tone} action="Manage teams" href={route('admin.departments.index', event.id)}/>
        <WorkspaceCard icon="calendar" title="Schedule" detail="Build a clear day-by-day schedule for everyone to follow." value={schedules ? `${publishedSchedules}/${schedules}` : '0'} valueLabel="ready" status={scheduleStatus.label} tone={scheduleStatus.tone} action="Open schedule" href={route('admin.sports.schedules', event.id)}/>
        <WorkspaceCard icon="badge" title="Event Staff" detail="Bring your event staff together and make sure everyone has a task." value={summary.event_staff || 0} valueLabel="people" status={staffStatus.label} tone={staffStatus.tone} action="Manage event staff" href={route('admin.staff.index', event.id)}/>
        <WorkspaceCard icon="clipboard-check" title="Results" detail="Review results as they come in and keep the event moving." value={pendingResults} valueLabel="to review" status={resultsStatus.label} tone={resultsStatus.tone} action="Review results" href={route('admin.approvals.index', event.id)}/>
    </div></section>;
}

function WorkerDashboard({ event, workQueue }) {
    return <AuthenticatedLayout header={<h1 className="font-serif text-xl font-bold">My scoring assignments</h1>}><Head title="My scoring assignments"/><main className="p-4 sm:p-7"><div className="mx-auto max-w-5xl"><p className="text-sm text-[#68767E]">{event.name} · access is limited to the assignments below.</p><div className="mt-5 grid gap-3 sm:grid-cols-2">{workQueue.length ? workQueue.map((work) => <article key={work.id} className={`${panel} p-5`}><p className="text-xs font-bold uppercase text-[#0B536D]">{work.scope.replaceAll('_', ' ')}</p><h2 className="mt-2 font-serif text-lg font-bold">{work.label}</h2>{work.url ? <Link href={work.url} className="mt-4 inline-flex rounded-lg bg-[#0B536D] px-4 py-2 text-sm font-bold text-white">Open assignment</Link> : null}</article>) : <Empty title="No assignments yet" detail="Your role is active, but an administrator has not assigned a scoring target."/>}</div></div></main></AuthenticatedLayout>;
}

export default function Dashboard({ events = [], event, summary = {}, teams = [], work_queue: workQueue = [], capabilities = {} }) {
    const flash = usePage().props.flash?.status;
    const departmentCount = teams.length;

    if (event && !capabilities.global_admin) return <WorkerDashboard event={event} workQueue={workQueue}/>;

    return <AuthenticatedLayout header={<div><p className="text-[0.68rem] font-bold uppercase tracking-[0.15em] text-[#0B536D]">{event?.name ?? 'Syntix events'}</p><h1 className="font-serif text-2xl font-bold">Event home</h1></div>}><Head title="Event home"/><main className="min-h-[calc(100vh-4rem)] overflow-x-hidden p-4 sm:p-7 lg:p-8"><div className="mx-auto max-w-[96rem] space-y-6">
        {flash ? <div role="status" className="border-l-4 border-[#16845B] bg-emerald-50 p-4 text-sm text-emerald-900">{flash}</div> : null}
        {!event ? <Empty title="Create the first event" detail="Start an event workspace, add sports and activities, then invite event staff." href={route('admin.events.create')} action="Create event"/> : <>
            <EventMasthead event={event} events={events} summary={summary} departmentCount={departmentCount}/>
            <AtAGlance summary={summary} departmentCount={departmentCount}/>
            <WorkspaceCards event={event} summary={summary} departmentCount={departmentCount}/>
        </>}
    </div></main></AuthenticatedLayout>;
}
