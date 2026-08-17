import AppIcon from '@/Components/AppIcon';
import InputError from '@/Components/InputError';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SportWorkspaceShell from '@/Components/Sports/SportWorkspaceShell';
import React from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const input = 'mt-1 min-h-11 w-full rounded-lg border-[#C6D0CC] bg-white text-sm text-[#17333F] shadow-xs focus:border-[#0B536D] focus:ring-[#0B536D]';
const primary = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-[#0B536D] px-4 text-sm font-bold text-white transition hover:bg-[#083E52] disabled:cursor-not-allowed disabled:opacity-50';
const quiet = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-[#B8C3C0] bg-white px-4 text-sm font-bold text-[#0B536D] transition hover:border-[#0B536D] disabled:cursor-not-allowed disabled:opacity-50';

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
        neutral: 'bg-[#EEF2F0] text-[#52636A]',
        amber: 'bg-[#FFF5D9] text-[#806000]',
        green: 'bg-[#E5F6ED] text-[#12623F]',
        blue: 'bg-[#E4F1F5] text-[#0B536D]',
    };
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-[0.08em] ${tones[tone]}`}>{children}</span>;
}

function Drawer({ titleId, label, title, onClose, children }) {
    return <div className="fixed inset-0 z-50">
        <button type="button" aria-label="Close drawer" onClick={onClose} className="absolute inset-0 bg-[#17212B]/45" />
        <aside role="dialog" aria-modal="true" aria-labelledby={titleId} className="absolute inset-y-0 right-0 w-full max-w-xl overflow-y-auto bg-[#F4F5F2] shadow-2xl">
            <header className="sticky top-0 z-10 flex items-start justify-between border-b border-[#DDE2E0] bg-white p-5">
                <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#B07E00]">{label}</p><h2 id={titleId} className="mt-1 font-serif text-2xl font-bold text-[#17212B]">{title}</h2></div>
                <button type="button" autoFocus onClick={onClose} aria-label="Close drawer" className="grid size-10 place-items-center rounded-lg focus-visible:ring-2 focus-visible:ring-[#D5A21F]"><AppIcon name="close" /></button>
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
            {!readOnly ? <form onSubmit={save} className="rounded-xl border border-[#DDE2E0] bg-white p-5">
                <h3 className="font-serif text-lg font-bold">Add a venue</h3>
                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                    <label className="text-xs font-bold uppercase">Name<input required value={form.data.name} onChange={(eventObject) => form.setData('name', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase">Short code<input value={form.data.code} onChange={(eventObject) => form.setData('code', eventObject.target.value)} className={input} /></label>
                    <label className="text-xs font-bold uppercase sm:col-span-2">Location<input value={form.data.location} onChange={(eventObject) => form.setData('location', eventObject.target.value)} className={input} /></label>
                </div>
                <button type="submit" disabled={form.processing} className={`mt-4 ${primary}`}>Add venue</button>
                <InputError message={form.errors.name} className="mt-2" />
            </form> : null}
            <section className="rounded-xl border border-[#DDE2E0] bg-white p-5">
                <div className="flex items-center justify-between"><h3 className="font-serif text-lg font-bold">Available venues</h3><span className="text-sm text-[#68767E]">{venues.length}</span></div>
                <div className="mt-3 divide-y divide-[#E6EAE8]">
                    {venues.length ? venues.map((venue) => <div key={venue.id} className="py-4 first:pt-0 last:pb-0"><div className="flex items-center justify-between gap-3"><div><p className="font-bold text-[#17333F]">{venue.name}</p><p className="mt-1 text-sm text-[#68767E]">{venue.location || 'Location not added'}</p></div><div className="flex items-center gap-2"><Pill tone={venue.is_active ? 'green' : 'neutral'}>{venue.is_active ? 'Active' : 'Inactive'}</Pill>{!readOnly ? <button type="button" onClick={() => setEditing(venue)} className="text-sm font-bold text-[#0B536D] underline">Edit</button> : null}</div></div></div>) : <p className="py-4 text-sm text-[#68767E]">No venues yet. Add the first one above.</p>}
                </div>
            </section>
            {editing ? <form onSubmit={update} className="rounded-xl border border-[#DDE2E0] bg-white p-5">
                <div className="flex justify-between gap-3"><h3 className="font-serif text-lg font-bold">Edit {editing.name}</h3><button type="button" onClick={() => setEditing(null)} className="text-sm font-bold text-[#0B536D]">Cancel</button></div>
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
            <p className="text-sm font-semibold text-[#17333F]">{match.teams.join(' vs ')}</p>
            {!existing ? <div className="rounded-lg bg-[#E5F6ED] p-3 text-sm text-[#12623F]">This schedules the existing bracket match. It does not create another game.</div> : null}
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

    return <article className="grid gap-3 border-b border-[#E6EAE8] px-4 py-4 last:border-b-0 sm:grid-cols-[6.5rem_minmax(0,1fr)_auto] sm:items-center sm:px-5">
        <div><p className="text-lg font-black text-[#17333F]">{formatTime(schedule?.starts_at)}</p><p className="mt-1 text-xs font-bold uppercase tracking-[0.08em] text-[#68767E]">{schedule?.ends_at ? `Until ${formatTime(schedule.ends_at)}` : 'End time not set'}</p></div>
        <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><Pill tone="blue">{match.round}</Pill><Pill>{match.division}</Pill>{schedule?.publication && !schedule.has_unpublished_changes ? <Pill tone="green">Published</Pill> : <Pill tone="amber">Draft</Pill>}{schedule?.has_unpublished_changes && schedule?.publication ? <Pill tone="amber">Changes waiting</Pill> : null}</div><h3 className="mt-2 truncate font-serif text-xl font-bold text-[#17212B]">{match.teams[0]} <span className="text-[#B07E00]">vs</span> {match.teams[1]}</h3><p className="mt-1 truncate text-sm text-[#68767E]">{schedule?.venue || 'Venue to be announced'} / {schedule?.title || 'Bracket match not scheduled'}</p></div>
        <div className="flex flex-wrap gap-2 sm:justify-end">{schedule ? <button type="button" disabled={readOnly} onClick={() => onEdit(match)} className={quiet}>Edit</button> : <button type="button" disabled={readOnly} onClick={() => onEdit(match)} className={primary}>Set time</button>}{canPublish ? <button type="button" disabled={readOnly || publishForm.processing} onClick={publish} className={`${primary} !bg-[#16845B]`}>{publishForm.processing ? 'Publishing...' : 'Publish'}</button> : null}</div>
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

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">{isScoped ? `${scope.competition}${scope.division ? ` / ${scope.division}` : ''}` : event.name}</p><h1 className="font-serif text-2xl font-bold text-[#17212B]">Schedule &amp; Publishing</h1></div>}>
        <Head title={`Schedule & Publishing / ${event.name}`} />
        <main className="min-h-[calc(100vh-9rem)] bg-background px-4 py-6 sm:px-6 lg:px-10"><ScheduleWorkspace event={event} competitions={competitions} scope={scope}><div className="mx-auto flex max-w-[90rem] flex-col gap-6">
            {flash?.status ? <div role="status" className="border-l-4 border-[#16845B] bg-white px-5 py-4 text-sm font-medium text-[#12623F]">{flash.status}</div> : null}
            <section className="rounded-2xl bg-[#0B2E4F] px-5 py-6 text-white shadow-xs sm:px-8"><div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-[#E7C865]">{isScoped ? 'Schedule' : 'All sports'}</p><h2 className="mt-2 font-serif text-3xl font-bold sm:text-4xl">Match-day schedule</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-white/75">Schedule the matches already in your bracket, then publish the times your students will see.</p></div><div className="flex flex-wrap gap-2"><span className="rounded-lg bg-white/10 px-3 py-2 text-sm font-bold">{visibleMatches.length} matches</span><span className="rounded-lg bg-[#D5A21F] px-3 py-2 text-sm font-bold text-[#17212B]">{needsTime.length} need a time</span></div></div></section>
            <section className="rounded-xl border border-[#DDE2E0] bg-white p-4 shadow-xs"><div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {!isScoped ? <label className="text-xs font-bold uppercase">Sport<select value={sport} onChange={(eventObject) => { setSport(eventObject.target.value); setDivision('all'); }} className={input}><option value="all">All sports</option>{competitions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label> : null}
                <label className="text-xs font-bold uppercase">Division<select value={division} onChange={(eventObject) => setDivision(eventObject.target.value)} className={input}><option value="all">All divisions</option>{visibleDivisions.map((item) => <option key={item.id} value={item.id}>{item.competition ? `${item.competition} / ` : ''}{item.name}</option>)}</select></label>
                <label className="text-xs font-bold uppercase">Status<select value={status} onChange={(eventObject) => setStatus(eventObject.target.value)} className={input}><option value="all">All statuses</option>{statuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></label>
                <label className="text-xs font-bold uppercase">Visibility<select value={publication} onChange={(eventObject) => setPublication(eventObject.target.value)} className={input}><option value="all">All visibility</option><option value="public">Published</option><option value="draft">Needs publishing</option></select></label>
                <label className="text-xs font-bold uppercase">Day<select value={date} onChange={(eventObject) => setDate(eventObject.target.value)} className={input}><option value="all">All days</option><option value="unscheduled">Needs a time</option>{dates.map((item) => <option key={item} value={item}>{formatDay(`${item}T12:00:00`)}</option>)}</select></label>
            </div></section>
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#B07E00]">Operations agenda</p><h2 className="mt-1 font-serif text-2xl font-bold text-[#17212B]">{isScoped ? scope.competition : (activeCompetition?.name || 'Event programme')}</h2></div><div className="flex flex-wrap gap-2">{!readOnly ? <button type="button" onClick={() => setVenuesOpen(true)} className={quiet}><AppIcon name="map-pin" className="size-4" />Manage venues</button> : null}{!readOnly && activeCompetition ? <button type="button" disabled={bulkForm.processing || publishableMatches.length === 0} onClick={publishAll} className={`${primary} !bg-[#16845B]`}>{bulkForm.processing ? 'Publishing...' : `Publish ${activeCompetition.name}`}</button> : null}</div></div>
            {needsTime.length ? <section className="overflow-hidden rounded-xl border border-[#E9D58A] bg-[#FFFDF4]"><header className="flex items-center justify-between gap-3 border-b border-[#E9D58A] px-4 py-4 sm:px-5"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#B07E00]">Needs a time</p><h2 className="mt-1 font-serif text-xl font-bold text-[#17212B]">Bracket matches waiting for a slot</h2></div><span className="text-sm font-bold text-[#806000]">{needsTime.length}</span></header>{needsTime.map((match) => <MatchRow key={match.id} event={event} match={match} readOnly={readOnly} onEdit={setEditor} />)}</section> : null}
            {Object.keys(dayGroups).length ? <div className="space-y-5">{Object.entries(dayGroups).map(([day, dayMatches]) => <section key={day} className="overflow-hidden rounded-xl border border-[#DDE2E0] border-l-4 border-l-[#D5A21F] bg-white shadow-xs"><header className="flex items-center justify-between border-b border-[#DDE2E0] bg-[#F8FAF9] px-4 py-4 sm:px-5"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#B07E00]">Match day</p><h2 className="mt-1 font-serif text-2xl font-bold text-[#17212B]">{formatDay(`${day}T12:00:00`)}</h2></div><span className="text-sm font-bold text-[#68767E]">{dayMatches.length} match{dayMatches.length === 1 ? '' : 'es'}</span></header>{dayMatches.map((match) => <MatchRow key={match.id} event={event} match={match} readOnly={readOnly} onEdit={setEditor} />)}</section>)}</div> : null}
            {!needsTime.length && !Object.keys(dayGroups).length ? <div className="rounded-xl border border-dashed border-[#B8C3C0] bg-white p-12 text-center"><h2 className="font-serif text-2xl font-bold text-[#17212B]">{visibleMatches.length ? 'No matches match these filters' : 'Publish a bracket first'}</h2><p className="mx-auto mt-2 max-w-lg text-sm leading-6 text-[#68767E]">{visibleMatches.length ? 'Try clearing a filter or choose another day.' : 'Once a bracket is ready, its contests will appear here for scheduling.'}</p></div> : null}
            <p className="text-sm text-[#68767E]">Saved times stay private until you publish them. Bracket matches are the source of truth, so there is no separate add-game action.</p>
        </div></ScheduleWorkspace></main>
        {editor ? <ScheduleSheet key={editor.id} event={event} match={editor} venues={venues} statuses={statuses} readOnly={readOnly} onClose={() => setEditor(null)} /> : null}
        {venuesOpen ? <VenueSheet event={event} venues={venues} readOnly={readOnly} onClose={() => setVenuesOpen(false)} /> : null}
    </AuthenticatedLayout>;
}
