import React from 'react';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import Rosters from './Rosters';
import { getSportArtwork } from '@/Support/sportArtwork';

const panel = 'rounded-2xl border border-[#DDE2E0] bg-white shadow-[0_8px_24px_rgba(17,38,51,0.05)]';
const primary = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#0B536D] px-4 text-sm font-bold text-white transition hover:bg-[#083e52] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transition-none';
const quiet = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[#B8C3C0] bg-white px-4 text-sm font-bold text-[#0B536D] transition hover:border-[#0B536D] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transition-none';

/**
 * Build a sport hub URL. Legacy tab arguments are accepted for callers that
 * still pass them, but only the focused roster editor keeps a tab parameter.
 * Normal hub URLs are intentionally just the canonical route plus division.
 */
export function workspaceUrl(eventId, sportId, tabOrOptions = null, divisionId = null, departmentId = null) {
    let tab = null;
    let division = divisionId;
    let department = departmentId;

    if (tabOrOptions && typeof tabOrOptions === 'object') {
        tab = tabOrOptions.tab ?? null;
        division = tabOrOptions.division ?? division;
        department = tabOrOptions.department ?? department;
    } else if (['overview', 'rosters', 'matches', 'schedule', 'results'].includes(tabOrOptions)) {
        tab = tabOrOptions;
    } else if (tabOrOptions !== null && tabOrOptions !== undefined && tabOrOptions !== '') {
        division = tabOrOptions;
    }

    const base = route('admin.sports.show', [eventId, sportId]);
    const params = new URLSearchParams();
    if (tab === 'rosters') params.set('tab', 'rosters');
    if (division) params.set('division', division);
    if (tab === 'rosters' && department) params.set('department', department);
    const query = params.toString();
    return query ? `${base}?${query}` : base;
}

function humanize(value, fallback = 'Not started') {
    if (!value) return fallback;
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function statusClass(value) {
    const text = String(value || '').toLowerCase();
    if (['published', 'complete', 'ready', 'uncontested'].includes(text)) return 'text-emerald-700';
    if (['draft', 'preview', 'not_generated', 'not_scheduled', 'missing'].includes(text)) return 'text-amber-800';
    return 'text-[#17212B]';
}

function divisionHref(event, sport, division = null) {
    return workspaceUrl(event.id, sport.id, { division: division?.id ?? division ?? null });
}

function SportHeader({ event, sport, divisions, selectedDivision }) {
    const artwork = sport.cover?.url ? sport.cover : getSportArtwork(sport.name);

    return <>
        <div className="mb-4 flex flex-wrap items-center gap-2 text-sm text-[#68767E]">
            <Link href={route('admin.sports.index', event.id)} className="font-semibold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4">Sports Directory</Link>
            <span aria-hidden="true">/</span>
            <span>{sport.name}</span>
        </div>
        <section className={`${panel} relative overflow-hidden bg-[#0B2E4F] text-white`}>
            <div className="absolute inset-0" aria-hidden="true">
                {artwork?.url
                    ? <img src={artwork.url} alt="" style={{ objectPosition: artwork.position || 'center' }} onError={(eventObject) => { eventObject.currentTarget.style.display = 'none'; }} className="h-full w-full object-cover opacity-45" />
                    : <div className="h-full w-full bg-[radial-gradient(circle_at_top_right,#17647a,#082944_65%)]" />}
                <div className="absolute inset-0 bg-gradient-to-r from-[#071E33]/95 via-[#0B2E4F]/70 to-[#0B2E4F]/25" />
            </div>
            <div className="relative flex min-h-40 flex-col justify-center gap-6 p-5 sm:p-7 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-[0.65rem] font-bold uppercase text-emerald-800">{sport.active ? 'Active' : 'Inactive'}</span>
                        <span className="text-sm text-white/75">{sport.division_count} divisions <span aria-hidden="true">/</span> {sport.entry_count} entries <span aria-hidden="true">/</span> {sport.player_count} players</span>
                    </div>
                    <h1 className="mt-2 font-serif text-4xl font-bold tracking-tight sm:text-5xl">{sport.name}</h1>
                    <p className="mt-2 max-w-xl text-sm text-white/70">One place to keep divisions moving from team sheets to game day.</p>
                </div>
                <label className="flex flex-col gap-2 text-xs font-bold uppercase tracking-[0.12em] text-white/75 sm:flex-row sm:items-center sm:gap-3">
                    <span>View division</span>
                    <select aria-label="Select division" value={selectedDivision ?? ''} onChange={(eventObject) => router.get(workspaceUrl(event.id, sport.id, { division: eventObject.target.value || null }), {}, { preserveScroll: true })} className="min-h-11 min-w-52 rounded-xl border-white/20 bg-white px-3 text-sm font-semibold normal-case tracking-normal text-[#17212B] focus:border-[#D5A21F] focus:outline-none focus:ring-2 focus:ring-[#D5A21F]">
                        <option value="">All divisions</option>
                        {divisions.map((division) => <option key={division.id} value={division.id}>{division.name}</option>)}
                    </select>
                </label>
            </div>
        </section>
    </>;
}

function Metric({ label, value, tone = '' }) {
    return <div className="min-w-0 border-t border-[#E6EAE8] pt-3 sm:border-t-0 sm:border-l sm:pl-4 sm:first:border-l-0 sm:first:pl-0">
        <p className="text-[0.65rem] font-bold uppercase tracking-[0.1em] text-[#78858A]">{label}</p>
        <p className={`mt-1 truncate text-sm font-bold ${tone}`}>{value}</p>
    </div>;
}

function AttentionSummary({ divisions }) {
    const pendingRosters = divisions.reduce((total, division) => total + (division.unlocked_entry_count || 0), 0);
    const withBracketsToSet = divisions.filter((division) => ['not_generated', 'missing'].includes(division.bracket_state));
    const draftSchedules = divisions.filter((division) => division.schedule_state === 'draft');

    let title = 'You are all caught up';
    let detail = 'Use a division card below whenever you are ready to work on a team, bracket, or schedule.';
    let tone = 'border-[#BFE2D0] bg-[#F1FBF5] text-emerald-900';
    if (pendingRosters) {
        title = `${pendingRosters} team sheet${pendingRosters === 1 ? '' : 's'} still in progress`;
        detail = 'Open a division to finish teams and players before game day.';
        tone = 'border-[#E7D39A] bg-[#FFF9E8] text-[#6D5300]';
    } else if (withBracketsToSet.length) {
        title = `${withBracketsToSet.length} division${withBracketsToSet.length === 1 ? '' : 's'} ready for a bracket`;
        detail = 'Open a division when its teams are ready to set up the draw.';
        tone = 'border-[#E7D39A] bg-[#FFF9E8] text-[#6D5300]';
    } else if (draftSchedules.length) {
        title = `${draftSchedules.length} schedule${draftSchedules.length === 1 ? '' : 's'} not shared yet`;
        detail = 'Review the schedule when you are ready to share game times publicly.';
        tone = 'border-[#E7D39A] bg-[#FFF9E8] text-[#6D5300]';
    }

    return <section className={`rounded-2xl border px-5 py-4 sm:px-6 ${tone}`}>
        <div className="flex items-start gap-3"><span className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full bg-white/75"><AppIcon name={pendingRosters || withBracketsToSet.length || draftSchedules.length ? 'warning' : 'check'} className="size-4" /></span><div><p className="text-xs font-bold uppercase tracking-[0.14em] opacity-75">Next up</p><h2 className="mt-1 font-serif text-xl font-bold">{title}</h2><p className="mt-1 text-sm opacity-80">{detail}</p></div></div>
    </section>;
}

function DivisionCard({ event, sport, division }) {
    const roster = `${division.locked_entry_count || 0}/${division.entry_count || 0} ready`;
    return <article className={`${panel} group overflow-hidden transition duration-200 hover:-translate-y-0.5 hover:border-[#9EB4B0] hover:shadow-[0_14px_30px_rgba(17,38,51,0.09)] motion-reduce:transform-none`}>
        <div className="h-1.5 bg-[#D5A21F]" aria-hidden="true" />
        <div className="p-5 sm:p-6">
            <div className="flex items-start justify-between gap-4">
                <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Division</p><h3 className="mt-1 font-serif text-2xl font-bold text-[#17212B]">{division.name}</h3></div>
                <span className={`rounded-full bg-[#F2F6F4] px-2.5 py-1 text-[0.62rem] font-bold uppercase ${division.active ? 'text-emerald-700' : 'text-[#68767E]'}`}>{division.active ? 'Active' : 'Inactive'}</span>
            </div>
            <p className="mt-2 text-sm text-[#68767E]">{division.entry_count || 0} teams <span aria-hidden="true">/</span> {division.player_count || 0} players</p>
            <div className="mt-5 grid grid-cols-2 gap-4 border-y border-[#E6EAE8] py-4 sm:grid-cols-4 sm:gap-3">
                <Metric label="Team sheets" value={roster} />
                <Metric label="Bracket" value={humanize(division.bracket_state)} tone={statusClass(division.bracket_state)} />
                <Metric label="Schedule" value={humanize(division.schedule_state)} tone={statusClass(division.schedule_state)} />
                <Metric label="Next" value={division.next_schedule?.title || 'Not scheduled'} />
            </div>
            <Link href={divisionHref(event, sport, division)} className={`${primary} mt-5 w-full sm:w-auto`}>Open {division.name} <AppIcon name="arrow-right" className="size-4" /></Link>
        </div>
    </article>;
}

function NextActivity({ event, sport, divisions }) {
    const next = divisions.map((division) => division.next_schedule ? { ...division.next_schedule, division: division.name, id: division.id } : null)
        .filter(Boolean)
        .sort((a, b) => new Date(a.starts_at) - new Date(b.starts_at))[0];
    if (!next) return <section className={`${panel} p-5 sm:p-6`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Next activity</p><h2 className="mt-2 font-serif text-2xl font-bold">No games scheduled yet</h2><p className="mt-2 text-sm leading-6 text-[#68767E]">When a time is set, it will appear here with its division and venue.</p><Link href={route('admin.sports.schedules', { event: event.id, competition: sport.id })} className={`${quiet} mt-5`}>Open event schedule <AppIcon name="calendar" className="size-4" /></Link></section>;
    const startsAt = next.starts_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(next.starts_at)) : 'Time to be announced';
    return <section className={`${panel} p-5 sm:p-6`}><div className="flex items-start gap-3"><span className="grid size-10 shrink-0 place-items-center rounded-xl bg-[#EAF4F4] text-[#0B536D]"><AppIcon name="calendar" /></span><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Next activity</p><h2 className="mt-1 font-serif text-2xl font-bold">{next.title}</h2><p className="mt-1 text-sm text-[#68767E]">{next.division} <span aria-hidden="true">/</span> {startsAt}</p><p className="mt-1 text-sm text-[#68767E]">{next.venue || 'Venue to be announced'}</p></div></div><Link href={route('admin.sports.schedules', { event: event.id, competition: sport.id, division: next.id })} className={`${quiet} mt-5`}>Open schedule <AppIcon name="arrow-right" className="size-4" /></Link></section>;
}

function AllDivisionsHub({ event, sport, divisions }) {
    return <div className="space-y-5">
        <AttentionSummary divisions={divisions} />
        <div className="grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)]">
            <section className={`${panel} overflow-hidden`} aria-labelledby="division-overview-title">
                <div className="flex flex-wrap items-end justify-between gap-3 border-b border-[#E6EAE8] p-5 sm:p-6"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Sport overview</p><h2 id="division-overview-title" className="mt-1 font-serif text-2xl font-bold">Choose a division</h2><p className="mt-1 text-sm text-[#68767E]">Each card keeps teams, players, brackets, and game times together.</p></div><Link href={route('admin.departments.index', event.id)} className={quiet}>Teams &amp; rosters</Link></div>
                <div className="grid gap-4 p-4 sm:p-5 xl:grid-cols-2">{divisions.length ? divisions.map((division) => <DivisionCard key={division.id} event={event} sport={sport} division={division} />) : <div className="rounded-xl border border-dashed border-[#C4CECB] bg-[#FAFBF9] p-8 text-center sm:col-span-2"><h3 className="font-serif text-xl font-bold">No divisions configured yet</h3><p className="mt-2 text-sm text-[#68767E]">Add a division in sport settings before setting up teams and games.</p></div>}</div>
            </section>
            <NextActivity event={event} sport={sport} divisions={divisions} />
        </div>
    </div>;
}

function ActionCard({ href, icon, label, description, tone = 'teal' }) {
    const iconClass = tone === 'gold' ? 'bg-[#FFF6DA] text-[#745A00]' : 'bg-[#EAF4F4] text-[#0B536D]';
    return <Link href={href} className="group flex min-h-28 items-start gap-4 rounded-2xl border border-[#DDE2E0] bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#9EB4B0] hover:shadow-[0_12px_26px_rgba(17,38,51,0.08)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transform-none">
        <span className={`grid size-11 shrink-0 place-items-center rounded-xl ${iconClass}`}><AppIcon name={icon} /></span><span className="min-w-0"><strong className="block text-base text-[#17212B]">{label}<AppIcon name="arrow-right" className="ml-2 inline size-4 align-[-0.15em] transition-transform group-hover:translate-x-0.5 motion-reduce:transform-none" /></strong><span className="mt-1 block text-sm leading-5 text-[#68767E]">{description}</span></span>
    </Link>;
}

function SelectedDivisionHub({ event, sport, division }) {
    const scheduleHref = route('admin.sports.schedules', { event: event.id, competition: sport.id, division: division.id });
    const resultsHref = route('admin.approvals.index', { event: event.id, competition: sport.id, division: division.id });
    return <div className="space-y-5">
        <section className={`${panel} overflow-hidden`}>
            <div className="border-b border-[#E6EAE8] bg-[#F8FAF9] p-5 sm:p-7"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Selected division</p><div className="mt-2 flex flex-wrap items-end justify-between gap-4"><div><h2 className="font-serif text-3xl font-bold text-[#17212B]">{division.name}</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-[#68767E]">This is the starting point for {division.name}. Choose a job below, then return here whenever you need the full picture.</p></div><Link href={workspaceUrl(event.id, sport.id)} className={`${quiet} min-h-10`}>View all divisions</Link></div></div>
            <div className="grid gap-4 p-5 sm:grid-cols-2 sm:p-7 lg:grid-cols-5">
                <Metric label="Teams" value={division.entry_count || 0} />
                <Metric label="Players" value={division.player_count || 0} />
                <Metric label="Team sheets" value={`${division.locked_entry_count || 0}/${division.entry_count || 0} ready`} />
                <Metric label="Bracket" value={humanize(division.bracket_state)} tone={statusClass(division.bracket_state)} />
                <Metric label="Schedule" value={humanize(division.schedule_state)} tone={statusClass(division.schedule_state)} />
            </div>
        </section>
        <section aria-labelledby="division-actions-title"><div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Division workspace</p><h2 id="division-actions-title" className="mt-1 font-serif text-2xl font-bold">What do you need to do?</h2></div><p className="text-sm text-[#68767E]">All links keep {division.name} selected.</p></div><div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><ActionCard href={route('admin.sports.tournament', [event.id, division.id])} icon="trophy" label="Bracket" description="Set up or review the division draw." tone="gold" /><ActionCard href={scheduleHref} icon="calendar" label="Schedule" description="Set game times, venues, and sharing." /><ActionCard href={resultsHref} icon="clipboard-check" label="Results" description="Review scores and final standings." /><ActionCard href={route('admin.departments.index', event.id)} icon="users" label="Teams & rosters" description="Departments own the player and team sheets." /></div></section>
        <section className={`${panel} flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6`}><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Next activity</p><h2 className="mt-1 font-serif text-xl font-bold">{division.next_schedule?.title || 'No game is scheduled yet'}</h2><p className="mt-1 text-sm text-[#68767E]">{division.next_schedule ? `${division.next_schedule.venue || 'Venue to be announced'} / ${division.next_schedule.starts_at ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(division.next_schedule.starts_at)) : 'Time to be announced'}` : 'Open Schedule when a time and venue are ready.'}</p></div><Link href={scheduleHref} className={quiet}>Open schedule <AppIcon name="arrow-right" className="size-4" /></Link></section>
    </div>;
}

function RosterEditorFrame({ event, sport, division, selectedDepartment, rosterWorkspace, rosterOptions }) {
    const departmentId = selectedDepartment?.id ?? selectedDepartment;
    const department = selectedDepartment && typeof selectedDepartment === 'object'
        ? selectedDepartment
        : rosterWorkspace?.departments?.find((item) => String(item.id) === String(departmentId));
    const departmentName = department?.name || department?.abbreviation || 'Department';
    const backHref = departmentId ? route('admin.departments.show', [event.id, departmentId]) : route('admin.departments.index', event.id);
    const backLabel = departmentId ? `Back to ${departmentName} rosters` : 'Back to Departments';
    const entry = rosterWorkspace?.selected?.entry;
    const status = entry === null || entry === undefined
        ? 'Not started'
        : entry.status === 'locked'
            ? `Approved revision ${entry.approval_revision}`
            : event.archived
                ? 'Archived'
                : entry.capabilities?.published
                    ? 'Published'
                    : 'Draft';
    return <div className="space-y-5">
        <section aria-label="Roster context">
            <Link href={backHref} className="inline-flex min-h-10 items-center gap-2 rounded-lg text-sm font-bold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2"><AppIcon name="arrow-right" className="size-4 rotate-180" />{backLabel}</Link>
            <h2 className="mt-3 font-serif text-3xl font-bold tracking-tight sm:text-4xl">{sport.name} {division.name} roster</h2>
            <p className="mt-1 text-sm font-medium text-[#68767E]">{departmentId ? departmentName : 'All departments'} <span className="mx-1.5 text-[#9AA5A8]" aria-hidden="true">/</span> {status}</p>
        </section>
        <Rosters event={event} sport={sport} division={division} selectedDepartment={selectedDepartment} workspace={rosterWorkspace} options={rosterOptions} archived={event.archived} departmentName={departmentName} />
    </div>;
}

export default function Workspace({ event, sport, divisions = [], selected_division: selectedDivision = null, active_tab: activeTab = 'overview', selected_department: selectedDepartment = null, roster_workspace: rosterWorkspace = null, roster_options: rosterOptions = { roster_roles: [] } }) {
    const division = divisions.find((item) => String(item.id) === String(selectedDivision)) ?? null;
    const focusedRosterEditor = activeTab === 'rosters' && division !== null;
    const scopedRosterEditor = focusedRosterEditor && selectedDepartment !== null && selectedDepartment !== '';
    return <AuthenticatedLayout activeSection={scopedRosterEditor ? 'departments' : null} header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">{event.name}</p><h1 className="font-serif text-2xl font-bold">{scopedRosterEditor ? 'Department roster' : sport.name}</h1></div>}>
        <Head title={`${sport.name} / Sports Directory`} />
        <main className="p-4 sm:p-7 lg:p-8"><div className="mx-auto max-w-[96rem]">{scopedRosterEditor ? null : <SportHeader event={event} sport={sport} divisions={divisions} selectedDivision={selectedDivision} />}{focusedRosterEditor ? <RosterEditorFrame event={event} sport={sport} division={division} selectedDepartment={selectedDepartment} rosterWorkspace={rosterWorkspace} rosterOptions={rosterOptions} /> : division ? <SelectedDivisionHub event={event} sport={sport} division={division} /> : <AllDivisionsHub event={event} sport={sport} divisions={divisions} />}</div></main>
    </AuthenticatedLayout>;
}
