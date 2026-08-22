import React from 'react';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { AdminMasthead } from '@/Components/Admin/AdminSurface';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SlideOver from '@/Components/SlideOver';
import { adminStyles } from '@/Support/adminStyles';

const surface = adminStyles.section;
const primary = adminStyles.primaryAction;
const quiet = adminStyles.secondaryAction;
const input = `mt-1 ${adminStyles.field}`;
const stateTone = {
    active: 'bg-[#E8F5EE] text-[#167044]',
    draft: 'bg-[#FFF5D8] text-[#8B6300]',
    locked: 'bg-[#EAF2F1] text-[#0B536D]',
    blocked: 'bg-[#FBE7E5] text-[#9D2D24]',
    not_started: 'bg-[#F0F2F1] text-[#68767E]',
    unassigned: 'bg-[#FFF5D8] text-[#8B6300]',
};
const stateLabel = {
    active: 'Active',
    draft: 'Needs review',
    locked: 'Approved',
    blocked: 'Blocked',
    not_started: 'Not started',
    unassigned: 'Not rostered',
};

function Errors({ errors }) {
    return Object.values(errors).map((error) => <p key={error} className="text-sm font-semibold text-danger">{error}</p>);
}

function countLabel(value, singular, plural = `${singular}s`) {
    return `${value} ${value === 1 ? singular : plural}`;
}

function StateBadge({ state }) {
    return <span className={`inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[0.12em] ${stateTone[state] || stateTone.not_started}`}>{stateLabel[state] || state}</span>;
}

