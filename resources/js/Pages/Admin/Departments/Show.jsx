import React from 'react';
import { AdminEmptyState, AdminMasthead } from '@/Components/Admin/AdminSurface';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import SlideOver from '@/Components/SlideOver';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { adminStyles } from '@/Support/adminStyles';
import { CoachPanel, ProfileForm } from '@/Pages/Admin/Registrations/ParticipantDirectory';
import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const primary = adminStyles.primaryAction;
const quiet = adminStyles.secondaryAction;

const states = {
    not_started: 'bg-surface-muted text-muted',
    draft: 'bg-accent/10 text-accent-foreground',
    active: 'bg-primary/10 text-primary',
    locked: 'bg-primary/10 text-primary',
    blocked: 'bg-rose-50 text-rose-800',
};

function countLabel(count, singular, plural = `${singular}s`) {
    return `${count} ${count === 1 ? singular : plural}`;
}

function rosterStateLabel(state) {
    if (state === 'locked') return 'Approved';
    if (state === 'ready') return 'Ready for approval';

    return String(state || 'not_started').replaceAll('_', ' ');
}

function PersonPreview({ preview, view, onSelect }) {
    if (!preview) return null;
    return <div className="border-t border-border bg-surface-muted px-4 py-4">
        {preview.people?.length ? <div className="divide-y divide-border overflow-hidden border border-border bg-surface">{preview.people.map((person) => <button key={person.id} type="button" onClick={() => onSelect(person)} className="flex min-h-12 w-full items-center justify-between gap-4 px-4 py-3 text-left transition-colors hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"><span className="min-w-0"><strong className="block truncate text-sm">{person.display_name}</strong><span className="mt-0.5 block truncate text-xs text-muted">{view === 'players' ? person.student_number || 'No student number' : person.assignments?.map((assignment) => assignment.title).filter(Boolean).join(', ') || 'Coach or support assignment'}</span></span><span className="text-xs font-bold text-primary">Manage</span></button>)}</div> : <p className="text-sm text-muted">No {view === 'players' ? 'players' : 'coaches or support people'} are assigned here.</p>}
        {preview.has_more ? <p className="mt-3 text-xs font-semibold text-muted">Showing the first {preview.limit} of {preview.total}. Open the roster for the complete team sheet.</p> : null}
    </div>;
}

function DivisionRoster({ event, department, sport, division, view, preview, onPreview, onSelectPerson }) {
    const roster = division.rosters[0];
    const manageHref = `${route('admin.sports.show', [event.id, sport.id])}?tab=rosters&division=${division.id}&department=${department.id}`;
    const previewable = roster.id !== null;
    return <div className="overflow-hidden border border-border bg-surface">
        <div className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h4 className="font-bold text-foreground">{division.name}</h4><span className={`rounded-full px-2 py-1 text-[0.62rem] font-bold uppercase tracking-[0.06em] ${states[roster.state] || states.draft}`}>{rosterStateLabel(roster.state)}</span></div><p className="mt-1 text-xs text-muted">{countLabel(roster.counts.players, 'player')} · {countLabel(roster.counts.coaches, 'coach', 'coaches')}</p></div>
            <div className="flex flex-wrap gap-2"><button type="button" className={quiet} aria-label={`${preview?.open ? 'Hide' : 'View'} ${view === 'players' ? 'players' : 'coaches'} for ${sport.name} ${division.name}`} disabled={!previewable || preview?.loading} onClick={() => onPreview(roster, division, sport)}>{preview?.loading ? 'Loading…' : preview?.open ? 'Hide people' : `View ${view === 'players' ? 'players' : 'coaches'}`}</button><Link href={manageHref} className={primary}>{roster.id ? 'Manage roster' : 'Start roster'} <AppIcon name="arrow-right" className="size-4" /></Link></div>
        </div>
        {preview?.error ? <div className="border-t border-rose-100 bg-rose-50 px-4 py-3 text-sm text-danger">{preview.error}</div> : null}
        {preview?.open ? <PersonPreview preview={preview.data} view={view} onSelect={onSelectPerson} /> : null}
    </div>;
}

