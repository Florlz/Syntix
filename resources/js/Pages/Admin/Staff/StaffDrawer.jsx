import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import SlideOver from '@/Components/SlideOver';

const control = 'mt-1 w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-primary focus:ring-primary';
const action = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50';

function ReasonDialog({ request, form, onClose, onSubmit }) {
    if (!request) return null;

    return <div className="fixed inset-0 z-[70] grid place-items-center bg-foreground/45 p-4" role="dialog" aria-modal="true" aria-labelledby="audit-reason-title">
        <form onSubmit={onSubmit} className="w-full max-w-lg rounded-xl border border-border bg-surface p-5 shadow-2xl">
            <h2 id="audit-reason-title" className="font-serif text-xl font-bold">{request.title}</h2>
            <p className="mt-2 text-sm leading-6 text-muted">{request.description}</p>
            <label className="mt-5 block text-sm font-semibold">Reason<textarea autoFocus required rows="4" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={control} placeholder="Record the administrative reason"/></label>
            <InputError message={form.errors?.reason} className="mt-2" />
            {form.errors && Object.keys(form.errors).filter((key) => key !== 'reason').length ? <p role="alert" className="mt-3 text-sm text-danger">{Object.values(form.errors).join(' ')}</p> : null}
            <div className="mt-5 flex flex-wrap justify-end gap-2">
                <button type="button" onClick={onClose} className="min-h-10 rounded-lg border border-border px-4 text-sm font-bold text-foreground">Cancel</button>
                <button type="submit" disabled={form.processing} className="min-h-10 rounded-lg bg-danger px-4 text-sm font-bold text-white disabled:opacity-50">{form.processing ? 'Processing…' : request.submitLabel}</button>
            </div>
        </form>
    </div>;
}

function Feedback({ message, errors }) {
    return <>{message ? <p role="status" className="mt-3 rounded-lg bg-primary/10 p-3 text-sm text-foreground">{message}</p> : null}{errors && Object.keys(errors).length ? <p role="alert" className="mt-3 rounded-lg bg-danger-surface p-3 text-sm text-danger">{Object.values(errors).join(' ')}</p> : null}</>;
}