export function ProfileForm({ event, departments, participant, coach = false, onClose }) {
    const form = useForm(participant ? {
        event_delegation_id: participant.event_delegation_id || '',
        display_name: participant.display_name || '',
        given_name: participant.given_name || '',
        family_name: participant.family_name || '',
        student_number: participant.student_number || '',
        email: participant.email || '',
        phone: participant.phone || '',
        private_notes: participant.private_notes || '',
        is_active: participant.is_active,
        is_competitor: coach ? false : participant.is_competitor,
    } : {
        event_delegation_id: departments[0]?.id || '',
        display_name: '',
        given_name: '',
        family_name: '',
        student_number: '',
        email: '',
        phone: '',
        private_notes: '',
        is_active: true,
        is_competitor: !coach,
    });
    const submit = (eventTarget) => {
        eventTarget.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (participant) form.patch(route('admin.participants.update', [event.id, participant.id]), options);
        else form.post(route('admin.participants.store', event.id), options);
    };

    return <form onSubmit={submit} className="space-y-4">
        <p className="text-sm text-[#68767E]">Shared event profile; no login account is created.</p>
        <label className="block text-sm font-bold">Department
            <select required value={form.data.event_delegation_id} onChange={(eventTarget) => form.setData('event_delegation_id', eventTarget.target.value)} className={input} disabled={Boolean(participant)}>
                {departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
            </select>
        </label>
        <label className="block text-sm font-bold">Display name
            <input required value={form.data.display_name} onChange={(eventTarget) => form.setData('display_name', eventTarget.target.value)} className={input} />
        </label>
        <div className="grid gap-3 sm:grid-cols-2">
            <label className="block text-sm font-bold">Student number<input value={form.data.student_number} onChange={(eventTarget) => form.setData('student_number', eventTarget.target.value)} className={input} /></label>
            <label className="block text-sm font-bold">Email<input type="email" value={form.data.email} onChange={(eventTarget) => form.setData('email', eventTarget.target.value)} className={input} /></label>
        </div>
        <details>
            <summary className="cursor-pointer text-sm font-semibold text-[#0B536D]">Other details</summary>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <label className="block text-sm font-bold">Given name<input value={form.data.given_name} onChange={(eventTarget) => form.setData('given_name', eventTarget.target.value)} className={input} /></label>
                <label className="block text-sm font-bold">Family name<input value={form.data.family_name} onChange={(eventTarget) => form.setData('family_name', eventTarget.target.value)} className={input} /></label>
                <label className="block text-sm font-bold">Phone<input value={form.data.phone} onChange={(eventTarget) => form.setData('phone', eventTarget.target.value)} className={input} /></label>
                <label className="block text-sm font-bold sm:col-span-2">Private notes<textarea rows="3" value={form.data.private_notes} onChange={(eventTarget) => form.setData('private_notes', eventTarget.target.value)} className={input} /></label>
            </div>
        </details>
        <button className={primary} disabled={form.processing}>{participant ? 'Save profile' : `Create ${coach ? 'coach' : 'player'} profile`}</button>
        <Errors errors={form.errors} />
    </form>;
}

export function CoachPanel({ event, coach, departments, competitions, onClose }) {
    const divisions = competitions.flatMap((competition) => competition.divisions.map((division) => ({ ...division, competition: competition.name })));
    const families = [...new Set(competitions.map((competition) => competition.programme_family).filter(Boolean))];
    const form = useForm({ coach_type: 'student_coach', title: 'Coach', scope_type: 'competition_division', scope_key: divisions[0]?.id || '', notes: '' });
    const save = (eventTarget) => {
        eventTarget.preventDefault();
        form.post(route('admin.coach-assignments.store', [event.id, coach.id]), { preserveScroll: true });
    };
    const choices = form.data.scope_type === 'competition_division'
        ? divisions.map((division) => ({ value: division.id, label: `${division.competition} — ${division.name}` }))
        : families.map((family) => ({ value: family, label: family.replaceAll('_', ' ') }));

    return <div className="space-y-6">
        <ProfileForm event={event} departments={departments} participant={coach} coach onClose={() => {}} />
        <section className="border-t border-[#CFD6D3] pt-5">
            <h3 className="font-serif text-xl font-bold">Coverage assignments</h3>
            <div className="mt-3 divide-y divide-[#E6EAE8]">
                {coach.assignments?.filter((assignment) => assignment.is_active).map((assignment) => <div key={assignment.id} className="flex items-center justify-between gap-3 py-3">
                    <div><strong className="text-sm">{assignment.title}</strong><p className="text-xs capitalize text-[#68767E]">{assignment.coach_type.replaceAll('_', ' ')} · {assignment.scope_type.replaceAll('_', ' ')} · {assignment.scope_key.replaceAll('_', ' ')}</p></div>
                    <button type="button" onClick={() => router.patch(route('admin.coach-assignments.deactivate', [event.id, assignment.id]), {}, { preserveScroll: true })} className="text-xs font-bold text-red-700">Remove</button>
                </div>)}
                {!coach.assignments?.some((assignment) => assignment.is_active) ? <p className="py-3 text-sm text-[#68767E]">No active coverage yet.</p> : null}
            </div>
            <form onSubmit={save} className="mt-4 space-y-3 border border-[#CFD6D3] bg-[#FBFCFA] p-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <label className="text-sm font-bold">Coach type<select value={form.data.coach_type} onChange={(eventTarget) => form.setData('coach_type', eventTarget.target.value)} className={input}><option value="student_coach">Student coach</option><option value="faculty_coach">Faculty coach</option></select></label>
                    <label className="text-sm font-bold">Title<select value={form.data.title} onChange={(eventTarget) => form.setData('title', eventTarget.target.value)} className={input}><option>Coach</option><option>Head Coach</option><option>Assistant Coach</option><option>Trainer</option><option>Team Captain</option></select></label>
                    <label className="text-sm font-bold">Coverage<select value={form.data.scope_type} onChange={(eventTarget) => form.setData({ ...form.data, scope_type: eventTarget.target.value, scope_key: eventTarget.target.value === 'competition_division' ? divisions[0]?.id || '' : families[0] || '' })} className={input}><option value="competition_division">Exact division</option><option value="programme_family">Programme family</option></select></label>
                    <label className="text-sm font-bold">Assignment<select required value={form.data.scope_key} onChange={(eventTarget) => form.setData('scope_key', eventTarget.target.value)} className={input}>{choices.map((choice) => <option key={choice.value} value={choice.value}>{choice.label}</option>)}</select></label>
                </div>
                <button className={primary} disabled={form.processing || !form.data.scope_key}>Add assignment</button>
                <Errors errors={form.errors} />
            </form>
        </section>
        <button type="button" className={quiet} onClick={onClose}>Done</button>
    </div>;
}

function DirectoryFilters({ view, values, setValues, onSubmit, onClear }) {
    const set = (key, value) => setValues((current) => ({ ...current, [key]: value }));
    return <section className={`${surface} p-4`}>
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Directory filters</p><p className="mt-1 text-sm text-[#68767E]">Search the event without loading every person at once.</p></div>
            <button type="button" onClick={onClear} className="text-xs font-bold text-[#0B536D] underline underline-offset-4">Clear filters</button>
        </div>
        <form onSubmit={onSubmit} className="grid gap-3 md:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_auto]">
            <label className="block text-sm font-bold"><span>Search players or coaches</span><input value={values.q} onChange={(eventTarget) => set('q', eventTarget.target.value)} className={input} placeholder={`Search ${view === 'players' ? 'name or student number' : 'coach name'}`} /></label>
            <label className="block text-sm font-bold"><span>Profile</span><select value={values.status} onChange={(eventTarget) => set('status', eventTarget.target.value)} className={input}><option value="all">All profiles</option><option value="active">Active profiles</option><option value="inactive">Inactive profiles</option></select></label>
            {view === 'players' ? <label className="block text-sm font-bold"><span>Roster state</span><select value={values.roster} onChange={(eventTarget) => set('roster', eventTarget.target.value)} className={input}><option value="">All players</option><option value="assigned">Rostered players</option><option value="unassigned">Not yet rostered</option></select></label> : <div />}
            <div className="flex items-end"><button className={primary}>Apply</button></div>
        </form>
    </section>;
}

function DepartmentRail({ departments, selectedId, view, onSelect }) {
    return <aside className={`${surface} h-fit overflow-hidden lg:sticky lg:top-5`}>
        <div className="border-b border-[#CFD6D3] bg-[#FBFCFA] px-4 py-4"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Departments</p><p className="mt-1 text-sm text-[#68767E]">Choose a college or unit.</p></div>
        <div className="divide-y divide-[#E6EAE8]">
            {departments.map((department) => {
                const selected = String(department.id) === String(selectedId);
                const count = department.counts?.[view === 'players' ? 'players' : 'coaches'] || 0;
                return <button key={department.id} type="button" onClick={() => onSelect(department.id)} aria-pressed={selected} className={`relative w-full px-4 py-4 text-left transition hover:bg-[#F2F7F6] ${selected ? 'bg-[#EEF6F5]' : 'bg-white'}`}>
                    {selected ? <span className="absolute inset-y-0 left-0 w-1 bg-[#E4B84A]" /> : null}
                    <span className="flex items-start justify-between gap-3"><span><strong className="block text-sm text-[#17212B]">{department.name}</strong><span className="mt-1 block text-xs text-[#68767E]">{countLabel(count, view === 'players' ? 'player' : 'coach')} · {countLabel(department.counts?.rosters || 0, 'roster')}</span></span><span className="font-serif text-lg font-bold text-[#0B536D]">{department.abbreviation || '—'}</span></span>
                </button>;
            })}
            {departments.length === 0 ? <p className="p-4 text-sm text-[#68767E]">No departments match these filters.</p> : null}
        </div>
    </aside>;
}

function SportTabs({ sports, selectedId, view, onSelect }) {
    return <div className="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Sports">
        {sports.map((sport) => {
            const selected = String(sport.id) === String(selectedId);
            const count = sport.counts?.[view === 'players' ? 'players' : 'coaches'] || 0;
            return <button key={sport.id} type="button" role="tab" aria-selected={selected} onClick={() => onSelect(sport.id)} className={`min-w-[11rem] rounded-md border px-4 py-3 text-left transition ${selected ? 'border-[#0B536D] bg-[#0B536D] text-white' : 'border-[#CFD6D3] bg-white text-[#17212B] hover:border-[#0B536D]'}`}>
                <span className="block text-sm font-bold">{sport.name}</span><span className={`mt-1 block text-xs ${selected ? 'text-[#D7ECE8]' : 'text-[#68767E]'}`}>{countLabel(count, view === 'players' ? 'player' : 'coach')} · {countLabel(sport.counts?.rosters || 0, 'roster')}</span>
            </button>;
        })}
        {sports.length === 0 ? <p className="rounded-md border border-dashed border-[#CFD6D3] px-4 py-4 text-sm text-[#68767E]">No sports match these filters.</p> : null}
    </div>;
}

function PreviewTable({ preview, view, onSelect }) {
    if (!preview) return null;
    const people = preview.people || [];
    return <div className="border-t border-[#E6EAE8] bg-[#FBFCFA] px-4 py-4 sm:px-5">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2"><div><p className="text-xs font-bold uppercase tracking-[0.12em] text-[#0B536D]">{view === 'players' ? 'Player preview' : 'Support preview'}</p><p className="mt-1 text-xs text-[#68767E]">Showing {Math.min(preview.total, preview.limit)} of {preview.total} people.</p></div><span className="text-xs font-bold text-[#68767E]">Click a person to manage the profile</span></div>
        {people.length > 0 ? <div className="overflow-hidden rounded-md border border-[#CFD6D3] bg-white"><div className="divide-y divide-[#E6EAE8]">{people.map((person) => <button key={person.id} type="button" onClick={() => onSelect(person)} className="flex w-full items-center justify-between gap-4 px-4 py-3 text-left transition hover:bg-[#F2F7F6]"><span className="min-w-0"><strong className="block truncate text-sm text-[#17212B]">{person.display_name}</strong><span className="mt-1 block text-xs text-[#68767E]">{view === 'players' ? person.student_number || 'No student number' : `${person.assignments?.filter((assignment) => assignment.is_active).length || 0} active assignments`}</span></span><span className="shrink-0 text-xs font-bold text-[#0B536D]">Manage</span></button>)}</div></div> : <p className="rounded-md border border-dashed border-[#CFD6D3] px-4 py-4 text-sm text-[#68767E]">No people match this preview.</p>}
        {preview.has_more ? <p className="mt-3 text-xs font-semibold text-[#68767E]">There are {preview.total - preview.limit} more people. Open the roster for the complete list.</p> : null}
    </div>;
}

function RosterRow({ event, department, sport, division, roster, view, previewState, onPreview, onSelectPerson }) {
    const previewOpen = Boolean(previewState?.open);
    const isPreviewable = roster.id !== null || (view === 'players' && roster.state === 'unassigned');
    const rosterHref = `${route('admin.sports.show', [event.id, sport.id])}?tab=rosters&division=${division.id}&department=${department.id}`;
    return <div className={`${surface} overflow-hidden`}>
        <div className="flex flex-col gap-4 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h4 className="truncate text-sm font-bold text-[#17212B]">{roster.name}</h4><StateBadge state={roster.state} /></div><p className="mt-1 text-xs text-[#68767E]">{roster.code ? `${roster.code} · ` : ''}{countLabel(roster.counts?.players || 0, 'player')} · {countLabel(roster.counts?.coaches || 0, 'coach', 'coaches')}</p></div>
            <div className="flex shrink-0 flex-wrap gap-2"><button type="button" className={quiet} onClick={() => onPreview(roster, division, sport)} disabled={!isPreviewable || previewState?.loading}>{previewState?.loading ? 'Loading…' : view === 'players' ? 'Preview players' : 'Preview support'}</button>{roster.id !== null ? <a className={primary} href={rosterHref}>Open roster</a> : null}</div>
        </div>
        {previewState?.error ? <div className="border-t border-[#E6EAE8] bg-[#FFF8F7] px-4 py-3 text-sm text-red-700"><p>{previewState.error}</p><button type="button" onClick={() => onPreview(roster, division, sport)} className="mt-2 text-xs font-bold underline">Retry preview</button></div> : null}
        {previewOpen ? <PreviewTable preview={previewState.data} view={view} onSelect={onSelectPerson} /> : null}
    </div>;
}

function DivisionPanel({ event, department, sport, division, view, previews, onPreview, onSelectPerson }) {
    return <section className="space-y-3" aria-labelledby={`division-${division.id}`}>
        <div className="flex flex-wrap items-end justify-between gap-2"><div><p className="text-xs font-bold uppercase tracking-[0.12em] text-[#0B536D]">Event category</p><h3 id={`division-${division.id}`} className="font-serif text-2xl font-bold text-[#17212B]">{sport.name} · {division.name}</h3></div><p className="text-xs text-[#68767E]">{countLabel(division.counts?.rosters || 0, 'roster')} · {countLabel(division.counts?.players || 0, 'player')} · {countLabel(division.counts?.coaches || 0, 'coach', 'coaches')}</p></div>
        {division.rosters.map((roster) => <RosterRow key={roster.id || `${department.id}-${sport.id}-${division.id}`} event={event} department={department} sport={sport} division={division} roster={roster} view={view} previewState={previews[roster.id || `unassigned:${department.id}`]} onPreview={onPreview} onSelectPerson={onSelectPerson} />)}
    </section>;
}

export default function ParticipantDirectory({ event, departments = [], competitions = [], directory_summary: directorySummary = { departments: [], totals: {} }, selection = {}, initialView = 'players', filters = {} }) {
    const view = initialView === 'coaches' ? 'coaches' : 'players';
    const [values, setValues] = useState({ q: filters.q || '', status: filters.directory_status || 'all', roster: filters.directory_roster || '' });
    const [selectedDepartmentId, setSelectedDepartmentId] = useState(selection.department || directorySummary.departments?.[0]?.id || '');
    const [selectedSportId, setSelectedSportId] = useState(selection.sport || '');
    const [selectedDivisionId, setSelectedDivisionId] = useState(selection.division || '');
    const [previews, setPreviews] = useState({});
    const [profile, setProfile] = useState(null);
    const [createOpen, setCreateOpen] = useState(false);

    const activeDepartment = useMemo(() => directorySummary.departments?.find((department) => String(department.id) === String(selectedDepartmentId)) || directorySummary.departments?.[0] || null, [directorySummary.departments, selectedDepartmentId]);
    const activeSport = useMemo(() => activeDepartment?.sports?.find((sport) => String(sport.id) === String(selectedSportId)) || activeDepartment?.sports?.[0] || null, [activeDepartment, selectedSportId]);
    const activeDivision = useMemo(() => activeSport?.divisions?.find((division) => String(division.id) === String(selectedDivisionId)) || activeSport?.divisions?.[0] || null, [activeSport, selectedDivisionId]);

    useEffect(() => {
        if (activeDepartment && String(activeDepartment.id) !== String(selectedDepartmentId)) setSelectedDepartmentId(activeDepartment.id);
        if (activeSport && String(activeSport.id) !== String(selectedSportId)) setSelectedSportId(activeSport.id);
        if (activeDivision && String(activeDivision.id) !== String(selectedDivisionId)) setSelectedDivisionId(activeDivision.id);
    }, [activeDepartment, activeSport, activeDivision, selectedDepartmentId, selectedSportId, selectedDivisionId]);

    const buildParams = (overrides = {}) => {
        const params = {
            view,
            directory_department: selectedDepartmentId,
            directory_sport: selectedSportId,
            directory_division: selectedDivisionId,
            directory_entry: '',
            q: values.q,
            directory_status: values.status !== 'all' ? values.status : '',
            directory_roster: view === 'players' ? values.roster : '',
            ...overrides,
        };
        return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== null && value !== undefined && value !== ''));
    };

    const navigate = (overrides = {}, options = {}) => router.get(route('admin.registrations.index', event.id), buildParams(overrides), { preserveState: true, preserveScroll: true, replace: true, ...options });
    const selectDepartment = (id) => {
        const next = directorySummary.departments.find((department) => String(department.id) === String(id));
        const nextSport = next?.sports?.[0];
        const nextDivision = nextSport?.divisions?.[0];
        setSelectedDepartmentId(String(id));
        setSelectedSportId(nextSport?.id || '');
        setSelectedDivisionId(nextDivision?.id || '');
        setPreviews({});
        navigate({ directory_department: id, directory_sport: nextSport?.id || '', directory_division: nextDivision?.id || '' });
    };
    const selectSport = (id) => {
        const next = activeDepartment?.sports?.find((sport) => String(sport.id) === String(id));
        const nextDivision = next?.divisions?.[0];
        setSelectedSportId(String(id));
        setSelectedDivisionId(nextDivision?.id || '');
        setPreviews({});
        navigate({ directory_sport: id, directory_division: nextDivision?.id || '' });
    };
    const selectDivision = (id) => {
        setSelectedDivisionId(String(id));
        setPreviews({});
        navigate({ directory_division: id });
    };
    const applyFilters = (eventTarget) => {
        eventTarget.preventDefault();
        setPreviews({});
        navigate({ q: values.q, directory_status: values.status !== 'all' ? values.status : '', directory_roster: view === 'players' ? values.roster : '' });
    };
    const clearFilters = () => {
        const next = { q: '', status: 'all', roster: '' };
        setValues(next);
        setPreviews({});
        navigate({ q: '', directory_status: '', directory_roster: '' });
    };
    const changeView = (nextView) => {
        router.get(route('admin.registrations.index', event.id), { ...buildParams({ view: nextView, directory_roster: '' }), view: nextView }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const previewKey = (roster, department = activeDepartment) => roster.id || `unassigned:${department?.id}`;
    const loadPreview = async (roster, division, sport) => {
        if (!activeDepartment) return;
        const key = previewKey(roster);
        const current = previews[key];
        if (current?.data) {
            setPreviews((state) => ({ ...state, [key]: { ...current, open: !current.open } }));
            return;
        }
        setPreviews((state) => ({ ...state, [key]: { loading: true, open: true, data: null, error: null } }));
        const url = new URL(route('admin.registrations.directory-preview', event.id), window.location.origin);
        const params = {
            view,
            department: activeDepartment.id,
            sport: sport?.id || '',
            division: division?.id || '',
            entry: roster.id || '',
            q: values.q,
            status: values.status,
            roster: view === 'players' ? values.roster : '',
        };
        Object.entries(params).forEach(([param, value]) => { if (value !== null && value !== undefined && value !== '') url.searchParams.set(param, value); });
        try {
            const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('The preview could not be loaded.');
            const data = await response.json();
            setPreviews((state) => ({ ...state, [key]: { loading: false, open: true, data, error: null } }));
        } catch (error) {
            setPreviews((state) => ({ ...state, [key]: { loading: false, open: true, data: null, error: error.message || 'The preview could not be loaded.' } }));
        }
    };
    const selectedCount = activeDepartment?.counts?.[view === 'players' ? 'players' : 'coaches'] || 0;
    const showingUnassigned = view === 'players' && values.roster === 'unassigned' && activeDepartment?.counts?.unassigned > 0;
    const unassignedRoster = { id: null, name: 'Not yet rostered', state: 'unassigned', counts: { players: activeDepartment?.counts?.unassigned || 0, coaches: 0 } };

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">{event.name}</p><h1 className="font-serif text-2xl font-bold">Players &amp; Coaches</h1></div>}>
        <main className={adminStyles.page}><div className="mx-auto max-w-[96rem] space-y-5">
            <AdminMasthead eyebrow="Event-wide roster directory" title="Find the right roster faster" description="Choose a department, then move through its sports and event categories before opening a roster or person." actions={<button type="button" className={primary} onClick={() => setCreateOpen(true)} disabled={event.archived}>Add {view === 'players' ? 'player' : 'coach'}</button>} />
            <nav className="flex flex-wrap gap-2" aria-label="Directory views"><button type="button" onClick={() => changeView('players')} className={view === 'players' ? primary : quiet}>Players</button><button type="button" onClick={() => changeView('coaches')} className={view === 'coaches' ? primary : quiet}>Coaches &amp; support</button></nav>
            <DirectoryFilters view={view} values={values} setValues={setValues} onSubmit={applyFilters} onClear={clearFilters} />
            <div className="grid gap-5 lg:grid-cols-[18rem_minmax(0,1fr)]">
                <DepartmentRail departments={directorySummary.departments || []} selectedId={activeDepartment?.id} view={view} onSelect={selectDepartment} />
                <section className="min-w-0 space-y-5">
                    {activeDepartment ? <>
                        <header className="border border-border border-l-4 border-l-accent bg-surface px-5 py-5 sm:px-6"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Selected department</p><div className="mt-1 flex flex-wrap items-end justify-between gap-3"><h3 className="text-3xl font-bold text-foreground">{activeDepartment.name}</h3><span className="text-sm font-bold text-primary">{activeDepartment.abbreviation || 'Event delegation'}</span></div><div className="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-muted"><span>{countLabel(selectedCount, view === 'players' ? 'player' : 'coach')}</span><span>{countLabel(activeDepartment.counts?.rosters || 0, 'roster')}</span><span>{countLabel(activeDepartment.sports?.length || 0, 'sport')}</span>{view === 'players' && activeDepartment.counts?.unassigned ? <span>{countLabel(activeDepartment.counts.unassigned, 'player')} not rostered</span> : null}</div></header>
                        {showingUnassigned ? <section className="space-y-3"><div><p className="text-xs font-bold uppercase tracking-[0.12em] text-[#0B536D]">Roster state</p><h3 className="mt-1 font-serif text-2xl font-bold">Players not yet rostered</h3></div><RosterRow event={event} department={activeDepartment} sport={{ id: '', name: 'Unassigned' }} division={{ id: '', name: 'Event-wide' }} roster={unassignedRoster} view={view} previewState={previews[previewKey(unassignedRoster)]} onPreview={loadPreview} onSelectPerson={setProfile} /></section> : <>
                            <section className="space-y-3"><div><p className="text-xs font-bold uppercase tracking-[0.12em] text-[#0B536D]">Sports</p><p className="mt-1 text-sm text-[#68767E]">Choose a sport to see its event categories and department rosters.</p></div><SportTabs sports={activeDepartment.sports || []} selectedId={activeSport?.id} view={view} onSelect={selectSport} /></section>
                            {activeSport ? <section className="space-y-4"><div className="flex flex-wrap items-center justify-between gap-2"><div><p className="text-xs font-bold uppercase tracking-[0.12em] text-primary">Event categories</p><h3 className="mt-1 text-2xl font-bold">{activeSport.name}</h3></div><span className="text-xs text-muted">{countLabel(activeSport.divisions?.length || 0, 'event category', 'event categories')}</span></div><div className="flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label={`${activeSport.name} event categories`}>{activeSport.divisions?.map((division) => <button key={division.id} type="button" role="tab" aria-selected={String(division.id) === String(activeDivision?.id)} onClick={() => selectDivision(division.id)} className={`rounded-sm border px-4 py-2 text-xs font-bold transition-colors ${String(division.id) === String(activeDivision?.id) ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-surface text-muted hover:border-primary'}`}>{division.name}</button>)}</div>{activeDivision ? <DivisionPanel event={event} department={activeDepartment} sport={activeSport} division={activeDivision} view={view} previews={previews} onPreview={loadPreview} onSelectPerson={setProfile} /> : <p className="border border-dashed border-border px-5 py-6 text-sm text-muted">No event categories match these filters.</p>}</section> : <section className={`${surface} p-8 text-center`}><h3 className="text-xl font-bold">No sports match these filters</h3><p className="mt-2 text-sm text-muted">Clear one or more filters to see this department’s sports.</p></section>}
                        </>}
                    </> : <section className={`${surface} p-8 text-center`}><h3 className="text-xl font-bold">No departments match these filters</h3><p className="mt-2 text-sm text-muted">Clear one or more filters or add a department participant.</p></section>}
                </section>
            </div>
        </div></main>
        <SlideOver show={Boolean(profile)} title={view === 'players' ? 'Shared player profile' : 'Coach profile and assignments'} onClose={() => setProfile(null)}>{profile ? (view === 'players' ? <ProfileForm event={event} departments={departments} participant={profile} onClose={() => setProfile(null)} /> : <CoachPanel event={event} coach={profile} departments={departments} competitions={competitions} onClose={() => setProfile(null)} />) : null}</SlideOver>
        <SlideOver show={createOpen} title={`Add ${view === 'players' ? 'player' : 'coach'}`} onClose={() => setCreateOpen(false)}><ProfileForm event={event} departments={departments} coach={view === 'coaches'} onClose={() => setCreateOpen(false)} /></SlideOver>
    </AuthenticatedLayout>;
}
