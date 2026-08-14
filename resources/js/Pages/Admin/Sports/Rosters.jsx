import React from 'react';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Link from '@/Components/PrefetchLink';
import SlideOver from '@/Components/SlideOver';
import RosterAddPlayers from './RosterAddPlayers';
import RosterPlayerList from './RosterPlayerList';

const surface = 'border border-[#CFD6D3] bg-white';
const primary = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-md bg-[#0B536D] px-4 text-sm font-bold text-white transition hover:bg-[#083e52] disabled:cursor-not-allowed disabled:opacity-50';
const quiet = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-md border border-[#B8C3C0] bg-white px-4 text-sm font-bold text-[#0B536D] transition hover:border-[#0B536D] disabled:cursor-not-allowed disabled:opacity-50';
const input = 'mt-1 block w-full rounded-md border border-[#B8C3C0] bg-white px-3 py-2.5 text-sm text-[#17212B] focus:border-[#0B536D] focus:ring-[#0B536D]';

const playerRoles = ['student_athlete', 'reserve'];
const emptyParticipants = [];

function rosterUrl(eventId, sportId, divisionId, departmentId = null) {
    const base = route('admin.sports.show', [eventId, sportId]);
    const params = new URLSearchParams({ tab: 'rosters', division: divisionId });
    if (departmentId) params.set('department', departmentId);
    return `${base}?${params.toString()}`;
}

const stateCopy = {
    not_started: 'Not started', review: 'Needs review', ready: 'Ready to lock', blocked: 'Blocked', locked: 'Locked',
};

function DepartmentIndex({ event, sport, division, departments, selectedId, attentionOnly }) {
    const visible = departments.filter((department) => !attentionOnly || ['review', 'blocked', 'not_started'].includes(department.state));
    return <section className={`${surface} overflow-hidden`} aria-labelledby="department-rosters-title">
        <div className="border-b border-[#CFD6D3] px-4 py-4 sm:px-5">
            <div className="flex items-center justify-between gap-3">
                <div><h2 id="department-rosters-title" className="font-serif text-xl font-bold">Departments</h2><p className="mt-1 text-sm text-[#68767E]">{departments.length} department{departments.length === 1 ? '' : 's'} / choose one team sheet</p></div>
                <span className="text-xs font-semibold text-[#68767E]">{visible.length} shown</span>
            </div>
        </div>
        <div className="divide-y divide-[#E6EAE8]">
            {visible.map((department) => <Link key={department.id} href={rosterUrl(event.id, sport.id, division.id, department.id)} preserveScroll className={`group relative flex min-h-[5.8rem] items-center gap-3 px-4 py-3 transition hover:bg-[#F2F7F6] ${selectedId === department.id ? 'bg-[#EAF2F1]' : ''}`}>
                <span className="absolute inset-y-3 left-0 w-1" style={{ backgroundColor: department.color || '#0B536D' }} aria-hidden="true" />
                <span className="min-w-0 flex-1 pl-2"><strong className="block text-sm text-[#17212B]">{department.abbreviation || department.name}</strong><span className="mt-1 block text-xs text-[#68767E]">{department.summary}</span>{department.attention ? <span className="mt-1 block truncate text-xs font-semibold text-[#68767E]">{department.attention}</span> : null}</span>
                <span className={`text-xs font-bold ${department.state === 'locked' || department.state === 'ready' ? 'text-emerald-700' : department.state === 'blocked' ? 'text-red-700' : department.state === 'not_started' ? 'text-[#68767E]' : 'text-[#9A6E00]'}`}>{stateCopy[department.state]}</span>
                <span aria-hidden="true" className="text-lg text-[#68767E] transition group-hover:text-[#0B536D]">&gt;</span>
            </Link>)}
            {visible.length === 0 ? <p className="p-5 text-sm text-[#68767E]">No departments match this view.</p> : null}
        </div>
    </section>;
}

function updateSection(form, section, field, value) {
    form.setData(section, { ...form.data[section], [field]: value });
}

function CoachSupportForm({ event, division, departmentId, departmentName, sportName, archived, onClose }) {
    const form = useForm({
        display_name: '', given_name: '', family_name: '', student_number: '', email: '', phone: '', private_notes: '',
        coach_type: 'student_coach', title: 'Coach', notes: '',
    });
    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.post(route('admin.roster-coach-support.store', [event.id, division.id, departmentId]), { preserveScroll: true, onSuccess: onClose });
    };
    return <form onSubmit={submit} className="space-y-5">
        <section className="rounded-lg border border-[#CFD6D3] bg-[#F8FAF8] p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-[#0B536D]">Assignment</p><p className="mt-1 text-sm font-semibold text-[#17212B]">{departmentName} <span className="mx-1 text-[#9AA5A8]" aria-hidden="true">/</span> {sportName} {division.name}</p><p className="mt-1 text-xs text-[#68767E]">This coverage is fixed to the current roster.</p></section>
        <section className="space-y-3"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Person</p><label className="block text-sm font-bold">Display name<input required value={form.data.display_name} onChange={(eventObject) => form.setData('display_name', eventObject.target.value)} className={input} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Student number<input value={form.data.student_number} onChange={(eventObject) => form.setData('student_number', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Email<input type="email" value={form.data.email} onChange={(eventObject) => form.setData('email', eventObject.target.value)} className={input} /></label></div><details><summary className="cursor-pointer text-sm font-semibold text-[#0B536D]">Other profile details</summary><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Given name<input value={form.data.given_name} onChange={(eventObject) => form.setData('given_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Family name<input value={form.data.family_name} onChange={(eventObject) => form.setData('family_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Phone<input value={form.data.phone} onChange={(eventObject) => form.setData('phone', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold sm:col-span-2">Private notes<textarea rows="2" value={form.data.private_notes} onChange={(eventObject) => form.setData('private_notes', eventObject.target.value)} className={input} /></label></div></details></section>
        <section className="space-y-3 border-t border-[#E6EAE8] pt-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Role</p><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Type<select value={form.data.coach_type} onChange={(eventObject) => form.setData('coach_type', eventObject.target.value)} className={input}><option value="student_coach">Student coach</option><option value="faculty_coach">Faculty coach</option></select></label><label className="block text-sm font-bold">Title<select value={form.data.title} onChange={(eventObject) => form.setData('title', eventObject.target.value)} className={input}><option>Coach</option><option>Head Coach</option><option>Assistant Coach</option><option>Trainer</option><option>Team Captain</option></select></label></div><label className="block text-sm font-bold">Assignment notes<textarea rows="2" value={form.data.notes} onChange={(eventObject) => form.setData('notes', eventObject.target.value)} className={input} /></label></section>
        {Object.values(form.errors).map((error) => <p key={error} className="text-sm font-semibold text-red-700">{error}</p>)}
        <button type="submit" className={`${primary} w-full sm:w-auto`} disabled={archived || form.processing}>{form.processing ? 'Adding coach or staff...' : 'Add coach or staff'}</button>
    </form>;
}

function ManagePanel({ event, entry, participant, options, archived, onClose }) {
    const membership = participant.membership || {};
    const form = useForm({
        profile: {
            display_name: participant.display_name || '', given_name: participant.given_name || '', family_name: participant.family_name || '', student_number: participant.student_number || '', email: participant.email || '', phone: participant.phone || '', private_notes: participant.private_notes || '',
        },
        membership: { role: membership.role || 'student_athlete', is_active: membership.is_active ?? true, notes: membership.notes || '' },
    });
    const exceptionForm = useForm({ type: 'withdrawn', reason: '' });
    const capabilities = participant.capabilities || {};
    const canEditMembership = capabilities.can_edit_membership ?? (!archived && entry.status !== 'locked');
    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.put(route('admin.roster-players.update', [event.id, entry.id, participant.id]), { preserveScroll: true, onSuccess: onClose });
    };
    const recordException = (eventObject) => { eventObject.preventDefault(); exceptionForm.post(route('admin.participation-exceptions.store', [event.id, entry.id, participant.id]), { preserveScroll: true, onSuccess: onClose }); };
    return <form onSubmit={submit} className="space-y-5">
        <p className="text-sm leading-6 text-[#68767E]">Update the shared profile and this team sheet together. Shared profile deactivation stays in the event player directory.</p>
        <section className="space-y-3"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Profile</p><label className="block text-sm font-bold">Display name<input required value={form.data.profile.display_name} onChange={(eventObject) => updateSection(form, 'profile', 'display_name', eventObject.target.value)} className={input} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Student number<input value={form.data.profile.student_number} onChange={(eventObject) => updateSection(form, 'profile', 'student_number', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Email<input type="email" value={form.data.profile.email} onChange={(eventObject) => updateSection(form, 'profile', 'email', eventObject.target.value)} className={input} /></label></div><details><summary className="cursor-pointer text-sm font-semibold text-[#0B536D]">Other profile details</summary><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Given name<input value={form.data.profile.given_name} onChange={(eventObject) => updateSection(form, 'profile', 'given_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Family name<input value={form.data.profile.family_name} onChange={(eventObject) => updateSection(form, 'profile', 'family_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Phone<input value={form.data.profile.phone} onChange={(eventObject) => updateSection(form, 'profile', 'phone', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold sm:col-span-2">Private notes<textarea rows="3" value={form.data.profile.private_notes} onChange={(eventObject) => updateSection(form, 'profile', 'private_notes', eventObject.target.value)} className={input} /></label></div></details></section>
        <section className="space-y-3 border-t border-[#E6EAE8] pt-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Roster place</p><label className="block text-sm font-bold">Role<select value={form.data.membership.role} onChange={(eventObject) => updateSection(form, 'membership', 'role', eventObject.target.value)} className={input} disabled={!canEditMembership}>{options.roster_roles.filter((role) => playerRoles.includes(role.value)).map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}</select></label><label className="block text-sm font-bold">Notes<textarea rows="2" value={form.data.membership.notes} onChange={(eventObject) => updateSection(form, 'membership', 'notes', eventObject.target.value)} className={input} disabled={!canEditMembership} /></label><label className="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={Boolean(form.data.membership.is_active)} onChange={(eventObject) => updateSection(form, 'membership', 'is_active', eventObject.target.checked)} disabled={!canEditMembership} className="size-4 rounded border-[#B8C3C0] text-[#0B536D] focus:ring-[#D5A21F]" />Active and cleared to compete</label></section>
        {Object.values(form.errors).map((error) => <p key={error} className="text-sm font-semibold text-red-700">{error}</p>)}
        <button type="submit" className={primary} disabled={archived || form.processing}>Save changes</button>
        {(entry.status === 'locked' || entry.capabilities?.published) && membership.is_active ? <section className="space-y-3 border-t border-[#E6EAE8] pt-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#9A3030]">Participation exception</p><p className="text-sm text-[#68767E]">Use only for a confirmed withdrawal or disqualification. The approved roster snapshot remains unchanged.</p><div className="space-y-3"><select value={exceptionForm.data.type} onChange={(eventObject) => exceptionForm.setData('type', eventObject.target.value)} className={input}><option value="withdrawn">Withdrawn</option><option value="disqualified">Disqualified</option></select><textarea required rows="3" value={exceptionForm.data.reason} onChange={(eventObject) => exceptionForm.setData('reason', eventObject.target.value)} className={input} placeholder="Required reason" />{Object.values(exceptionForm.errors).map((error) => <p key={error} className="text-sm font-semibold text-red-700">{error}</p>)}<button type="button" onClick={recordException} className={quiet} disabled={exceptionForm.processing}>Record exception</button></div></section> : null}
    </form>;
}

function RosterDetail({ event, sport, division, selected, options, archived, departmentName = null }) {
    const [addOpen, setAddOpen] = useState(false);
    const [reopenOpen, setReopenOpen] = useState(false);
    const [lockReviewOpen, setLockReviewOpen] = useState(false);
    const [coachSupportOpen, setCoachSupportOpen] = useState(false);
    const [profile, setProfile] = useState(null);
    const entry = selected?.entry;
    const statusForm = useForm({ status: '', reason: '', roster_review_confirmed: false });

    const participants = selected?.participants ?? emptyParticipants;
    const activeMembers = useMemo(() => participants.filter((participant) => participant.membership?.is_active), [participants]);
    const players = useMemo(() => activeMembers.filter((participant) => playerRoles.includes(participant.membership?.role)), [activeMembers]);
    const coaches = selected?.coaches || [];
    const history = useMemo(() => participants.filter((participant) => participant.membership && !participant.membership.is_active), [participants]);
    const counts = selected?.counts || {};
    const entryCapabilities = entry?.capabilities || {};
    const statusErrors = [...new Set(Object.values(statusForm.errors).filter(Boolean))];
    const rosterReady = selected?.readiness?.ready === true;
    const readinessBlockers = selected?.readiness?.blockers || [];
    const readinessNotices = selected?.readiness?.notices || [];
    const playerCount = counts.active_players ?? players.length;
    const playerMaximum = entry?.limits?.maximum;
    const isLocked = entry?.status === 'locked';
    const canAddPlayers = !archived && !isLocked && entryCapabilities.can_add_players !== false;
    const canReview = !archived && !isLocked && rosterReady && entryCapabilities.can_lock !== false;

    const lock = (eventObject) => {
        eventObject.preventDefault();
        statusForm.clearErrors();
        statusForm.transform((data) => ({ ...data, status: 'locked', reason: '', roster_review_confirmed: true }))
            .patch(route('admin.entries.status', [event.id, entry.id]), { preserveScroll: true, onSuccess: () => setLockReviewOpen(false) });
    };
    const reopen = (eventObject) => {
        eventObject.preventDefault();
        statusForm.clearErrors();
        statusForm.transform((data) => ({ ...data, status: 'active' }))
            .patch(route('admin.entries.status', [event.id, entry.id]), { preserveScroll: true, onSuccess: () => setReopenOpen(false) });
    };

    if (!entry) {
        const rosterLabel = `${sport.name} ${division.name}`;
        return <section className="max-w-3xl rounded-xl border border-[#CFD6D3] bg-white p-5 shadow-[0_8px_24px_rgba(17,38,51,0.05)] sm:p-7"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Start roster</p><h3 className="mt-2 font-serif text-2xl font-bold">{selected?.department_id ? `Start the ${rosterLabel} roster` : 'Select a department'}</h3><p className="mt-2 max-w-xl text-sm leading-6 text-[#68767E]">{selected?.department_id ? `Create an empty team sheet for ${departmentName || 'this department'}, then add its players.` : 'Choose a department before starting its team sheet.'}</p>{selected?.department_id ? <form className="mt-5" onSubmit={(eventObject) => { eventObject.preventDefault(); router.post(route('admin.department-rosters.store', [event.id, division.id, selected.department_id]), {}, { preserveScroll: true }); }}><button className={`${primary} w-full sm:w-auto`} disabled={archived}>Start roster</button></form> : null}</section>;
    }

    return <div className="space-y-5">
        <RosterPlayerList
            title="Players"
            description="Students listed for this team."
            members={players}
            countLabel={playerMaximum === null || playerMaximum === undefined ? `${playerCount} players` : `${playerCount} of ${playerMaximum}`}
            onManage={setProfile}
            disabled={archived}
            emptyMessage="No players have been added to this roster yet."
            emphasis
            action={players.length && canAddPlayers ? <button type="button" className={`${primary} w-full sm:w-auto`} onClick={() => setAddOpen(true)}>Add player</button> : null}
            emptyAction={!players.length && canAddPlayers ? <button type="button" className={`${primary} w-full sm:w-auto`} onClick={() => setAddOpen(true)}>Add first player</button> : null}
        />
        {isLocked ? <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-end">{statusForm.processing ? <p className="text-sm font-semibold text-[#0B536D]">Updating roster status...</p> : null}{statusErrors.length ? <div role="alert" className="text-sm text-red-800"><p className="font-bold">The roster status was not updated.</p><ul className="mt-1 list-disc pl-5">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul></div> : null}{!archived && entryCapabilities.can_reopen ? <button type="button" className={`${quiet} w-full sm:w-auto`} onClick={() => setReopenOpen(true)}>Reopen roster</button> : null}</div> : null}
        <details className="overflow-hidden border-y border-[#CFD6D3] bg-white"><summary className="cursor-pointer list-none px-5 py-4 text-sm font-bold text-[#17212B] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D5A21F]">Team staff (optional) - {coaches.length}</summary><div className="border-t border-[#E6EAE8] p-5"><div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-[#68767E]">Student and faculty coaches connected to this team.</p><div className="flex flex-wrap gap-2"><button type="button" className={primary} onClick={() => setCoachSupportOpen(true)} disabled={archived || entryCapabilities.published}>Add coach or staff</button><Link href={`${route('admin.departments.show', [event.id, selected.department_id])}?view=coaches`} className={quiet}>Manage all team staff</Link></div></div>{coaches.length ? <div className="mt-4 divide-y divide-[#E6EAE8] border-y border-[#E6EAE8]">{coaches.map((coach) => <div key={`${coach.id}-${coach.coach_type}-${coach.scope_key}`} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><strong className="text-sm">{coach.display_name}</strong><p className="mt-1 text-xs text-[#68767E]">{coach.title} / {coach.coach_type.replaceAll('_', ' ')}</p></div><span className="text-xs font-semibold text-[#0B536D]">{coach.scope_type === 'programme_family' ? 'Shared assignment' : 'This division'}</span></div>)}</div> : <p className="mt-4 text-sm text-[#68767E]">No team staff are assigned to this roster yet. Add the first coach or staff person when ready.</p>}</div></details>
        <details className="overflow-hidden border-y border-[#CFD6D3] bg-white"><summary className="cursor-pointer list-none px-5 py-4 text-sm font-bold text-[#17212B] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D5A21F]">Roster history - {history.length} inactive</summary><div className="border-t border-[#CFD6D3]"><RosterPlayerList title="Inactive players" description="Previous roster places remain available for review and restoration." members={history} onManage={setProfile} disabled={archived} emptyMessage="No inactive roster history." /></div></details>
        {!isLocked ? <section className="border-t-2 border-[#D5A21F] pt-5" aria-labelledby="before-approval-title"><div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div className="max-w-2xl"><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Before approval</p><h3 id="before-approval-title" className="mt-1 font-serif text-xl font-bold">{rosterReady ? 'Ready for review' : `${readinessBlockers.length} item${readinessBlockers.length === 1 ? '' : 's'} to finish`}</h3>{readinessBlockers.length ? <ul className="mt-3 space-y-2 text-sm text-[#6B3131]">{readinessBlockers.map((blocker) => <li key={blocker} className="border-l-2 border-[#B94A48] pl-3">{blocker}</li>)}</ul> : <p className="mt-2 text-sm text-emerald-700">All roster requirements are complete.</p>}{archived ? <p className="mt-3 text-sm text-[#68767E]">Archived events are read-only.</p> : entryCapabilities.published ? <p className="mt-3 text-sm text-[#68767E]">This roster is read-only after the draw is published.</p> : null}{statusForm.processing ? <p className="mt-3 text-sm font-semibold text-[#0B536D]">Updating roster status...</p> : null}{statusErrors.length ? <div role="alert" className="mt-3 text-sm text-red-800"><p className="font-bold">The roster status was not updated.</p><ul className="mt-1 list-disc pl-5">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul></div> : null}</div><button type="button" className={`${primary} w-full sm:w-auto`} onClick={() => setLockReviewOpen(true)} disabled={!canReview || statusForm.processing}>Review roster</button></div></section> : null}
        <SlideOver show={lockReviewOpen} title="Review roster" onClose={() => setLockReviewOpen(false)}><form onSubmit={lock} className="space-y-5"><div><p className="text-sm leading-6 text-[#68767E]">Confirm the team sheet before creating its approved roster snapshot.</p><dl className="mt-4 grid grid-cols-2 gap-3"><div className="border border-[#CFD6D3] p-3"><dt className="text-xs uppercase text-[#68767E]">Players</dt><dd className="mt-1 text-xl font-bold">{players.length}</dd></div><div className="border border-[#CFD6D3] p-3"><dt className="text-xs uppercase text-[#68767E]">Team staff</dt><dd className="mt-1 text-xl font-bold">{coaches.length}</dd></div></dl></div>{readinessNotices.length ? <section className="border-l-2 border-[#D5A21F] pl-4"><h3 className="text-sm font-bold text-[#17212B]">Good to know</h3><ul className="mt-2 space-y-1 text-sm text-[#68767E]">{readinessNotices.map((notice) => <li key={notice}>{notice}</li>)}</ul></section> : null}<label className="flex items-start gap-3 border border-[#D5A21F] bg-[#FFFDF5] p-4 text-sm font-semibold"><input required type="checkbox" checked={statusForm.data.roster_review_confirmed} onChange={(eventObject) => statusForm.setData('roster_review_confirmed', eventObject.target.checked)} className="mt-0.5 rounded border-[#B8C3C0] text-[#0B536D] focus:ring-[#D5A21F]" />I confirm that the roster details and required external documents were reviewed.</label>{statusErrors.length ? <ul role="alert" className="list-disc space-y-1 pl-5 text-sm font-semibold text-red-700">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul> : null}<button className={primary} disabled={!statusForm.data.roster_review_confirmed || statusForm.processing}>{statusForm.processing ? 'Approving roster...' : 'Approve and lock roster'}</button></form></SlideOver>
        <SlideOver show={addOpen} title="Add players" onClose={() => setAddOpen(false)}><RosterAddPlayers event={event} entry={entry} departmentId={selected.department_id} participants={participants} roles={options.roster_roles} archived={archived} onClose={() => setAddOpen(false)} /></SlideOver>
        <SlideOver show={coachSupportOpen} title="Add coach or staff" onClose={() => setCoachSupportOpen(false)}><CoachSupportForm event={event} division={division} departmentId={selected.department_id} departmentName={departmentName} sportName={sport.name} archived={archived || entryCapabilities.published} onClose={() => setCoachSupportOpen(false)} /></SlideOver>
        <SlideOver show={reopenOpen} title="Reopen roster" onClose={() => setReopenOpen(false)}><form onSubmit={reopen} className="space-y-4"><p className="text-sm leading-6 text-[#68767E]">Reopening allows roster corrections. The lock history stays recorded and any preview draw may need regeneration.</p><label className="block text-sm font-bold">Reason<textarea required rows="4" value={statusForm.data.reason} onChange={(eventObject) => statusForm.setData('reason', eventObject.target.value)} className={input} /></label>{statusErrors.length ? <ul role="alert" className="list-disc space-y-1 pl-5 text-sm font-semibold text-red-700">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul> : null}<button className={primary} disabled={statusForm.processing}>{statusForm.processing ? 'Reopening roster...' : 'Reopen roster'}</button></form></SlideOver>
        {profile ? <SlideOver show title={`Manage ${profile.display_name}`} onClose={() => setProfile(null)}><ManagePanel key={profile.id} event={event} entry={entry} participant={profile} options={options} archived={archived} onClose={() => setProfile(null)} /></SlideOver> : null}
    </div>;
}

export default function Rosters({ event, sport, division, selectedDepartment, workspace, options, archived, departmentName = null }) {
    const [attentionOnly, setAttentionOnly] = useState(false);
    if (!division) return <section className={`${surface} p-6`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Players &amp; Rosters</p><h2 className="mt-2 font-serif text-2xl font-bold">Choose a division</h2><p className="mt-2 text-sm text-[#68767E]">Pick a division above to see its departments and team sheets.</p></section>;
    const departments = workspace?.departments || [];
    const departmentId = selectedDepartment?.id ?? selectedDepartment;
    const selectedDepartmentRecord = selectedDepartment && typeof selectedDepartment === 'object'
        ? selectedDepartment
        : departments.find((department) => String(department.id) === String(departmentId));
    const resolvedDepartmentName = departmentName || selectedDepartmentRecord?.name || selectedDepartmentRecord?.abbreviation || null;

    if (departmentId !== null && departmentId !== undefined && departmentId !== '') {
        return <RosterDetail event={event} sport={sport} division={division} selected={workspace?.selected} options={options} archived={archived} departmentName={resolvedDepartmentName} />;
    }

    return <div className="space-y-5"><div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">Players &amp; Rosters</p><h2 className="mt-1 font-serif text-2xl font-bold">{division.name}</h2><p className="mt-1 text-sm text-[#68767E]">Choose a department, then manage its team sheet.</p></div><label className="flex items-center gap-2 text-sm font-semibold text-[#68767E]"><input type="checkbox" checked={attentionOnly} onChange={(eventObject) => setAttentionOnly(eventObject.target.checked)} className="rounded border-[#B8C3C0] text-[#0B536D] focus:ring-[#D5A21F]" />Departments needing work</label></div><div className="grid gap-5 lg:grid-cols-[minmax(18rem,0.72fr)_minmax(0,1.45fr)]"><DepartmentIndex event={event} sport={sport} division={division} departments={departments} selectedId={selectedDepartment} attentionOnly={attentionOnly} /><RosterDetail event={event} sport={sport} division={division} selected={workspace?.selected} options={options} archived={archived} /></div></div>;
}