export default function StaffDrawer({ event, person, targets = {}, onClose }) {
    const isJudge = person.roles.some((role) => role.role === 'judge');
    const isTabulator = person.roles.some((role) => role.role === 'tabulator');
    const [reasonRequest, setReasonRequest] = useState(null);
    const [message, setMessage] = useState('');
    const role = useForm({ role: isJudge ? 'tabulator' : 'judge' });
    const assignment = useForm({ scope_type: 'competition_division', target_id: '' });
    const account = useForm({ reason: '' });
    const assignmentOptions = targets[assignment.data.scope_type] ?? [];
    const panels = person.coverage?.judging_panels ?? person.judging_assignments ?? [];
    const tabulatorTargets = person.coverage?.tabulator_targets ?? person.tabulator_assignments ?? [];

    function openReason(request) {
        if (!event.archived) {
            account.setData('reason', '');
            setReasonRequest(request);
        }
    }

    function submitReason(eventObject) {
        eventObject.preventDefault();
        account.patch(reasonRequest.url, {
            preserveScroll: true,
            onSuccess: () => {
                setMessage(reasonRequest.successMessage);
                setReasonRequest(null);
            },
        });
    }

    function submitAssignment(eventObject) {
        eventObject.preventDefault();
        assignment.post(route('admin.staff.assignments.store', [event.id, person.id]), { preserveScroll: true, onSuccess: () => setMessage('Assignment added.') });
    }

    return <>
        <SlideOver show title={`Staff record: ${person.name}`} onClose={() => reasonRequest ? setReasonRequest(null) : onClose()}>
            <div className="space-y-5">
                {event.archived ? <div className="rounded-xl border border-accent bg-accent/10 p-4 text-sm"><strong>Archived event.</strong> This record is read-only.</div> : null}
                <section className="rounded-xl border border-border bg-surface p-5">
                    <div className="flex flex-wrap items-center gap-2">{person.roles.map((item) => <span key={item.id} className="rounded-full bg-accent/15 px-3 py-1 text-xs font-bold uppercase text-accent-foreground">{item.role}</span>)}<span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${person.account_state === 'active' ? 'bg-primary/10 text-primary' : 'bg-danger-surface text-danger'}`}>{person.account_state}</span></div>
                    <p className="mt-4 text-sm text-muted">Invitation: {person.invitation?.state ?? 'Account already established'}{person.invitation?.expires_at ? ` · expires ${new Date(person.invitation.expires_at).toLocaleString()}` : ''}</p>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <button type="button" disabled={event.archived || account.processing} onClick={() => account.post(route('admin.staff.invitations.reissue', [event.id, person.id]), { preserveScroll: true, onSuccess: () => setMessage('New setup invitation ready.') })} className={action}>Reissue setup link</button>
                        {person.account_state === 'active' ? <button type="button" disabled={event.archived} onClick={() => openReason({ title: 'Disable account', description: `Disable ${person.name}'s account and revoke active sessions.`, submitLabel: 'Disable account', url: route('admin.staff.disable', [event.id, person.id]), successMessage: 'Account disabled.' })} className="inline-flex min-h-10 items-center justify-center rounded-lg bg-danger px-4 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Disable account</button> : <button type="button" disabled={event.archived || account.processing} onClick={() => account.patch(route('admin.staff.enable', [event.id, person.id]), { preserveScroll: true, onSuccess: () => setMessage('Account reactivated.') })} className="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50">Reactivate account</button>}
                    </div>
                    {person.event_memberships?.length > 1 ? <p className="mt-4 rounded-lg bg-accent/10 p-3 text-xs text-foreground"><strong>Platform-wide effect.</strong> Disabling affects {person.event_memberships.map((membership) => membership.event).join(', ')}.</p> : null}
                    <Feedback message={message} errors={account.errors}/>
                </section>

                <section className="rounded-xl border border-border bg-surface p-5">
                    <h3 className="font-serif text-lg font-bold">Event roles</h3>
                    {person.roles.length ? <div className="mt-3 space-y-2">{person.roles.map((item) => <div key={item.id} className="flex items-center justify-between rounded-lg border border-border p-3"><span className="text-sm font-bold capitalize">{item.role}</span><button type="button" disabled={event.archived} onClick={() => openReason({ title: `Revoke ${item.role} role`, description: `Remove ${item.role} access for ${person.name}. Matching incompatible scoring assignments will also be revoked.`, submitLabel: 'Revoke role', url: route('admin.staff.roles.revoke', [event.id, item.id]), successMessage: 'Role revoked.' })} className="text-xs font-bold text-danger disabled:opacity-50">Revoke</button></div>)}</div> : <p className="mt-2 text-sm text-muted">No active event roles.</p>}
                    <form onSubmit={(eventObject) => { eventObject.preventDefault(); role.post(route('admin.staff.roles.store', [event.id, person.id]), { preserveScroll: true, onSuccess: () => setMessage('Event role granted.') }); }} className="mt-4 flex gap-2"><label className="sr-only" htmlFor="grant-role">Role to grant</label><select id="grant-role" value={role.data.role} onChange={(eventObject) => role.setData('role', eventObject.target.value)} className={control}><option value="judge">Judge</option><option value="tabulator">Tabulator</option></select><button type="submit" disabled={event.archived || role.processing} className={action}>Grant role</button></form>
                    <Feedback errors={role.errors}/>
                </section>

                {isJudge ? <section className="rounded-xl border border-border bg-surface p-5"><div className="flex items-start justify-between gap-3"><div><h3 className="font-serif text-lg font-bold">Judging assignments</h3><p className="mt-1 text-sm text-muted">Assignments are managed through judging panels.</p></div><a href={route('admin.staff.index', { event: event.id, section: 'readiness' })} className="text-xs font-bold text-primary">Manage panels</a></div>{panels.length ? <div className="mt-3 space-y-2">{panels.map((panel) => <div key={panel.contest_id ?? panel.id} className="rounded-xl border border-border bg-surface-muted p-3"><p className="text-sm font-bold">{panel.label}</p><p className="mt-1 text-xs text-muted">{panel.entry_count ?? panel.scorecard_count ?? 0} scorecards{panel.locked ? ' · Locked' : ''}</p></div>)}</div> : <p className="mt-3 rounded-xl bg-accent/10 p-3 text-sm text-accent-foreground">No scoring coverage. Not assigned to a judging panel.</p>}</section> : null}

                {isTabulator ? <section className="rounded-xl border border-border bg-surface p-5"><h3 className="font-serif text-lg font-bold">Tabulator assignments</h3>{tabulatorTargets.length ? <div className="mt-3 space-y-2">{tabulatorTargets.map((item) => <div key={item.assignment_id ?? item.id} className="rounded-xl border border-border bg-surface-muted p-3"><p className="text-[0.65rem] font-bold uppercase tracking-[0.1em] text-primary">{item.scope}</p><div className="mt-1 flex items-start justify-between gap-3"><div><p className="text-sm text-foreground">{item.label}</p><p className="mt-1 text-xs text-muted">{item.scope === 'division' ? 'Division assignment' : 'Contest assignment'}</p></div><button type="button" disabled={event.archived} onClick={() => openReason({ title: 'Revoke tabulator assignment', description: `Remove ${person.name}'s responsibility for ${item.label}.`, submitLabel: 'Revoke assignment', url: route('admin.staff.assignments.revoke', [event.id, item.assignment_id ?? item.id]), successMessage: 'Assignment revoked.' })} className="text-xs font-bold text-danger disabled:opacity-50">Revoke</button></div></div>)}</div> : <p className="mt-3 rounded-xl bg-accent/10 p-3 text-sm text-accent-foreground">No scoring coverage. No Division or Contest assignment.</p>}<form onSubmit={submitAssignment} className="mt-4 grid gap-2 sm:grid-cols-2"><label><span className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Scope</span><select aria-label="Tabulator assignment scope" value={assignment.data.scope_type} onChange={(eventObject) => assignment.setData({ scope_type: eventObject.target.value, target_id: '' })} className={control}><option value="competition_division">Division</option><option value="contest">Contest</option></select></label><label><span className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Target</span><select required aria-label="Tabulator assignment target" value={assignment.data.target_id} onChange={(eventObject) => assignment.setData('target_id', eventObject.target.value)} className={control}><option value="">Choose target</option>{assignmentOptions.map((item) => <option key={item.id} value={item.id}>{item.label}</option>)}</select></label><button type="submit" disabled={event.archived || assignment.processing} className={`${action} sm:col-span-2`}>Add tabulator assignment</button></form><Feedback errors={assignment.errors}/></section> : null}

                <section className="rounded-xl border border-border bg-surface p-5"><h3 className="font-serif text-lg font-bold">Audit history</h3>{person.audit?.length ? <ol className="mt-3 divide-y divide-border">{person.audit.map((item, index) => <li key={`${item.action}-${index}`} className="py-3 text-sm"><strong>{item.action.replaceAll('.', ' ')}</strong><p className="mt-1 text-xs text-muted">{item.at ? new Date(item.at).toLocaleString() : 'Time unavailable'}{item.reason ? ` · ${item.reason}` : ''}</p></li>)}</ol> : <p className="mt-2 text-sm text-muted">No relevant audit records yet.</p>}</section>
            </div>
        </SlideOver>
        <ReasonDialog request={reasonRequest} form={account} onClose={() => setReasonRequest(null)} onSubmit={submitReason}/>
    </>;
}