function SportRosterCard({ event, department, sport, view, previews, onPreview, onSelectPerson }) {
    const rosterCount = sport.divisions.filter((division) => division.rosters[0]?.id).length;
    return <article className="overflow-hidden border border-border bg-surface">
        <header className="flex items-start justify-between gap-4 border-b border-border bg-surface-muted px-5 py-4">
            <div><p className="text-[0.62rem] font-bold uppercase tracking-[0.13em] text-primary">Sport</p><h3 className="mt-1 text-2xl font-bold">{sport.name}</h3><p className="mt-1 text-xs text-muted">{rosterCount}/{sport.divisions.length} rosters started · {countLabel(sport.counts.players, 'player')}</p></div>
            <Link href={route('admin.sports.show', [event.id, sport.id])} className="text-xs font-bold text-primary underline decoration-accent decoration-2 underline-offset-4">Sport workspace</Link>
        </header>
        <div className="space-y-3 p-4">{sport.divisions.map((division) => {
            const roster = division.rosters[0];
            const key = `${view}:${roster.id || `new:${division.id}`}`;
            return <DivisionRoster key={division.id} event={event} department={department} sport={sport} division={division} view={view} preview={previews[key]} onPreview={onPreview} onSelectPerson={onSelectPerson} />;
        })}</div>
    </article>;
}

