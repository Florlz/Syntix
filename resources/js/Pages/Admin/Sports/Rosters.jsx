import React from 'react';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import { departmentPalette } from '@/Support/departmentColors';
import SlideOver from '@/Components/SlideOver';
import RosterAddPlayers from './RosterAddPlayers';
import RosterPlayerList from './RosterPlayerList';
import { adminStyles } from '@/Support/adminStyles';

const surface = adminStyles.section;
const primary = adminStyles.primaryAction;
const quiet = adminStyles.secondaryAction;
const input = `mt-1 ${adminStyles.field}`;

const playerRoles = ['student_athlete', 'reserve'];
const emptyParticipants = [];

function rosterUrl(eventId, sportId, divisionId, departmentId = null) {
    const base = route('admin.sports.show', [eventId, sportId]);
    const params = new URLSearchParams({ tab: 'rosters', division: divisionId });
    if (departmentId) params.set('department', departmentId);
    return `${base}?${params.toString()}`;
}

const stateCopy = {
    not_started: 'Not started', review: 'Needs review', ready: 'Ready for approval', blocked: 'Blocked', locked: 'Approved',
};

function departmentStatus(department) {
    if (department.state === 'locked') return 'Approved';
    if (department.state === 'ready') return 'Ready for approval';
    if (department.state === 'review') return 'Needs attention';
    if (department.state === 'blocked') return 'Blocked';
    if (department.state === 'not_started') return 'Not started';
    return stateCopy[department.state] || 'Draft';
}

function statusMatches(department, filter) {
    if (filter === 'all') return true;
    if (filter === 'needs_attention') return ['review', 'blocked'].includes(department.state);
    if (filter === 'draft') return ['not_started', 'draft'].includes(department.state);
    if (filter === 'ready') return department.state === 'ready';
    if (filter === 'locked') return department.state === 'locked';
    return department.state === filter;
}

