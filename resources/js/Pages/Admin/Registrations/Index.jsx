import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { cloneElement, useEffect, useId, useMemo, useRef, useState } from 'react';

const inputClass = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm text-slate-900 shadow-sm focus:border-[#d5a21f] focus:ring-[#d5a21f] disabled:cursor-not-allowed disabled:bg-slate-100';
const labelClass = 'text-xs font-bold uppercase tracking-[0.1em] text-slate-600';
const buttonPrimary = 'inline-flex min-h-11 items-center justify-center rounded-xl bg-[#0b2e4f] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#123c61] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d5a21f] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const buttonSecondary = 'inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 hover:border-slate-400 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d5a21f] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

function Field({ label, error, children, className = '' }) {
    const fieldId = useId();
    const errorId = `${fieldId}-error`;
    const control = cloneElement(children, {
        id: children.props.id || fieldId,
        name: children.props.name || label.toLowerCase().replaceAll(/[^a-z0-9]+/g, '_'),
        autoComplete: children.props.autoComplete || 'off',
        'aria-invalid': error ? 'true' : undefined,
        'aria-describedby': error ? errorId : children.props['aria-describedby'],
    });

    return (
        <label className={`block ${className}`}>
            <span className={labelClass}>{label}</span>
            {control}
            {error ? <span id={errorId} aria-live="polite" className="mt-1 block text-xs font-medium text-red-700">{error}</span> : null}
        </label>
    );
}

function focusFirstError() {
    requestAnimationFrame(() => document.querySelector('[aria-invalid="true"]')?.focus());
}

function syncSelection(participantId, entryId = null) {
    const url = new URL(window.location.href);
    if (participantId) url.searchParams.set('participant', participantId);
    else url.searchParams.delete('participant');
    if (entryId) url.searchParams.set('entry', entryId);
    else url.searchParams.delete('entry');
    window.history.replaceState(window.history.state, '', url);
}

function StatusPill({ children, tone = 'slate' }) {
    const tones = {
        slate: 'border-slate-200 bg-slate-50 text-slate-600',
        green: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        red: 'border-red-200 bg-red-50 text-red-800',
        navy: 'border-[#0b2e4f]/15 bg-[#0b2e4f]/5 text-[#0b2e4f]',
    };

    return <span className={`inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.1em] ${tones[tone]}`}>{children}</span>;
}