export default function DepartmentRosters({ event, department, departments = [], competitions = [], initial_view: initialView = 'players' }) {
    const [view, setView] = useState(initialView === 'coaches' ? 'coaches' : 'players');
    const [query, setQuery] = useState('');
    const [scope, setScope] = useState('all');
    const [previews, setPreviews] = useState({});
    const [profile, setProfile] = useState(null);
    const [createOpen, setCreateOpen] = useState(false);
    const sports = useMemo(() => {
        const needle = query.trim().toLowerCase();
        return department.sports.filter((sport) => {
            const matchesQuery = !needle || sport.name.toLowerCase().includes(needle);
            const peopleCount = view === 'players' ? sport.counts.players : sport.counts.coaches;
            const matchesScope = scope === 'all'
                || (scope === 'with_people' && peopleCount > 0)
                || (scope === 'not_started' && sport.divisions.some((division) => division.rosters[0]?.state === 'not_started'));
            return matchesQuery && matchesScope;
        });
    }, [department.sports, query, scope, view]);

    const changeView = (nextView) => {
        setView(nextView);
        setPreviews({});
        router.get(route('admin.departments.show', [event.id, department.id]), { view: nextView }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const loadPreview = async (roster, division, sport) => {
        if (!roster.id) return;
        const key = `${view}:${roster.id}`;
        const current = previews[key];
        if (current?.data) {
            setPreviews((state) => ({ ...state, [key]: { ...current, open: !current.open } }));
            return;
        }
        setPreviews((state) => ({ ...state, [key]: { loading: true, open: true, data: null, error: null } }));
        const url = new URL(route('admin.registrations.directory-preview', event.id), window.location.origin);
        Object.entries({ view, department: department.id, sport: sport.id, division: division.id, entry: roster.id, status: 'all' }).forEach(([name, value]) => url.searchParams.set(name, value));
        try {
            const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('The people preview could not be loaded.');
            const data = await response.json();
            setPreviews((state) => ({ ...state, [key]: { loading: false, open: true, data, error: null } }));
        } catch (error) {
            setPreviews((state) => ({ ...state, [key]: { loading: false, open: true, data: null, error: error.message || 'The people preview could not be loaded.' } }));
        }
    };

    const accent = department.color || '#0B536D';
    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{event.name}</p><h1 className="text-2xl font-bold">{department.abbreviation || department.name} rosters</h1></div>}>
        <Head title={`${department.name} · Rosters`} />
        <main className={adminStyles.page}><div className="mx-auto max-w-[96rem] space-y-6">
            <nav className="flex flex-wrap items-center gap-2 text-sm text-muted" aria-label="Breadcrumb"><Link href={route('admin.departments.index', event.id)} className="font-bold text-primary">Departments</Link><span aria-hidden="true">/</span><span>{department.name}</span></nav>

            <div style={{ borderTopColor: accent }} className="border-t-2"><AdminMasthead eyebrow="Department roster desk" title={department.name} description="Manage this department’s team sheets across every sport and event division."><dl className="grid grid-cols-3 divide-x divide-border text-sm"><div className="px-3 first:pl-0"><dt className="text-muted">Players</dt><dd className="mt-1 font-condensed text-3xl font-bold text-primary">{department.counts.players}</dd></div><div className="px-3"><dt className="text-muted">Coaches</dt><dd className="mt-1 font-condensed text-3xl font-bold text-primary">{department.counts.coaches}</dd></div><div className="px-3"><dt className="text-muted">Team sheets</dt><dd className="mt-1 font-condensed text-3xl font-bold text-primary">{department.counts.rosters}/{department.sports.reduce((total, sport) => total + sport.divisions.length, 0)}</dd></div></dl></AdminMasthead></div>

            <section className="flex flex-col gap-4 border border-border bg-surface p-4 sm:flex-row sm:items-end sm:justify-between sm:p-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end"><div><p className="mb-2 text-xs font-bold uppercase tracking-[0.12em] text-muted">People shown in previews</p><div className="inline-flex rounded-sm border border-border bg-surface-muted p-1"><button type="button" onClick={() => changeView('players')} className={`min-h-9 rounded-sm px-4 text-sm font-bold ${view === 'players' ? 'bg-primary text-primary-foreground' : 'text-muted'}`}>Players</button><button type="button" onClick={() => changeView('coaches')} className={`min-h-9 rounded-sm px-4 text-sm font-bold ${view === 'coaches' ? 'bg-primary text-primary-foreground' : 'text-muted'}`}>Coaches &amp; support</button></div></div><label className="relative w-full sm:w-72"><span className="sr-only">Find a sport</span><AppIcon name="search" className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" /><input type="search" value={query} onChange={(eventObject) => setQuery(eventObject.target.value)} placeholder="Find a sport" className={`${adminStyles.field} pl-10`} /></label><label className="w-full sm:w-48"><span className="mb-2 block text-xs font-bold uppercase tracking-[0.12em] text-muted">Show</span><select value={scope} onChange={(eventObject) => setScope(eventObject.target.value)} className={adminStyles.field}><option value="all">All sports</option><option value="with_people">With {view === 'players' ? 'players' : 'coaches'}</option><option value="not_started">Needs a team sheet</option></select></label></div>
                <button type="button" className={primary} onClick={() => setCreateOpen(true)} disabled={event.archived}><AppIcon name="user-plus" className="size-4" />Add {view === 'players' ? 'player' : 'coach or support'}</button>
            </section>

            {department.counts.unassigned > 0 && view === 'players' ? <div className="flex items-start gap-3 rounded-sm border border-accent/40 bg-accent/10 px-4 py-3 text-sm text-accent-foreground"><AppIcon name="warning" className="mt-0.5 size-4 shrink-0" /><p><strong>{department.counts.unassigned} registered player{department.counts.unassigned === 1 ? '' : 's'} still {department.counts.unassigned === 1 ? 'needs' : 'need'} a roster.</strong> Open the correct sport below, then use Add players.</p></div> : null}

            {sports.length ? <section className="grid items-start gap-5 xl:grid-cols-2" aria-label={`${department.name} sport rosters`}>{sports.map((sport) => <SportRosterCard key={sport.id} event={event} department={department} sport={sport} view={view} previews={previews} onPreview={loadPreview} onSelectPerson={setProfile} />)}</section> : <AdminEmptyState title="No sports match these choices" description="Try another sport name or change the Show filter." />}
        </div></main>

        <SlideOver show={Boolean(profile)} title={view === 'players' ? 'Player profile' : 'Coach profile and assignments'} onClose={() => setProfile(null)}>{profile ? (view === 'players' ? <ProfileForm event={event} departments={departments} participant={profile} onClose={() => setProfile(null)} /> : <CoachPanel event={event} coach={profile} departments={departments} competitions={competitions} onClose={() => setProfile(null)} />) : null}</SlideOver>
        <SlideOver show={createOpen} title={`Add ${view === 'players' ? 'player' : 'coach or support'}`} onClose={() => setCreateOpen(false)}><ProfileForm event={event} departments={[department]} coach={view === 'coaches'} onClose={() => setCreateOpen(false)} /></SlideOver>
    </AuthenticatedLayout>;
}