function DepartmentIndex({ event, sport, division, departments, selectedId, query = '', statusFilter = 'all' }) {
    const visible = departments.filter((department) => {
        const text = `${department.abbreviation || ''} ${department.name || ''} ${department.summary || ''}`.toLowerCase();
        return text.includes(query.trim().toLowerCase()) && statusMatches(department, statusFilter);
    });

    return <section className={`${surface} overflow-hidden`} aria-labelledby="team-rosters-title">
        <div className="hidden grid-cols-[minmax(14rem,1.4fr)_minmax(8rem,0.7fr)_minmax(9rem,0.8fr)_auto] gap-4 border-b border-border bg-surface-muted px-5 py-3 text-xs font-bold uppercase tracking-[0.12em] text-muted sm:grid">
            <span id="team-rosters-title">Team</span><span>Players</span><span>Team sheet</span><span className="sr-only">Action</span>
        </div>
        <div className="divide-y divide-border">
            {visible.map((department) => {
                const locked = department.state === 'locked';
                const palette = departmentPalette(department.color, department.id || department.abbreviation || department.name);
                const paletteStyle = { '--department-accent': palette.accent, '--department-tint': palette.tint, '--department-foreground': palette.foreground };
                return <Link key={department.id} href={rosterUrl(event.id, sport.id, division.id, department.id)} preserveScroll style={paletteStyle} className={`group relative grid min-h-[5.5rem] gap-3 px-4 py-4 transition hover:bg-surface-muted sm:grid-cols-[minmax(14rem,1.4fr)_minmax(8rem,0.7fr)_minmax(9rem,0.8fr)_auto] sm:items-center sm:gap-4 sm:px-5 ${selectedId === department.id ? 'bg-primary/5' : ''}`}>
                    <span className="absolute inset-y-3 left-0 w-1 bg-[var(--department-accent)]" aria-hidden="true" />
                    <span className="flex min-w-0 items-start gap-3 pl-2"><span aria-hidden="true" className="grid size-8 shrink-0 place-items-center rounded-md bg-[var(--department-tint)] text-[10px] font-black uppercase tracking-[0.08em] text-[var(--department-foreground)]">{(department.abbreviation || department.name || 'D').slice(0, 3)}</span><span className="min-w-0"><strong className="block text-sm text-foreground">{department.abbreviation || department.name}</strong><span className="mt-1 block truncate text-xs text-muted">{department.name}</span><span className="mt-1 block truncate text-xs text-muted sm:hidden">{department.summary || 'Team sheet not started'}</span></span></span>
                    <span className="text-sm text-muted sm:text-foreground">{department.summary || 'No players added'}</span>
                    <span className="inline-flex w-fit items-center rounded-full border border-border bg-surface-muted px-2.5 py-1 text-xs font-bold text-foreground">{departmentStatus(department)}</span>
                    <span className="inline-flex items-center gap-1 text-sm font-bold text-primary">{locked ? 'View' : 'Manage'}<AppIcon name="arrow-right" className="size-4 transition-transform group-hover:translate-x-0.5" /></span>
                </Link>;
            })}
            {visible.length === 0 ? <p className="p-6 text-sm text-muted">No teams match this search or status.</p> : null}
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
        <section className="rounded-sm border border-border bg-surface-muted p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-primary">Assignment</p><p className="mt-1 text-sm font-semibold text-foreground">{departmentName} <span className="mx-1 text-muted" aria-hidden="true">/</span> {sportName} {division.name}</p><p className="mt-1 text-xs text-muted">This coverage is fixed to the current roster.</p></section>
        <section className="space-y-3"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Person</p><label className="block text-sm font-bold">Display name<input required value={form.data.display_name} onChange={(eventObject) => form.setData('display_name', eventObject.target.value)} className={input} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Student number<input value={form.data.student_number} onChange={(eventObject) => form.setData('student_number', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Email<input type="email" value={form.data.email} onChange={(eventObject) => form.setData('email', eventObject.target.value)} className={input} /></label></div><details><summary className="cursor-pointer text-sm font-semibold text-primary">Other profile details</summary><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Given name<input value={form.data.given_name} onChange={(eventObject) => form.setData('given_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Family name<input value={form.data.family_name} onChange={(eventObject) => form.setData('family_name', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold">Phone<input value={form.data.phone} onChange={(eventObject) => form.setData('phone', eventObject.target.value)} className={input} /></label><label className="block text-sm font-bold sm:col-span-2">Private notes<textarea rows="2" value={form.data.private_notes} onChange={(eventObject) => form.setData('private_notes', eventObject.target.value)} className={input} /></label></div></details></section>
        <section className="space-y-3 border-t border-border pt-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Role</p><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Type<select value={form.data.coach_type} onChange={(eventObject) => form.setData('coach_type', eventObject.target.value)} className={input}><option value="student_coach">Student coach</option><option value="faculty_coach">Faculty coach</option></select></label><label className="block text-sm font-bold">Title<select value={form.data.title} onChange={(eventObject) => form.setData('title', eventObject.target.value)} className={input}><option>Coach</option><option>Head Coach</option><option>Assistant Coach</option><option>Trainer</option><option>Team Captain</option></select></label></div><label className="block text-sm font-bold">Assignment notes<textarea rows="2" value={form.data.notes} onChange={(eventObject) => form.setData('notes', eventObject.target.value)} className={input} /></label></section>
        {Object.values(form.errors).map((error) => <p key={error} className="text-sm font-semibold text-danger">{error}</p>)}
        <button type="submit" className={`${primary} w-full sm:w-auto`} disabled={archived || form.processing}>{form.processing ? 'Adding coach or staff...' : 'Add coach or staff'}</button>
    </form>;
}

function ManagePanel({ event, entry, participant, options, onClose }) {
    const membership = participant.membership || {};
    const form = useForm({
        profile: {
            display_name: participant.display_name || '', given_name: participant.given_name || '', family_name: participant.family_name || '', student_number: participant.student_number || '', email: participant.email || '', phone: participant.phone || '', private_notes: participant.private_notes || '',
        },
        membership: { role: membership.role || 'student_athlete', is_active: membership.is_active ?? true, notes: membership.notes || '' },
    });
    const exceptionForm = useForm({ type: 'withdrawn', reason: '' });
    const capabilities = participant.capabilities || {};
    const canEditProfile = capabilities.can_edit_profile !== false;
    const canEditMembership = capabilities.can_edit_membership === true;
    const canRecordException = capabilities.can_record_exception === true;
    const submit = (eventObject) => {
        eventObject.preventDefault();
        form.transform((data) => ({
            ...(canEditProfile ? { profile: data.profile } : {}),
            ...(canEditMembership ? { membership: data.membership } : {}),
        })).put(route('admin.roster-players.update', [event.id, entry.id, participant.id]), { preserveScroll: true, onSuccess: onClose });
    };
    const recordException = (eventObject) => { eventObject.preventDefault(); exceptionForm.post(route('admin.participation-exceptions.store', [event.id, entry.id, participant.id]), { preserveScroll: true, onSuccess: onClose }); };
    return <div className="space-y-5">
        <form onSubmit={submit} className="space-y-5">
            <p className="text-sm leading-6 text-muted">Update the shared profile and this team sheet together. Shared profile deactivation stays in the event player directory.</p>
            <section className="space-y-3"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Profile</p><label className="block text-sm font-bold">Display name<input required value={form.data.profile.display_name} onChange={(eventObject) => updateSection(form, 'profile', 'display_name', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label><div className="grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Student number<input value={form.data.profile.student_number} onChange={(eventObject) => updateSection(form, 'profile', 'student_number', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label><label className="block text-sm font-bold">Email<input type="email" value={form.data.profile.email} onChange={(eventObject) => updateSection(form, 'profile', 'email', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label></div><details><summary className="cursor-pointer text-sm font-semibold text-primary">Other profile details</summary><div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="block text-sm font-bold">Given name<input value={form.data.profile.given_name} onChange={(eventObject) => updateSection(form, 'profile', 'given_name', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label><label className="block text-sm font-bold">Family name<input value={form.data.profile.family_name} onChange={(eventObject) => updateSection(form, 'profile', 'family_name', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label><label className="block text-sm font-bold">Phone<input value={form.data.profile.phone} onChange={(eventObject) => updateSection(form, 'profile', 'phone', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label><label className="block text-sm font-bold sm:col-span-2">Private notes<textarea rows="3" value={form.data.profile.private_notes} onChange={(eventObject) => updateSection(form, 'profile', 'private_notes', eventObject.target.value)} className={input} disabled={!canEditProfile} /></label></div></details></section>
            <section className="space-y-3 border-t border-border pt-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Roster place</p><label className="block text-sm font-bold">Role<select value={form.data.membership.role} onChange={(eventObject) => updateSection(form, 'membership', 'role', eventObject.target.value)} className={input} disabled={!canEditMembership}>{options.roster_roles.filter((role) => playerRoles.includes(role.value)).map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}</select></label><label className="block text-sm font-bold">Notes<textarea rows="2" value={form.data.membership.notes} onChange={(eventObject) => updateSection(form, 'membership', 'notes', eventObject.target.value)} className={input} disabled={!canEditMembership} /></label><label className="flex items-center gap-2 text-sm font-semibold"><input type="checkbox" checked={Boolean(form.data.membership.is_active)} onChange={(eventObject) => updateSection(form, 'membership', 'is_active', eventObject.target.checked)} disabled={!canEditMembership} className="size-4 rounded-sm border-border text-primary focus:ring-accent" />Active and cleared to compete</label></section>
            {Object.values(form.errors).map((error) => <p key={error} className="text-sm font-semibold text-danger">{error}</p>)}
            <button type="submit" className={primary} disabled={(!canEditProfile && !canEditMembership) || form.processing}>Save changes</button>
        </form>
        {canRecordException ? <form onSubmit={recordException} className="space-y-3 border-t border-border pt-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-danger">Participation exception</p><p className="text-sm text-muted">Use only for a confirmed withdrawal or disqualification. The approved roster snapshot remains unchanged.</p><div className="space-y-3"><label className="block text-sm font-bold">Exception type<select value={exceptionForm.data.type} onChange={(eventObject) => exceptionForm.setData('type', eventObject.target.value)} className={input}><option value="withdrawn">Withdrawn</option><option value="disqualified">Disqualified</option></select></label><label className="block text-sm font-bold">Required reason<textarea required rows="3" value={exceptionForm.data.reason} onChange={(eventObject) => exceptionForm.setData('reason', eventObject.target.value)} className={input} placeholder="Explain the confirmed exception" /></label>{Object.values(exceptionForm.errors).map((error) => <p key={error} className="text-sm font-semibold text-danger">{error}</p>)}<button type="submit" className={quiet} disabled={exceptionForm.processing}>Record exception</button></div></form> : null}
    </div>;
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
        return <section className={`${surface} max-w-3xl rounded-sm p-5  sm:p-7`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Start team sheet</p><h3 className="mt-2 text-2xl font-bold text-foreground">{selected?.department_id ? `Start the ${rosterLabel} roster` : 'Select a team'}</h3><p className="mt-2 max-w-xl text-sm leading-6 text-muted">{selected?.department_id ? `Create an empty team sheet for ${departmentName || 'this team'}, then add its players.` : 'Choose a team before starting its team sheet.'}</p>{selected?.department_id ? <form className="mt-5" onSubmit={(eventObject) => { eventObject.preventDefault(); router.post(route('admin.department-rosters.store', [event.id, division.id, selected.department_id]), {}, { preserveScroll: true }); }}><button className={`${primary} w-full sm:w-auto`} disabled={archived}>Start team sheet</button></form> : null}</section>;
    }

    return <div className="flex flex-col gap-5">
        <section className={`${surface} rounded-sm p-5 sm:p-6`} aria-labelledby="roster-readiness-title">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="max-w-2xl"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Roster readiness</p><h3 id="roster-readiness-title" className="mt-1 text-2xl font-bold text-foreground">{isLocked ? 'Approved team sheet' : rosterReady ? 'Team sheet is ready for approval' : `${readinessBlockers.length || 1} item${readinessBlockers.length === 1 ? '' : 's'} to finish`}</h3><p className="mt-2 text-sm text-muted">{isLocked ? `Approved revision ${entry.approval_revision || '—'}. Normal roster editing is hidden while this approved snapshot is active.` : `${playerCount}${playerMaximum === null || playerMaximum === undefined ? '' : ` / ${playerMaximum}`} players${rosterReady ? ' · All roster requirements are complete.' : ''}`}</p></div>
                <span className={`inline-flex w-fit items-center rounded-full border px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] ${isLocked ? 'border-primary/30 bg-primary/10 text-primary' : rosterReady ? 'border-primary/30 bg-primary/10 text-primary' : 'border-accent/40 bg-accent/10 text-accent-foreground'}`}>{isLocked ? 'Approved' : rosterReady ? 'Ready for approval' : 'Needs attention'}</span>
            </div>
            {!isLocked && readinessBlockers.length ? <ul className="mt-4 flex flex-col gap-2 text-sm text-danger">{readinessBlockers.map((blocker) => <li key={blocker} className="border border-danger/30 bg-danger-surface px-3 py-2">{blocker}</li>)}</ul> : null}
            {!isLocked && !readinessBlockers.length ? <p className="mt-4 text-sm font-semibold text-primary">All roster requirements are complete.</p> : null}
            {archived ? <p className="mt-4 text-sm text-muted">Archived events are read-only.</p> : entryCapabilities.published && !isLocked ? <div className="mt-4 flex flex-col gap-3 rounded-sm border border-accent/40 bg-accent/10 p-4 text-sm text-muted sm:flex-row sm:items-center sm:justify-between"><p><strong className="text-foreground">Roster approved by the published bracket.</strong> The approved team sheet can no longer be edited.</p><Link href={route('admin.sports.tournament', [event.id, division.id])} className={quiet}>View bracket<AppIcon name="arrow-right" className="size-4" /></Link></div> : null}
            {statusForm.processing ? <p className="mt-4 text-sm font-semibold text-primary">Updating team sheet status...</p> : null}
            {statusErrors.length ? <div role="alert" className="mt-4 text-sm text-danger"><p className="font-bold">The team sheet status was not updated.</p><ul className="mt-1 list-disc pl-5">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul></div> : null}
            <div className="mt-5 flex flex-wrap gap-2">
                {!isLocked && !archived && !entryCapabilities.published && canReview ? <button type="button" className={primary} onClick={() => setLockReviewOpen(true)} disabled={statusForm.processing}>Approve team sheet</button> : null}
                {isLocked && !archived && entryCapabilities.can_reopen ? <button type="button" className={quiet} onClick={() => setReopenOpen(true)}>Edit approved team sheet</button> : null}
                {!isLocked && !rosterReady && canAddPlayers ? <button type="button" className={quiet} onClick={() => setAddOpen(true)}>+ Add players</button> : null}
            </div>
        </section>
        <RosterPlayerList
            title="Players"
            description="Players listed on this team sheet."
            members={players}
            countLabel={playerMaximum === null || playerMaximum === undefined ? `${playerCount} players` : `${playerCount} of ${playerMaximum}`}
            onManage={setProfile}
            emptyMessage="No players have been added to this roster yet."
            emphasis
            action={players.length && canAddPlayers ? <button type="button" className={`${primary} w-full sm:w-auto`} onClick={() => setAddOpen(true)}>Add player</button> : null}
            emptyAction={!players.length && canAddPlayers ? <button type="button" className={`${primary} w-full sm:w-auto`} onClick={() => setAddOpen(true)}>Add first player</button> : null}
        />
        <details className="overflow-hidden border-y border-border bg-surface"><summary className="cursor-pointer list-none px-5 py-4 text-sm font-bold text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D5A21F]">Team staff (optional) - {coaches.length}</summary><div className="border-t border-border p-5"><div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-muted">Student and faculty coaches connected to this team.</p><div className="flex flex-wrap gap-2"><button type="button" className={primary} onClick={() => setCoachSupportOpen(true)} disabled={archived || entryCapabilities.published}>Add coach or staff</button><Link href={`${route('admin.departments.show', [event.id, selected.department_id])}?view=coaches`} className={quiet}>Manage all team staff</Link></div></div>{coaches.length ? <div className="mt-4 divide-y divide-border border-y border-border">{coaches.map((coach) => <div key={`${coach.id}-${coach.coach_type}-${coach.scope_key}`} className="flex flex-wrap items-center justify-between gap-3 py-3"><div><strong className="text-sm">{coach.display_name}</strong><p className="mt-1 text-xs text-muted">{coach.title} / {coach.coach_type.replaceAll('_', ' ')}</p></div><span className="text-xs font-semibold text-primary">{coach.scope_type === 'programme_family' ? 'Shared assignment' : 'This division'}</span></div>)}</div> : <p className="mt-4 text-sm text-muted">No team staff are assigned to this roster yet. Add the first coach or staff person when ready.</p>}</div></details>
        <details className="overflow-hidden border-y border-border bg-surface"><summary className="cursor-pointer list-none px-5 py-4 text-sm font-bold text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D5A21F]">Roster history - {history.length} inactive</summary><div className="border-t border-border"><RosterPlayerList title="Inactive players" description="Previous roster places remain available for review and restoration." members={history} onManage={setProfile} emptyMessage="No inactive roster history." /></div></details>
        <SlideOver show={lockReviewOpen} title="Approve team sheet" onClose={() => setLockReviewOpen(false)}><form onSubmit={lock} className="space-y-5"><div><h3 className="text-2xl font-bold text-foreground">Approve {departmentName || 'team'} team sheet?</h3><p className="mt-2 text-sm leading-6 text-muted">This confirms the competition roster for {sport.name} {division.name}. {players.length} players will be included. Later changes need a correction reason and a new approval.</p><dl className="mt-4 grid grid-cols-2 gap-3"><div className="rounded-sm border border-border bg-surface p-3"><dt className="text-xs uppercase text-muted">Players</dt><dd className="mt-1 text-xl font-bold text-foreground">{players.length}</dd></div><div className="rounded-sm border border-border bg-surface p-3"><dt className="text-xs uppercase text-muted">Team staff</dt><dd className="mt-1 text-xl font-bold text-foreground">{coaches.length}</dd></div></dl></div>{readinessNotices.length ? <section className="border border-accent/40 bg-accent/10 p-4"><h3 className="text-sm font-bold text-foreground">Good to know</h3><ul className="mt-2 flex flex-col gap-1 text-sm text-muted">{readinessNotices.map((notice) => <li key={notice}>{notice}</li>)}</ul></section> : null}<label className="flex items-start gap-3 rounded-sm border border-accent/40 bg-accent/10 p-4 text-sm font-semibold text-foreground"><input required type="checkbox" checked={statusForm.data.roster_review_confirmed} onChange={(eventObject) => statusForm.setData('roster_review_confirmed', eventObject.target.checked)} className="mt-0.5 rounded-sm border-border text-primary focus:ring-accent" />I confirm that the roster details and required external documents were reviewed.</label>{statusErrors.length ? <ul role="alert" className="list-disc space-y-1 pl-5 text-sm font-semibold text-danger">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul> : null}<button className={primary} disabled={!statusForm.data.roster_review_confirmed || statusForm.processing}>{statusForm.processing ? 'Approving team sheet...' : 'Approve team sheet'}</button></form></SlideOver>
        <SlideOver show={addOpen} title="Add players" onClose={() => setAddOpen(false)}><RosterAddPlayers event={event} entry={entry} departmentId={selected.department_id} participants={participants} roles={options.roster_roles} archived={archived} onClose={() => setAddOpen(false)} /></SlideOver>
        <SlideOver show={coachSupportOpen} title="Add coach or staff" onClose={() => setCoachSupportOpen(false)}><CoachSupportForm event={event} division={division} departmentId={selected.department_id} departmentName={departmentName} sportName={sport.name} archived={archived || entryCapabilities.published} onClose={() => setCoachSupportOpen(false)} /></SlideOver>
        <SlideOver show={reopenOpen} title="Edit approved team sheet" onClose={() => setReopenOpen(false)}><form onSubmit={reopen} className="space-y-4"><p className="text-sm leading-6 text-muted">The approved roster remains in the event history. Explain the correction before making changes.</p><label className="block text-sm font-bold text-foreground">Reason<textarea required rows="4" value={statusForm.data.reason} onChange={(eventObject) => statusForm.setData('reason', eventObject.target.value)} className={input} /></label>{statusErrors.length ? <ul role="alert" className="list-disc space-y-1 pl-5 text-sm font-semibold text-danger">{statusErrors.map((error) => <li key={error}>{error}</li>)}</ul> : null}<button className={primary} disabled={statusForm.processing}>{statusForm.processing ? 'Enabling edits...' : 'Enable editing'}</button></form></SlideOver>
        {profile ? <SlideOver show title={`Manage ${profile.display_name}`} onClose={() => setProfile(null)}><ManagePanel key={profile.id} event={event} entry={entry} participant={profile} options={options} onClose={() => setProfile(null)} /></SlideOver> : null}
    </div>;
}

export default function Rosters({ event, sport, division, selectedDepartment, workspace, options, archived, departmentName = null }) {
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');
    if (!division) return <section className={`${surface} rounded-sm p-6`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Teams &amp; Rosters</p><h2 className="mt-2 text-2xl font-bold text-foreground">Choose a division</h2><p className="mt-2 text-sm text-muted">Pick a division above to see its teams and team sheets.</p></section>;
    const departments = workspace?.departments || [];
    const departmentId = selectedDepartment?.id ?? selectedDepartment;
    const selectedDepartmentRecord = selectedDepartment && typeof selectedDepartment === 'object'
        ? selectedDepartment
        : departments.find((department) => String(department.id) === String(departmentId));
    const resolvedDepartmentName = departmentName || selectedDepartmentRecord?.name || selectedDepartmentRecord?.abbreviation || null;

    if (departmentId !== null && departmentId !== undefined && departmentId !== '') {
        return <RosterDetail event={event} sport={sport} division={division} selected={workspace?.selected} options={options} archived={archived} departmentName={resolvedDepartmentName} />;
    }

    const readyCount = departments.filter((department) => ['ready', 'locked'].includes(department.state)).length;
    const draftCount = departments.filter((department) => ['not_started', 'draft'].includes(department.state)).length;
    const attentionCount = departments.filter((department) => ['review', 'blocked'].includes(department.state)).length;

    return <div className="flex flex-col gap-5">
        <header className="flex flex-col gap-3 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Teams &amp; Rosters</p><h2 className="mt-1 text-3xl font-bold text-foreground">{division.name}</h2><p className="mt-1 text-sm text-muted">Manage the teams competing in {sport.name} {division.name}.</p></div><p className="text-sm font-semibold text-muted">{departments.length} teams · {readyCount} ready · {draftCount} draft · {attentionCount} need attention</p></header>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <label className="min-w-0 flex-1 text-sm font-bold text-foreground">Search teams<input aria-label="Search teams" value={query} onChange={(eventObject) => setQuery(eventObject.target.value)} className={input} placeholder="Search teams..." /></label>
            <label className="text-sm font-bold text-foreground">Team status<select aria-label="Team status" value={statusFilter} onChange={(eventObject) => setStatusFilter(eventObject.target.value)} className={input}><option value="all">All statuses</option><option value="needs_attention">Needs attention</option><option value="draft">Draft</option><option value="not_started">Not started</option><option value="ready">Ready for approval</option><option value="locked">Approved</option></select></label>
        </div>
        <DepartmentIndex event={event} sport={sport} division={division} departments={departments} selectedId={selectedDepartment} query={query} statusFilter={statusFilter} />
    </div>;
}
