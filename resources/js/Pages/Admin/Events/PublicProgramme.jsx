import { AdminEmptyState, AdminMasthead } from '@/Components/Admin/AdminSurface';
import AppIcon from '@/Components/AppIcon';
import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SportWorkspaceShell from '@/Components/Sports/SportWorkspaceShell';
import { adminStyles } from '@/Support/adminStyles';
import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const input = `mt-1 ${adminStyles.field}`;
const primary = adminStyles.primaryAction;
const quiet = adminStyles.secondaryAction;

function formatDay(value) {
    if (!value) return 'Needs a time';
    return new Intl.DateTimeFormat(undefined, { weekday: 'long', month: 'short', day: 'numeric' }).format(new Date(value));
}

function formatTime(value) {
    if (!value) return 'Time not set';
    return new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value));
}

function toLocalInput(value) {
    if (!value) return '';
    const date = new Date(value);
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function Pill({ children, tone = 'neutral' }) {
    const tones = {
        neutral: 'bg-surface-muted text-muted',
        amber: 'bg-accent/10 text-accent-foreground',
        green: 'bg-primary/10 text-primary',
        blue: 'bg-primary/10 text-primary',
    };
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-[0.08em] ${tones[tone]}`}>{children}</span>;
}

function Drawer({ titleId, label, title, onClose, children }) {
    return <div className="fixed inset-0 z-50">
        <button type="button" aria-label="Close drawer" onClick={onClose} className="absolute inset-0 bg-foreground/45" />
        <aside role="dialog" aria-modal="true" aria-labelledby={titleId} className="absolute inset-y-0 right-0 w-full max-w-xl overflow-y-auto border-l border-border bg-background shadow-[0_0_24px_rgb(0_26_63/0.12)]">
            <header className="sticky top-0 z-10 flex items-start justify-between border-b border-border bg-surface p-5">
                <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{label}</p><h2 id={titleId} className="mt-1 text-2xl font-bold text-foreground">{title}</h2></div>
                <button type="button" autoFocus onClick={onClose} aria-label="Close drawer" className="grid size-10 place-items-center rounded-sm focus-visible:ring-2 focus-visible:ring-ring"><AppIcon name="close" /></button>
            </header>
            {children}
        </aside>
    </div>;
}

function VenueSheet({ event, venues, onClose, readOnly }) {
    const [editing, setEditing] = useState(null);
    const form = useForm({ name: '', code: '', location: '', description: '', is_active: true });
    const editForm = useForm({ name: '', code: '', location: '', description: '', is_active: true });

    useEffect(() => {
        if (!editing) return;
        editForm.setData('name', editing.name || '');
        editForm.setData('code', editing.code || '');
        editForm.setData('location', editing.location || '');
        editForm.setData('description', editing.description || '');
        editForm.setData('is_active', Boolean(editing.is_active));
    }, [editing]);

    const save = (eventObject) => {
        eventObject.preventDefault();
        form.post(route('admin.venues.store', event.id), { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const update = (eventObject) => {
        eventObject.preventDefault();
        editForm.patch(route('admin.venues.update', [event.id, editing.id]), { preserveScroll: true, onSuccess: () => setEditing(null) });
    };

    return <Drawer titleId="venues-title" label="Schedule settings" title="Manage venues" onClose={onClose}>
        <div className="space-y-5 p-5">
            {!readOnly ? <form onSubmit={save} className="rounded-sm border border-border bg-surface p-5">
                <h3 className="text-lg font-bold">Add a venue</h3>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <label className="text-xs font-bold uppercase">Name<input required value={form.data.name} onChange={(eventObject) => form.setData('name', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase">Short code<input value={form.data.code} onChange={(eventObject) => form.setData('code', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase sm:col-span-2">Location<input value={form.data.location} onChange={(eventObject) => form.setData('location', eventObject.target.value)} className={input} /></label>
                </div>
                <button type="submit" disabled={form.processing} className={`mt-4 ${primary}`}>Add venue</button>
                <InputError message={form.errors.name} className="mt-2" />
            </form> : null}
            <section className="rounded-sm border border-border bg-surface p-5">
                <div className="flex items-center justify-between"><h3 className="text-lg font-bold">Available venues</h3><span className="text-sm text-muted">{venues.length}</span></div>
                <div className="mt-3 divide-y divide-border">
                    {venues.length ? venues.map((venue) => <div key={venue.id} className="py-4 first:pt-0 last:pb-0"><div className="flex items-center justify-between gap-3"><div><p className="font-bold text-foreground">{venue.name}</p><p className="mt-1 text-sm text-muted">{venue.location || 'Location not added'}</p></div><div className="flex items-center gap-2"><Pill tone={venue.is_active ? 'green' : 'neutral'}>{venue.is_active ? 'Active' : 'Inactive'}</Pill>{!readOnly ? <button type="button" onClick={() => setEditing(venue)} className="text-sm font-bold text-primary underline">Edit</button> : null}</div></div></div>) : <p className="py-4 text-sm text-muted">No venues yet. Add the first one above.</p>}
                </div>
            </section>
            {editing ? <form onSubmit={update} className="rounded-sm border border-border bg-surface p-5">
                <div className="flex justify-between gap-3"><h3 className="text-lg font-bold">Edit {editing.name}</h3><button type="button" onClick={() => setEditing(null)} className="text-sm font-bold text-primary">Cancel</button></div>
                <div className="mt-4 space-y-3">
                    <label className="text-xs font-bold uppercase">Name<input required value={editForm.data.name} onChange={(eventObject) => editForm.setData('name', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase">Short code<input value={editForm.data.code} onChange={(eventObject) => editForm.setData('code', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase">Location<input value={editForm.data.location} onChange={(eventObject) => editForm.setData('location', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase">Notes<textarea rows="3" value={editForm.data.description} onChange={(eventObject) => editForm.setData('description', eventObject.target.value)} className={input} /></label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={editForm.data.is_active} onChange={(eventObject) => editForm.setData('is_active', eventObject.target.checked)} />Available for schedules</label>
                </div>
                <button type="submit" disabled={editForm.processing} className={`mt-4 ${primary}`}>Save venue</button>
            </form> : null}
        </div>
    </Drawer>;
}

function ScheduleSheet({ event, match, venues, statuses, onClose, readOnly }) {
    const existing = match.schedule;
    const form = useForm({
        contest_id: match.contest_id,
        competition_division_id: match.division_id,
        venue_id: existing?.venue_id || '',
        title: existing?.title || `${match.teams[0]} vs ${match.teams[1]}`,
        starts_at: toLocalInput(existing?.starts_at),
        ends_at: toLocalInput(existing?.ends_at),
        status: existing?.status || 'scheduled',
        notes: existing?.notes || '',
    });

    const submit = (eventObject) => {
        eventObject.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (existing) form.patch(route('admin.schedules.update', [event.id, existing.id]), options);
        else form.post(route('admin.schedules.store', event.id), options);
    };

    return <Drawer titleId="match-editor-title" label={`${match.competition || 'Sport'} / ${match.division || 'Division'}`} title={existing ? 'Edit match time' : 'Set match time'} onClose={onClose}>
        <form onSubmit={submit} className="space-y-4 p-5">
            <p className="text-sm font-semibold text-foreground">{match.teams.join(' vs ')}</p>
            {!existing ? <div className="rounded-sm bg-primary/10 p-3 text-sm text-primary">This schedules the existing bracket match. It does not create another game.</div> : null}
            <label className="block text-xs font-bold uppercase">Title<input required value={form.data.title} onChange={(eventObject) => form.setData('title', eventObject.target.value)} className={input} /></label>
            <div className="grid gap-3 sm:grid-cols-2"><label className="text-xs font-bold uppercase">Starts<input required type="datetime-local" value={form.data.starts_at} onChange={(eventObject) => form.setData('starts_at', eventObject.target.value)} className={input} /><InputError message={form.errors.starts_at} /></label><label className="text-xs font-bold uppercase">Ends<input type="datetime-local" value={form.data.ends_at} onChange={(eventObject) => form.setData('ends_at', eventObject.target.value)} className={input} /></label></div>
            <label className="block text-xs font-bold uppercase">Venue<select value={form.data.venue_id} onChange={(eventObject) => form.setData('venue_id', eventObject.target.value)} className={input}><option value="">Venue to be announced</option>{venues.filter((venue) => venue.is_active).map((venue) => <option key={venue.id} value={venue.id}>{venue.name}</option>)}</select></label>
            <label className="block text-xs font-bold uppercase">Status<select value={form.data.status} onChange={(eventObject) => form.setData('status', eventObject.target.value)} className={input}>{statuses.map((status) => <option key={status.value} value={status.value}>{status.label}</option>)}</select></label>
            <label className="block text-xs font-bold uppercase">Internal notes<textarea rows="4" value={form.data.notes} onChange={(eventObject) => form.setData('notes', eventObject.target.value)} className={input} /></label>
            <div className="flex justify-end gap-2"><button type="button" onClick={onClose} className={quiet}>Cancel</button><button type="submit" disabled={readOnly || form.processing} className={primary}>{form.processing ? 'Saving...' : 'Save match time'}</button></div>
        </form>
    </Drawer>;
}

function MatchRow({ event, match, readOnly, onEdit }) {
    const publishForm = useForm({});
    const schedule = match.schedule;
    const canPublish = schedule && (!schedule.publication || schedule.has_unpublished_changes);
    const publish = () => publishForm.post(route('admin.schedules.publish', [event.id, schedule.id]), { preserveScroll: true });

    return <article className="grid gap-3 border-b border-border px-4 py-4 last:border-b-0 sm:grid-cols-[6.5rem_minmax(0,1fr)_auto] sm:items-center sm:px-5">
        <div><p className="text-lg font-black text-foreground">{formatTime(schedule?.starts_at)}</p><p className="mt-1 text-xs font-bold uppercase tracking-[0.08em] text-muted">{schedule?.ends_at ? `Until ${formatTime(schedule.ends_at)}` : 'End time not set'}</p></div>
        <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><Pill tone="blue">{match.round}</Pill><Pill>{match.division}</Pill>{schedule?.publication && !schedule.has_unpublished_changes ? <Pill tone="green">Published</Pill> : <Pill tone="amber">Draft</Pill>}{schedule?.has_unpublished_changes && schedule?.publication ? <Pill tone="amber">Changes waiting</Pill> : null}</div><h3 className="mt-2 truncate text-xl font-bold text-foreground">{match.teams[0]} <span className="text-accent-foreground">vs</span> {match.teams[1]}</h3><p className="mt-1 truncate text-sm text-muted">{schedule?.venue || 'Venue to be announced'} / {schedule?.title || 'Bracket match not scheduled'}</p></div>
        <div className="flex flex-wrap gap-2 sm:justify-end">{schedule ? <button type="button" disabled={readOnly} onClick={() => onEdit(match)} className={quiet}>Edit</button> : <button type="button" disabled={readOnly} onClick={() => onEdit(match)} className={primary}>Set time</button>}{canPublish ? <button type="button" disabled={readOnly || publishForm.processing} onClick={publish} className={`${primary} !bg-primary`}>{publishForm.processing ? 'Publishing...' : 'Publish'}</button> : null}</div>
    </article>;
}

function ScheduleWorkspace({ event, competitions, scope, children }) {
    const selectedSport = competitions.find((competition) => String(competition.id) === String(scope.competition_id));
    if (!selectedSport) return children;
    const selectedDivision = selectedSport.divisions.find((division) => String(division.id) === String(scope.division_id)) || null;
    const sport = { ...selectedSport, division_count: selectedSport.divisions.length };

    return <SportWorkspaceShell event={event} sport={sport} division={selectedDivision} divisions={selectedSport.divisions} activeSection="schedule">{children}</SportWorkspaceShell>;
}

export default function PublicProgramme({ event, competitions = [], venues = [], schedules = [], matches = [], schedule_statuses: statuses = [], scope = {} }) {
    const isScoped = Boolean(scope.competition_id || scope.division_id);
    const selectedCompetition = competitions.find((competition) => String(competition.id) === String(scope.competition_id));
    const visibleMatches = matches.length ? matches : (!isScoped ? schedules.filter((schedule) => schedule.contest_id).map((schedule) => ({
        id: schedule.id,
        contest_id: schedule.contest_id,
        competition: schedule.competition,
        competition_id: schedule.competition_id,
        division: schedule.division,
        division_id: schedule.competition_division_id,
        teams: [schedule.title, 'TBD'],
        round: 'Programme',
        schedule,
    })) : []);
    const [sport, setSport] = useState('all');
    const [division, setDivision] = useState(scope.division_id || 'all');
    const [status, setStatus] = useState('all');
    const [publication, setPublication] = useState('all');
    const [date, setDate] = useState('all');
    const [editor, setEditor] = useState(null);
    const [venuesOpen, setVenuesOpen] = useState(false);
    const { flash } = usePage().props;
    const readOnly = event.archived;
    const selectedSport = competitions.find((competition) => String(competition.id) === String(sport));
    const activeCompetition = isScoped ? selectedCompetition : selectedSport;
    const sportMatches = useMemo(() => visibleMatches.filter((match) => isScoped || sport === 'all' || String(match.competition_id) === String(sport)), [visibleMatches, isScoped, sport]);
    const visibleDivisions = activeCompetition?.divisions || competitions.flatMap((item) => item.divisions.map((itemDivision) => ({ ...itemDivision, competition: item.name })));
    const filtered = useMemo(() => sportMatches.filter((match) => (division === 'all' || String(match.division_id) === String(division)) && (status === 'all' || match.schedule?.status === status) && (publication === 'all' || (publication === 'public' ? Boolean(match.schedule?.publication && !match.schedule?.has_unpublished_changes) : !match.schedule?.publication)) && (date === 'all' || (date === 'unscheduled' ? !match.schedule?.starts_at : match.schedule?.starts_at?.slice(0, 10) === date))), [sportMatches, division, status, publication, date]);
    const needsTime = filtered.filter((match) => !match.schedule?.starts_at);
    const scheduled = filtered.filter((match) => match.schedule?.starts_at);
    const dayGroups = scheduled.reduce((groups, match) => { const key = match.schedule.starts_at.slice(0, 10); (groups[key] ||= []).push(match); return groups; }, {});
    const dates = [...new Set(sportMatches.map((match) => match.schedule?.starts_at?.slice(0, 10)).filter(Boolean))];
    const publishableMatches = sportMatches.filter((match) => match.schedule?.starts_at && (match.schedule?.has_unpublished_changes || !match.schedule?.publication));
    const bulkForm = useForm({});
    const publishAll = () => { if (activeCompetition) bulkForm.post(route('admin.schedules.publish-competition', [event.id, activeCompetition.id]), { preserveScroll: true }); };

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{isScoped ? `${scope.competition}${scope.division ? ` / ${scope.division}` : ''}` : event.name}</p><h1 className="text-2xl font-bold text-foreground">Schedule &amp; Publishing</h1></div>}>
        <Head title={`Schedule & Publishing / ${event.name}`} />
        <main className={adminStyles.page}><ScheduleWorkspace event={event} competitions={competitions} scope={scope}><div className="mx-auto flex max-w-[90rem] flex-col gap-6">
            {flash?.status ? <div role="status" className="border border-primary/35 bg-surface px-5 py-4 text-sm font-medium text-primary">{flash.status}</div> : null}
            <AdminMasthead eyebrow={isScoped ? 'Schedule' : 'All sports'} title="Match-day schedule" description="Schedule the matches already in your bracket, then publish the times your students will see."><div className="flex flex-wrap gap-5 text-sm"><span><strong className="font-condensed text-3xl text-primary">{visibleMatches.length}</strong> matches</span><span><strong className="font-condensed text-3xl text-accent-foreground">{needsTime.length}</strong> need a time</span></div></AdminMasthead>
            <section className="border border-border bg-surface p-4"><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {!isScoped ? <label className="text-xs font-bold uppercase">Sport<select value={sport} onChange={(eventObject) => { setSport(eventObject.target.value); setDivision('all'); }} className={input}><option value="all">All sports</option>{competitions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label> : null}
                <label className="text-xs font-bold uppercase">Division<select value={division} onChange={(eventObject) => setDivision(eventObject.target.value)} className={input}><option value="all">All divisions</option>{visibleDivisions.map((item) => <option key={item.id} value={item.id}>{item.competition ? `${item.competition} / ` : ''}{item.name}</option>)}</select></label>
                <label className="text-xs font-bold uppercase">Status<select value={status} onChange={(eventObject) => setStatus(eventObject.target.value)} className={input}><option value="all">All statuses</option>{statuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>
                <label className="text-xs font-bold uppercase">Visibility<select value={publication} onChange={(eventObject) => setPublication(eventObject.target.value)} className={input}><option value="all">All visibility</option><option value="public">Published</option><option value="draft">Needs publishing</option></select></label>
                <label className="text-xs font-bold uppercase">Day<select value={date} onChange={(eventObject) => setDate(eventObject.target.value)} className={input}><option value="all">All days</option><option value="unscheduled">Needs a time</option>{dates.map((item) => <option key={item} value={item}>{formatDay(`${item}T12:00:00`)}</option>)}</select></label>
            </div></section>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-accent-foreground">Operations agenda</p><h2 className="mt-1 text-2xl font-bold text-foreground">{isScoped ? scope.competition : (activeCompetition?.name || 'Event programme')}</h2></div><div className="flex flex-wrap gap-2">{!readOnly ? <button type="button" onClick={() => setVenuesOpen(true)} className={quiet}><AppIcon name="map-pin" className="size-4" />Manage venues</button> : null}{!readOnly && activeCompetition ? <button type="button" disabled={bulkForm.processing || publishableMatches.length === 0} onClick={publishAll} className={`${primary} !bg-primary`}>{bulkForm.processing ? 'Publishing...' : `Publish ${activeCompetition.name}`}</button> : null}</div></div>
            {needsTime.length ? <section className="overflow-hidden rounded-sm border border-accent/50 bg-accent/10"><header className="flex items-center justify-between gap-3 border-b border-accent/50 px-4 py-4 sm:px-5"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-accent-foreground">Needs a time</p><h2 className="mt-1 text-xl font-bold text-foreground">Bracket matches waiting for a slot</h2></div><span className="text-sm font-bold text-accent-foreground">{needsTime.length}</span></header>{needsTime.map((match) => <MatchRow key={match.id} event={event} match={match} readOnly={readOnly} onEdit={setEditor} />)}</section> : null}
            {Object.keys(dayGroups).length ? <div className="space-y-5">{Object.entries(dayGroups).map(([day, dayMatches]) => <section key={day} className="overflow-hidden border border-border border-t-2 border-t-accent bg-surface"><header className="flex items-center justify-between border-b border-border bg-surface-muted px-4 py-4 sm:px-5"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-accent-foreground">Match day</p><h2 className="mt-1 text-2xl font-bold text-foreground">{formatDay(`${day}T12:00:00`)}</h2></div><span className="text-sm font-bold text-muted">{dayMatches.length} match{dayMatches.length === 1 ? '' : 'es'}</span></header>{dayMatches.map((match) => <MatchRow key={match.id} event={event} match={match} readOnly={readOnly} onEdit={setEditor} />)}</section>)}</div> : null}
            {!needsTime.length && !Object.keys(dayGroups).length ? <AdminEmptyState title={visibleMatches.length ? 'No matches match these filters' : 'Publish a bracket first'} description={visibleMatches.length ? 'Try clearing a filter or choose another day.' : 'Once a bracket is ready, its contests will appear here for scheduling.'} /> : null}
            <p className="text-sm text-muted">Saved times stay private until you publish them. Bracket matches are the source of truth, so there is no separate add-game action.</p>
        </div></ScheduleWorkspace></main>
        {editor ? <ScheduleSheet key={editor.id} event={event} match={editor} venues={venues} statuses={statuses} readOnly={readOnly} onClose={() => setEditor(null)} /> : null}
        {venuesOpen ? <VenueSheet event={event} venues={venues} readOnly={readOnly} onClose={() => setVenuesOpen(false)} /> : null}
    </AuthenticatedLayout>;
}
