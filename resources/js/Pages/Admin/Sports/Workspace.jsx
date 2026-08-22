import React from 'react';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DivisionStatus from '@/Components/Sports/DivisionStatus';
import SportWorkspaceShell from '@/Components/Sports/SportWorkspaceShell';
import WorkflowNotice from '@/Components/Sports/WorkflowNotice';
import { sportWorkflow, sportWorkspaceUrl } from '@/Support/sportWorkspaceRoutes';
import { adminStyles } from '@/Support/adminStyles';
import Rosters from './Rosters';

const panel = adminStyles.section;
const primary = adminStyles.primaryAction;
const quiet = adminStyles.secondaryAction;

const legacySection = {
    overview: 'overview',
    rosters: 'teams',
    matches: 'bracket',
    schedule: 'schedule',
    results: 'results',
};

const resultPresentation = {
    not_started: {
        label: 'Not started',
        tone: 'waiting',
        ready: false,
        nextLabel: 'Start recording results',
        nextDetail: 'Record the first competition result when play begins.',
    },
    in_progress: {
        label: 'In progress',
        tone: 'progress',
        ready: false,
        nextLabel: 'Complete competition results',
        nextDetail: 'Finish the remaining results and prepare the final placement for approval.',
    },
    pending_review: {
        label: 'Needs review',
        tone: 'attention',
        ready: false,
        nextLabel: 'Review submitted results',
        nextDetail: 'Review the submitted scores or final placement before closing the division.',
    },
    complete: {
        label: 'Complete',
        tone: 'ready',
        ready: true,
        nextLabel: 'Division is complete',
        nextDetail: 'The approved final placement completes this division workflow.',
    },
};

const schedulePresentation = {
    not_scheduled: {
        label: 'Not scheduled',
        ready: false,
        nextLabel: 'Set the schedule',
        nextDetail: 'Add official game times and venues.',
    },
    draft: {
        label: 'Draft changes',
        ready: false,
        nextLabel: 'Publish the schedule',
        nextDetail: 'Review and publish the latest schedule changes.',
    },
    published: {
        label: 'Published',
        ready: true,
    },
};

/**
 * Backwards-compatible helper for existing consumers while the visible
 * navigation uses the shared sport workspace route builder.
 */