function SummaryStrip({ summary }) {
    const items = [
        ['Participants', summary.participants],
        ['Active', summary.active_participants],
        ['Roster places', summary.active_roster_memberships],
        ['Eligible', summary.eligibility.eligible],
        ['Pending', summary.eligibility.pending],
        ['Flagged', summary.eligibility.ineligible + summary.eligibility.withdrawn + summary.eligibility.disqualified],
    ];

    return (
        <dl className="grid grid-cols-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm sm:grid-cols-3 xl:grid-cols-6">
            {items.map(([label, value]) => <div key={label} className="border-b border-r border-slate-100 p-4 last:border-r-0 sm:p-5"><dt className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-slate-500">{label}</dt><dd className="mt-1 font-serif text-2xl font-bold tabular-nums text-[#17212b]">{value}</dd></div>)}
        </dl>
    );
}

function Filters({ filters, delegations, competitions, options, eventId }) {
    const [values, setValues] = useState(filters);
    const divisions = useMemo(() => competitions
        .filter((competition) => !values.competition || competition.id === values.competition)
        .flatMap((competition) => competition.divisions.map((division) => ({ ...division, competition: competition.name }))), [competitions, values.competition]);

    function submit(event) {
        event.preventDefault();
        router.get(route('admin.registrations.index', eventId), Object.fromEntries(Object.entries(values).filter(([, value]) => value !== '')), { preserveState: true, replace: true });
    }

    return (
        <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-[1.4fr_repeat(5,minmax(0,1fr))_auto] xl:items-end">
                <Field label="Search">
                    <input value={values.q} onChange={(event) => setValues({ ...values, q: event.target.value })} className={inputClass} placeholder="Name or student number…" type="search" />
                </Field>
                <Field label="Delegation">
                    <select value={values.delegation} onChange={(event) => setValues({ ...values, delegation: event.target.value })} className={inputClass}><option value="">All</option>{delegations.map((item) => <option key={item.id} value={item.id}>{item.abbreviation}</option>)}</select>
                </Field>
                <Field label="Competition">
                    <select value={values.competition} onChange={(event) => setValues({ ...values, competition: event.target.value, division: '' })} className={inputClass}><option value="">All</option>{competitions.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</select>
                </Field>
                <Field label="Division">
                    <select value={values.division} onChange={(event) => setValues({ ...values, division: event.target.value })} className={inputClass}><option value="">All</option>{divisions.map((item) => <option key={item.id} value={item.id}>{item.competition} — {item.name}</option>)}</select>
                </Field>
                <Field label="Entry mode">
                    <select value={values.entry_mode} onChange={(event) => setValues({ ...values, entry_mode: event.target.value })} className={inputClass}><option value="">All</option>{options.entry_modes.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select>
                </Field>
                <Field label="Eligibility">
                    <select value={values.eligibility} onChange={(event) => setValues({ ...values, eligibility: event.target.value })} className={inputClass}><option value="">All</option>{options.eligibility_statuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select>
                </Field>
                <div className="flex gap-2 md:col-span-2 xl:col-span-1">
                    <button type="submit" className={buttonPrimary}>Apply</button>
                    <Link href={route('admin.registrations.index', eventId)} className={buttonSecondary}>Clear</Link>
                </div>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
                <button type="button" onClick={() => setValues({ ...values, roster_status: values.roster_status === 'assigned' ? '' : 'assigned' })} aria-pressed={values.roster_status === 'assigned'} className={`rounded-full border px-3 py-1.5 text-xs font-bold ${values.roster_status === 'assigned' ? 'border-[#0b2e4f] bg-[#0b2e4f] text-white' : 'border-slate-200 text-slate-600'}`}>Assigned</button>
                <button type="button" onClick={() => setValues({ ...values, roster_status: values.roster_status === 'unassigned' ? '' : 'unassigned' })} aria-pressed={values.roster_status === 'unassigned'} className={`rounded-full border px-3 py-1.5 text-xs font-bold ${values.roster_status === 'unassigned' ? 'border-[#0b2e4f] bg-[#0b2e4f] text-white' : 'border-slate-200 text-slate-600'}`}>Unassigned</button>
                <span className="self-center text-xs text-slate-400">Filters are saved in the page URL after Apply.</span>
            </div>
        </form>
    );
}

function ParticipantList({ participants, selectedId, onSelect, onNew }) {
    return (
        <section aria-labelledby="participant-list-title" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between gap-4 border-b border-slate-200 px-4 py-4 sm:px-5">
                <div><p className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#9a6e00]">Master list</p><h2 id="participant-list-title" className="mt-1 font-serif text-xl font-bold text-[#17212b]">Participants</h2></div>
                <button type="button" onClick={onNew} className={buttonPrimary}>New participant</button>
            </div>
            {participants.length ? (
                <ul className="max-h-[70rem] divide-y divide-slate-100 overflow-y-auto">
                    {participants.map((participant) => {
                        const eligibility = participant.memberships.find((item) => item.is_active)?.eligibility;
                        const selected = selectedId === participant.id;
                        return (
                            <li key={participant.id} style={{ contentVisibility: 'auto', containIntrinsicSize: '92px' }}>
                                <button type="button" onClick={() => onSelect(participant)} aria-current={selected ? 'true' : undefined} className={`w-full px-4 py-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#d5a21f] sm:px-5 ${selected ? 'bg-[#0b2e4f]/5' : 'hover:bg-slate-50'}`}>
                                    <div className="flex items-start justify-between gap-3"><div className="min-w-0"><h3 className="truncate font-semibold text-slate-950">{participant.display_name}</h3><p className="mt-1 text-xs text-slate-500">{participant.delegation} · {participant.student_number || 'No student number'}</p></div><StatusPill tone={participant.is_active ? 'green' : 'slate'}>{participant.is_active ? 'Active' : 'Inactive'}</StatusPill></div>
                                    <div className="mt-3 flex flex-wrap items-center gap-2"><StatusPill tone="navy">{participant.memberships.filter((item) => item.is_active).length} entries</StatusPill>{eligibility ? <StatusPill tone={eligibility === 'eligible' ? 'green' : eligibility === 'pending' ? 'amber' : 'red'}>{eligibility}</StatusPill> : <span className="text-xs text-slate-400">No active roster</span>}</div>
                                </button>
                            </li>
                        );
                    })}
                </ul>
            ) : <div className="p-8 text-center"><p className="font-serif text-xl font-bold text-slate-800">No matching participants</p><p className="mt-2 text-sm text-slate-500">Clear the filters or register the first participant.</p></div>}
        </section>
    );
}

function ParticipantForm({ participant, delegations, eventId, archived }) {
    const headingRef = useRef(null);
    const form = useForm({
        event_delegation_id: '', display_name: '', given_name: '', family_name: '', student_number: '', email: '', phone: '', private_notes: '', is_active: true,
    });

    useEffect(() => {
        form.setData(participant ? {
            event_delegation_id: participant.event_delegation_id,
            display_name: participant.display_name,
            given_name: participant.given_name || '',
            family_name: participant.family_name || '',
            student_number: participant.student_number || '',
            email: participant.email || '',
            phone: participant.phone || '',
            private_notes: participant.private_notes || '',
            is_active: participant.is_active,
        } : { event_delegation_id: '', display_name: '', given_name: '', family_name: '', student_number: '', email: '', phone: '', private_notes: '', is_active: true });
        form.clearErrors();
        headingRef.current?.focus();
    }, [participant?.id]);

    function submit(event) {
        event.preventDefault();
        const options = { preserveScroll: true, onError: focusFirstError };
        if (participant) form.patch(route('admin.participants.update', [eventId, participant.id]), options);
        else form.post(route('admin.participants.store', eventId), options);
    }

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4"><div><p className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#9a6e00]">Private record</p><h2 ref={headingRef} tabIndex="-1" className="mt-1 font-serif text-xl font-bold text-[#17212b] outline-none">{participant ? 'Edit participant' : 'Register participant'}</h2></div><StatusPill tone="navy">No login account</StatusPill></div>
            <p className="mt-2 text-sm leading-6 text-slate-500">Contact fields and notes stay inside this Global Admin desk and never appear publicly.</p>
            <form onSubmit={submit} className="mt-5 grid gap-4 sm:grid-cols-2">
                <Field label="Delegation" error={form.errors.event_delegation_id} className="sm:col-span-2"><select required disabled={archived} value={form.data.event_delegation_id} onChange={(event) => form.setData('event_delegation_id', event.target.value)} className={inputClass}><option value="">Select department</option>{delegations.filter((item) => item.active).map((item) => <option key={item.id} value={item.id}>{item.name} ({item.abbreviation})</option>)}</select></Field>
                <Field label="Display name" error={form.errors.display_name} className="sm:col-span-2"><input required disabled={archived} value={form.data.display_name} onChange={(event) => form.setData('display_name', event.target.value)} className={inputClass} /></Field>
                <Field label="Given name" error={form.errors.given_name}><input disabled={archived} value={form.data.given_name} onChange={(event) => form.setData('given_name', event.target.value)} className={inputClass} autoComplete="given-name" /></Field>
                <Field label="Family name" error={form.errors.family_name}><input disabled={archived} value={form.data.family_name} onChange={(event) => form.setData('family_name', event.target.value)} className={inputClass} autoComplete="family-name" /></Field>
                <Field label="Student number" error={form.errors.student_number}><input disabled={archived} value={form.data.student_number} onChange={(event) => form.setData('student_number', event.target.value)} className={inputClass} /></Field>
                <Field label="Email" error={form.errors.email}><input disabled={archived} type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className={inputClass} autoComplete="email" /></Field>
                <Field label="Phone" error={form.errors.phone} className="sm:col-span-2"><input disabled={archived} value={form.data.phone} onChange={(event) => form.setData('phone', event.target.value)} className={inputClass} autoComplete="tel" /></Field>
                <Field label="Private notes" error={form.errors.private_notes} className="sm:col-span-2"><textarea disabled={archived} rows="3" value={form.data.private_notes} onChange={(event) => form.setData('private_notes', event.target.value)} className={inputClass} /></Field>
                <label className="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3 sm:col-span-2"><input disabled={archived} type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="rounded border-slate-300 text-[#0b2e4f] focus:ring-[#d5a21f]" /><span className="text-sm font-semibold text-slate-700">Active participant</span></label>
                <div className="flex items-center gap-3 sm:col-span-2"><button disabled={archived || form.processing} className={buttonPrimary}>{form.processing ? 'Saving…' : participant ? 'Save profile' : 'Register participant'}</button>{form.recentlySuccessful ? <span role="status" className="text-sm font-semibold text-emerald-700">Saved</span> : null}</div>
            </form>
        </section>
    );
}

function NewEntryForm({ participant, competitions, eventId, archived }) {
    const divisions = competitions.flatMap((competition) => competition.divisions.map((division) => ({ ...division, competition: competition.name })))
        .filter((division) => division.participant_mode);
    const form = useForm({ competition_division_id: '', event_delegation_id: participant.event_delegation_id, name: '', code: '', entry_mode: '' });

    useEffect(() => form.setData('event_delegation_id', participant.event_delegation_id), [participant.id]);

    function chooseDivision(id) {
        const division = divisions.find((item) => item.id === id);
        form.setData({ ...form.data, competition_division_id: id, entry_mode: division?.participant_mode || '', name: division ? `${participant.delegation} ${division.competition} ${division.name}` : '' });
    }

    return (
        <details className="rounded-2xl border border-dashed border-slate-300 bg-white p-5">
            <summary className="cursor-pointer font-semibold text-[#0b2e4f] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#d5a21f]">Create a missing Division Entry</summary>
            <form onSubmit={(event) => { event.preventDefault(); form.post(route('admin.entries.store', eventId), { preserveScroll: true, onError: focusFirstError, onSuccess: () => form.reset('competition_division_id', 'name', 'code', 'entry_mode') }); }} className="mt-4 grid gap-4 sm:grid-cols-2">
                <Field label="Division" error={form.errors.competition_division_id} className="sm:col-span-2"><select required disabled={archived} value={form.data.competition_division_id} onChange={(event) => chooseDivision(event.target.value)} className={inputClass}><option value="">Select Division</option>{divisions.map((item) => <option key={item.id} value={item.id}>{item.competition} — {item.name} ({item.participant_mode})</option>)}</select></Field>
                <Field label="Entry name" error={form.errors.name}><input required disabled={archived} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass} /></Field>
                <Field label="Code" error={form.errors.code}><input disabled={archived} value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} className={inputClass} /></Field>
                <div className="sm:col-span-2"><button disabled={archived || form.processing} className={buttonSecondary}>{form.processing ? 'Creating…' : 'Create draft Entry'}</button></div>
            </form>
        </details>
    );
}

function EntryEditor({ entry, eventId, archived }) {
    const form = useForm({ competition_division_id: entry.competition_division_id, event_delegation_id: entry.event_delegation_id, name: entry.name, code: entry.code || '', entry_mode: entry.entry_mode });
    const stateForm = useForm({ status: entry.status, reason: '' });

    useEffect(() => {
        form.setData({ competition_division_id: entry.competition_division_id, event_delegation_id: entry.event_delegation_id, name: entry.name, code: entry.code || '', entry_mode: entry.entry_mode });
        stateForm.setData({ status: entry.status, reason: '' });
    }, [entry.id, entry.status]);

    function transition(event) {
        event.preventDefault();
        if (!window.confirm(`Change ${entry.name} from ${entry.status} to ${stateForm.data.status}?`)) return;
        stateForm.patch(route('admin.entries.status', [eventId, entry.id]), { preserveScroll: true, onError: focusFirstError });
    }

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3"><div><p className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#9a6e00]">Entry control</p><h3 className="mt-1 font-serif text-lg font-bold text-[#17212b]">{entry.competition} — {entry.division}</h3><p className="mt-1 text-xs text-slate-500">{entry.delegation} · {entry.entry_mode.replaceAll('_', ' ')}</p></div><div className="flex gap-2"><StatusPill tone={entry.status === 'locked' ? 'green' : 'navy'}>{entry.status}</StatusPill>{entry.published ? <StatusPill tone="red">Published</StatusPill> : entry.has_preview ? <StatusPill tone="amber">Redraw after edits</StatusPill> : null}</div></div>
            <form onSubmit={(event) => { event.preventDefault(); form.patch(route('admin.entries.update', [eventId, entry.id]), { preserveScroll: true, onError: focusFirstError }); }} className="mt-4 grid gap-3 sm:grid-cols-[1fr_.45fr_auto] sm:items-end">
                <Field label="Entry name" error={form.errors.name}><input required disabled={archived || entry.status === 'locked' || entry.published} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className={inputClass} /></Field>
                <Field label="Code" error={form.errors.code}><input disabled={archived || entry.status === 'locked' || entry.published} value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} className={inputClass} /></Field>
                <button disabled={archived || entry.status === 'locked' || entry.published || form.processing} className={buttonSecondary}>Save</button>
            </form>
            <form onSubmit={transition} className="mt-4 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-[.7fr_1fr_auto] sm:items-end">
                <Field label="Entry state" error={stateForm.errors.status}><select disabled={archived} value={stateForm.data.status} onChange={(event) => stateForm.setData('status', event.target.value)} className={inputClass}><option value="draft">Draft</option><option value="active">Active</option><option value="locked">Locked</option><option value="withdrawn">Withdrawn</option><option value="disqualified">Disqualified</option></select></Field>
                <Field label="Reason / correction note" error={stateForm.errors.reason}><input disabled={archived} value={stateForm.data.reason} onChange={(event) => stateForm.setData('reason', event.target.value)} className={inputClass} placeholder="e.g. Correcting the submitted roster…" /></Field>
                <button disabled={archived || stateForm.processing || stateForm.data.status === entry.status} className={buttonPrimary}>Change state</button>
            </form>
            {stateForm.errors.entry ? <p className="mt-3 text-sm font-medium text-red-700">{stateForm.errors.entry}</p> : null}
        </div>
    );
}

function RosterControls({ participant, entry, options, eventId, archived }) {
    const current = participant.memberships.find((item) => item.entry_id === entry.id);
    const memberForm = useForm({ role: current?.role || 'student_athlete', is_active: current?.is_active ?? true, notes: '' });
    const eligibilityForm = useForm({ status: current?.eligibility || 'pending', reason: current?.eligibility_reason || '' });

    useEffect(() => {
        const membership = participant.memberships.find((item) => item.entry_id === entry.id);
        memberForm.setData({ role: membership?.role || 'student_athlete', is_active: membership?.is_active ?? true, notes: '' });
        eligibilityForm.setData({ status: membership?.eligibility || 'pending', reason: membership?.eligibility_reason || '' });
        memberForm.clearErrors(); eligibilityForm.clearErrors();
    }, [participant.id, entry.id]);

    const directEditBlocked = archived || entry.status === 'locked' || entry.published;

    return (
        <div className="grid gap-4 xl:grid-cols-2">
            <form onSubmit={(event) => { event.preventDefault(); memberForm.put(route('admin.entry-members.update', [eventId, entry.id, participant.id]), { preserveScroll: true, onError: focusFirstError }); }} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#9a6e00]">Roster membership</p><h3 className="mt-1 font-serif text-lg font-bold text-[#17212b]">Role and active place</h3>
                <div className="mt-4 grid gap-3">
                    <Field label="Roster role" error={memberForm.errors.role}><select disabled={directEditBlocked} value={memberForm.data.role} onChange={(event) => memberForm.setData('role', event.target.value)} className={inputClass}>{options.roster_roles.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field>
                    <label className="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-3"><input disabled={directEditBlocked} type="checkbox" checked={memberForm.data.is_active} onChange={(event) => memberForm.setData('is_active', event.target.checked)} className="rounded border-slate-300 text-[#0b2e4f] focus:ring-[#d5a21f]" /><span className="text-sm font-semibold text-slate-700">Active roster place</span></label>
                    <button disabled={directEditBlocked || memberForm.processing} className={buttonPrimary}>{memberForm.processing ? 'Saving…' : current ? 'Update membership' : 'Add to roster'}</button>
                    {memberForm.errors.entry || memberForm.errors.participant ? <p className="text-sm font-medium text-red-700">{memberForm.errors.entry || memberForm.errors.participant}</p> : null}
                </div>
            </form>
            <form onSubmit={(event) => { event.preventDefault(); eligibilityForm.put(route('admin.eligibility.update', [eventId, entry.id, participant.id]), { preserveScroll: true, onError: focusFirstError }); }} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <p className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#9a6e00]">Eligibility decision</p><h3 className="mt-1 font-serif text-lg font-bold text-[#17212b]">Review status</h3>
                <div className="mt-4 grid gap-3">
                    <Field label="Status" error={eligibilityForm.errors.status}><select disabled={archived || !current} value={eligibilityForm.data.status} onChange={(event) => eligibilityForm.setData('status', event.target.value)} className={inputClass}>{options.eligibility_statuses.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</select></Field>
                    <Field label="Reason" error={eligibilityForm.errors.reason}><textarea disabled={archived || !current} rows="2" value={eligibilityForm.data.reason} onChange={(event) => eligibilityForm.setData('reason', event.target.value)} className={inputClass} placeholder="e.g. Medical withdrawal confirmed by committee…" /></Field>
                    <button disabled={archived || !current || eligibilityForm.processing} className={buttonPrimary}>{eligibilityForm.processing ? 'Recording…' : 'Record eligibility'}</button>
                    {!current ? <p className="text-xs text-slate-500">Save the roster membership first.</p> : null}
                </div>
            </form>
        </div>
    );
}

function RegistrationWorkspace({ participant, entries, competitions, options, event, selectedEntryId }) {
    const available = entries.filter((entry) => entry.event_delegation_id === participant.event_delegation_id);
    const initialEntry = available.some((entry) => entry.id === selectedEntryId) ? selectedEntryId : participant.memberships.find((item) => item.is_active)?.entry_id || available[0]?.id || '';
    const [entryId, setEntryId] = useState(initialEntry);

    useEffect(() => {
        const next = available.some((entry) => entry.id === selectedEntryId) ? selectedEntryId : participant.memberships.find((item) => item.is_active)?.entry_id || available[0]?.id || '';
        setEntryId(next);
        syncSelection(participant.id, next);
    }, [participant.id, entries.length, selectedEntryId]);
    const entry = available.find((item) => item.id === entryId);

    return (
        <section aria-labelledby="roster-title" className="space-y-4">
            <div className="rounded-2xl bg-[#0b2e4f] p-5 text-white shadow-sm">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-[0.68rem] font-bold uppercase tracking-[0.14em] text-[#ffe197]">Delegation → Participant → Entry → Eligible → Locked</p><h2 id="roster-title" className="mt-2 font-serif text-xl font-bold">Registration path</h2><p className="mt-1 text-sm text-white/60">Select an Entry in {participant.delegation} to manage this participant’s roster place.</p></div><label className="block min-w-64"><span className="sr-only">Selected Entry</span><select name="selected_entry" autoComplete="off" value={entryId} onChange={(event) => { setEntryId(event.target.value); syncSelection(participant.id, event.target.value); }} className="w-full rounded-xl border-white/20 bg-[#123c61] text-sm text-white focus:border-[#d5a21f] focus:ring-[#d5a21f]"><option value="">Select Entry</option>{available.map((item) => <option key={item.id} value={item.id}>{item.competition} — {item.division} ({item.status})</option>)}</select></label></div>
            </div>
            <NewEntryForm participant={participant} competitions={competitions} eventId={event.id} archived={event.archived} />
            {entry ? <><EntryEditor entry={entry} eventId={event.id} archived={event.archived} /><RosterControls participant={participant} entry={entry} options={options} eventId={event.id} archived={event.archived} /></> : <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">No Entry exists for this Delegation under the current filters. Create one above.</div>}
        </section>
    );
}

export default function RegistrationIndex({ event, filters, selection = {}, delegations = [], competitions = [], participants = [], entries = [], summary, options }) {
    const flash = usePage().props.flash;
    const [selected, setSelected] = useState(participants.find((item) => item.id === selection.participant) || participants[0] || null);
    const selectedCurrent = selected ? participants.find((item) => item.id === selected.id) || selected : null;

    useEffect(() => {
        if (selected && !participants.some((item) => item.id === selected.id)) setSelected(participants[0] || null);
    }, [participants]);

    return (
        <AuthenticatedLayout header={<div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.18em] text-[#0B536D]">Global Admin · private operations</p><h1 className="mt-1 font-serif text-2xl font-bold text-[#17212b]">Players & Rosters</h1></div><Link href={route('dashboard', { event: event.id })} className={buttonSecondary}>Back to dashboard</Link></div>}>
            <Head title={`${event.name} Players & Rosters`} />
            <main className="mx-auto max-w-[96rem] space-y-5 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                <section className="overflow-hidden rounded-3xl bg-[#0b2e4f] p-6 text-white shadow-xl shadow-slate-900/10 sm:p-8"><div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div><div className="flex flex-wrap gap-2"><StatusPill tone="green">Global Admin only</StatusPill><span className="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.1em] text-white">{event.state}</span></div><h2 className="mt-4 font-serif text-3xl font-bold sm:text-4xl">{event.name}</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-white/65">Register private participant profiles, assign department Entries, confirm eligibility, and lock draw-ready rosters. Registration does not create student credentials.</p></div>{event.archived ? <StatusPill tone="red">Read-only archive</StatusPill> : <p className="max-w-sm border-l-2 border-[#d5a21f] pl-4 text-sm text-white/70">Direct edits are allowed before lock. Published records use explicit withdrawal or disqualification corrections.</p>}</div></section>
                {flash.status ? <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{flash.status}</div> : null}
                <SummaryStrip summary={summary} />
                <Filters filters={filters} delegations={delegations} competitions={competitions} options={options} eventId={event.id} />
                <div className="grid items-start gap-5 lg:grid-cols-[minmax(20rem,.75fr)_minmax(0,1.6fr)]">
                    <ParticipantList participants={participants} selectedId={selectedCurrent?.id} onSelect={(participant) => { setSelected(participant); syncSelection(participant.id); }} onNew={() => { setSelected(null); syncSelection(null); }} />
                    <div className="space-y-5"><ParticipantForm key={selectedCurrent?.id || 'new'} participant={selectedCurrent} delegations={delegations} eventId={event.id} archived={event.archived} />{selectedCurrent ? <RegistrationWorkspace participant={selectedCurrent} entries={entries} competitions={competitions} options={options} event={event} selectedEntryId={selection.entry} /> : null}</div>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