export function workspaceUrl(eventId, sportId, tabOrOptions = null, divisionId = null, departmentId = null) {
    let section = 'overview';
    let division = divisionId;
    let department = departmentId;

    if (tabOrOptions && typeof tabOrOptions === 'object') {
        section = legacySection[tabOrOptions.tab] || tabOrOptions.section || 'overview';
        division = tabOrOptions.division ?? division;
        department = tabOrOptions.department ?? department;
    } else if (tabOrOptions && legacySection[tabOrOptions]) {
        section = legacySection[tabOrOptions];
    } else if (tabOrOptions !== null && tabOrOptions !== undefined && tabOrOptions !== '') {
        division = tabOrOptions;
    }

    return sportWorkspaceUrl(eventId, sportId, { section, division, department });
}
function humanize(value, fallback = 'Not started') {
    if (!value) return fallback;
    return String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function divisionFor(divisions, selectedId) {
    return divisions.find((item) => String(item.id) === String(selectedId)) ?? null;
}

function ReadinessRow({ event, sport, division, section, label, detail, state, actionLabel = 'Open' }) {
    return <div className="flex flex-col gap-3 border-b border-border py-4 last:border-b-0 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex min-w-0 items-start gap-3"><span className={`mt-1 grid size-7 shrink-0 place-items-center rounded-full ${state === 'ready' ? 'bg-primary/10 text-primary' : 'bg-accent/15 text-accent-foreground'}`}><AppIcon name={state === 'ready' ? 'check' : 'chevron'} className="size-4" /></span><div className="min-w-0"><h3 className="font-semibold text-foreground">{label}</h3><p className="mt-1 text-sm text-muted">{detail}</p></div></div>
        <Link href={sportWorkspaceUrl(event.id, sport.id, { section, division: division.id })} className="inline-flex min-h-9 shrink-0 items-center gap-2 self-start rounded-sm px-3 text-sm font-bold text-primary hover:bg-primary/5 sm:self-auto">{actionLabel}<AppIcon name="arrow-right" className="size-4" /></Link>
    </div>;
}

function DivisionOverview({ event, sport, division }) {
    const requiredTeams = division.participating_entry_count ?? division.entry_count ?? 0;
    const lockedTeams = division.locked_entry_count ?? 0;
    const teamsReady = requiredTeams > 0 && lockedTeams === requiredTeams;
    const teamsDetail = requiredTeams ? `${lockedTeams} of ${requiredTeams} team sheets ready` : 'No participating teams yet';
    const bracketReady = !['not_generated', 'missing'].includes(division.bracket_state);
    const schedule = schedulePresentation[division.schedule_state] || schedulePresentation.not_scheduled;
    const scheduleReady = schedule.ready;
    const results = resultPresentation[division.results_state] || resultPresentation.not_started;
    const resultsReady = results.ready;
    const nextSection = !teamsReady ? 'teams' : !bracketReady ? 'bracket' : !scheduleReady ? 'schedule' : !resultsReady ? 'results' : 'overview';
    const nextLabel = !teamsReady ? 'Complete team sheets' : !bracketReady ? 'Prepare the bracket' : !scheduleReady ? schedule.nextLabel : results.nextLabel;
    const nextDetail = !teamsReady ? 'Prepare competition rosters before generating the official draw.' : !bracketReady ? 'Generate the official draw once all team sheets are ready.' : !scheduleReady ? schedule.nextDetail : results.nextDetail;

    return <div className="flex flex-col gap-5">
        <section className={`${panel} p-5 sm:p-7`} aria-labelledby="division-overview-title">
            <div className="flex flex-col gap-2 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">Division overview</p><h2 id="division-overview-title" className="mt-1 text-3xl font-bold text-foreground">{division.name}</h2><p className="mt-2 text-sm leading-6 text-muted">A single readiness view for the work that moves {sport.name} {division.name} toward game day.</p></div><DivisionStatus division={division} /></div>
            <div className="pt-5"><p className="text-xs font-bold uppercase tracking-[0.16em] text-muted">Setup progress</p><div className="mt-2">
                <ReadinessRow event={event} sport={sport} division={division} section="teams" label="Teams & Rosters" detail={teamsDetail} state={teamsReady ? 'ready' : 'attention'} actionLabel="Manage rosters" />
                <ReadinessRow event={event} sport={sport} division={division} section="bracket" label="Bracket" detail={humanize(division.bracket_state)} state={bracketReady ? 'ready' : 'attention'} actionLabel="Open bracket" />
                <ReadinessRow event={event} sport={sport} division={division} section="schedule" label="Schedule" detail={schedule.label} state={scheduleReady ? 'ready' : 'attention'} actionLabel="Open schedule" />
                <ReadinessRow event={event} sport={sport} division={division} section="results" label="Results" detail={results.label} state={resultsReady ? 'ready' : 'attention'} actionLabel="Open results" />
            </div></div>
        </section>
        <WorkflowNotice title="Next step" tone={nextSection === 'overview' ? 'success' : 'attention'} action={<Link href={sportWorkspaceUrl(event.id, sport.id, { section: nextSection, division: division.id })} className={primary}>{nextLabel}<AppIcon name="arrow-right" className="size-4" /></Link>}>
            {nextDetail} {teamsReady && !bracketReady ? `${lockedTeams} of ${requiredTeams} team sheets are ready.` : ''}
        </WorkflowNotice>
    </div>;
}

function DivisionDirectory({ event, sport, divisions }) {
    return <section className={`${panel} overflow-hidden`} aria-labelledby="division-directory-title">
        <div className="flex flex-col gap-2 border-b border-border p-5 sm:flex-row sm:items-end sm:justify-between sm:p-7"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">Sport overview</p><h2 id="division-directory-title" className="mt-1 text-2xl font-bold text-foreground">Choose a division</h2><p className="mt-1 text-sm text-muted">Open a division to see its readiness, teams, bracket, schedule, and results.</p></div><Link href={sportWorkspaceUrl(event.id, sport.id, { section: 'teams', division: divisions[0]?.id })} className={`${quiet} ${divisions.length ? '' : 'pointer-events-none opacity-50'}`}>Teams &amp; Rosters</Link></div>
        <div className="flex flex-col gap-1 p-4 sm:p-5">{divisions.length ? divisions.map((division) => <Link key={division.id} href={sportWorkspaceUrl(event.id, sport.id, { division: division.id })} className="group flex flex-col gap-3 rounded-sm border border-transparent px-4 py-4 transition hover:border-border hover:bg-surface-muted sm:flex-row sm:items-center sm:justify-between"><div className="min-w-0"><h3 className="text-2xl font-bold text-foreground group-hover:text-primary">{division.name}</h3><p className="mt-1 text-sm text-muted">{division.entry_count || 0} teams · {division.player_count || 0} players</p></div><div className="flex items-center gap-4"><DivisionStatus division={division} compact /><AppIcon name="arrow-right" className="size-4 text-muted transition-transform group-hover:translate-x-1" /></div></Link>) : <div className="rounded-sm border border-dashed border-border p-8 text-center"><h3 className="text-xl font-bold text-foreground">No divisions configured yet</h3><p className="mt-2 text-sm text-muted">Add a division in sport settings before setting up teams and games.</p></div>}</div>
    </section>;
}

function RosterEditorFrame({ event, sport, division, selectedDepartment, rosterWorkspace, rosterOptions }) {
    const departmentId = selectedDepartment?.id ?? selectedDepartment;
    const department = selectedDepartment && typeof selectedDepartment === 'object'
        ? selectedDepartment
        : rosterWorkspace?.departments?.find((item) => String(item.id) === String(departmentId));
    const departmentName = department?.name || department?.abbreviation || 'Team';
    const backHref = sportWorkspaceUrl(event.id, sport.id, { section: 'teams', division: division.id });
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

    return <div className="flex flex-col gap-5">
        <section aria-label="Team roster context"><Link href={backHref} className="inline-flex min-h-10 items-center gap-2 rounded-sm text-sm font-bold text-primary underline decoration-accent decoration-2 underline-offset-4 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2"><AppIcon name="arrow-right" className="size-4 rotate-180" />Back to Teams &amp; Rosters</Link><div className="mt-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h2 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">{departmentName}</h2><p className="mt-1 text-sm text-muted">Team roster · {status}</p></div><DivisionStatus division={division} compact /></div></section>
        <Rosters event={event} sport={sport} division={division} selectedDepartment={selectedDepartment} workspace={rosterWorkspace} options={rosterOptions} archived={event.archived} departmentName={departmentName} />
    </div>;
}

function WorkspaceContent({ event, sport, divisions, division, activeSection, selectedDepartment, rosterWorkspace, rosterOptions }) {
    if (activeSection === 'teams') {
        if (division) {
            return selectedDepartment !== null && selectedDepartment !== ''
                ? <RosterEditorFrame event={event} sport={sport} division={division} selectedDepartment={selectedDepartment} rosterWorkspace={rosterWorkspace} rosterOptions={rosterOptions} />
                : <Rosters event={event} sport={sport} division={division} selectedDepartment={null} workspace={rosterWorkspace} options={rosterOptions} archived={event.archived} />;
        }
        return <DivisionDirectory event={event} sport={sport} divisions={divisions} />;
    }

    if (activeSection === 'overview' && division) return <DivisionOverview event={event} sport={sport} division={division} />;
    if (activeSection === 'overview') return <DivisionDirectory event={event} sport={sport} divisions={divisions} />;

    return <WorkflowNotice title={`${sportWorkflow[activeSection]} keeps this division selected`} action={<Link href={sportWorkspaceUrl(event.id, sport.id, { section: activeSection, division: division?.id })} className={primary}>Open {sportWorkflow[activeSection]}<AppIcon name="arrow-right" className="size-4" /></Link>}>Choose a division above to enter the operational workspace for {sport.name}.</WorkflowNotice>;
}

export default function Workspace({ event, sport, divisions = [], selected_division: selectedDivision = null, active_tab: activeTab = 'overview', selected_department: selectedDepartment = null, roster_workspace: rosterWorkspace = null, roster_options: rosterOptions = { roster_roles: [] } }) {
    const division = divisionFor(divisions, selectedDivision);
    const activeSection = legacySection[activeTab] || 'overview';
    const title = division ? `${sport.name} ${division.name} · ${sportWorkflow[activeSection]}` : `${sport.name} · Sports Directory`;

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">{event.name}</p><h1 className="text-2xl font-bold">Sports Directory</h1></div>}>
        <Head title={title} />
        <main className={adminStyles.page}><SportWorkspaceShell event={event} sport={sport} division={division} divisions={divisions} activeSection={activeSection}>
            <WorkspaceContent event={event} sport={sport} divisions={divisions} division={division} activeSection={activeSection} selectedDepartment={selectedDepartment} rosterWorkspace={rosterWorkspace} rosterOptions={rosterOptions} />
        </SportWorkspaceShell></main>
    </AuthenticatedLayout>;
}
