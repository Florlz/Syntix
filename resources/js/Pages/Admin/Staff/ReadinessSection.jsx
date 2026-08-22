import React, { useEffect, useMemo, useRef, useState } from 'react';
import { DialogTitle } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import { adminStyles } from '@/Support/adminStyles';

const control = `mt-1 ${adminStyles.field}`;
const action = adminStyles.primaryAction;
const setupKeys = ['contest', 'panel', 'aggregation', 'deduction', 'tabulator', 'lock'];
const liveKeys = ['scores', 'tabulation'];
const completeStates = ['complete', 'prepared', 'configured', 'confirmed', 'ready', 'assigned', 'locked'];
const stepLabels = {
    contest: 'Contest',
    panel: 'Judge panel',
    aggregation: 'Aggregation',
    deduction: 'Deduction rules',
    tabulator: 'Tabulator',
    lock: 'Panel',
    scores: 'Judge scores',
    tabulation: 'Tabulation',
};

function statusForStep(step, current) {
    if (step.state === 'blocked') return { symbol: '!', text: 'Blocked' };
    if (current) return { symbol: '->', text: 'Current' };
    if (completeStates.includes(step.state)) return { symbol: 'OK', text: 'Complete' };
    return { symbol: 'o', text: step.state === 'waiting' ? 'Waiting' : 'Pending' };
}

function stepsFor(item, keys) {
    const provided = new Map((item.readiness_steps ?? []).map((step) => [step.key, step]));

    return keys.map((key) => {
        if (key === 'tabulator' && item.next_action_key === 'tabulator' && item.tabulator_available === false) {
            return { key, label: stepLabels[key], state: 'blocked', detail: 'No active Tabulator available' };
        }

        return provided.get(key) ?? {
            key,
            label: stepLabels[key],
            state: 'pending',
            detail: 'Not started',
        };
    });
}

function ReadinessSteps({ title, steps, currentKey }) {
    return (
        <section aria-labelledby={`${title.replaceAll(' ', '-').toLowerCase()}-heading`}>
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h4 id={`${title.replaceAll(' ', '-').toLowerCase()}-heading`} className="text-lg font-bold">
                    {title}
                </h4>
                <p className="text-xs text-muted">{steps.filter((step) => completeStates.includes(step.state)).length} of {steps.length} complete</p>
            </div>
            <ol aria-label={title} className="grid gap-2 sm:grid-cols-2">
                {steps.map((step) => {
                    const current = step.key === currentKey;
                    const status = statusForStep(step, current);

                    return (
                        <li
                            key={step.key}
                            aria-current={current ? 'step' : undefined}
                            className={`flex gap-3 rounded-sm border p-3 ${step.state === 'blocked' ? 'border-danger bg-danger-surface' : current ? 'border-primary bg-primary/10' : 'border-border bg-background/70'}`}
                        >
                            <span aria-hidden="true" className={`flex size-7 shrink-0 items-center justify-center rounded-full text-[0.65rem] font-bold ${step.state === 'blocked' ? 'bg-danger text-white' : current || completeStates.includes(step.state) ? 'bg-primary text-primary-foreground' : 'bg-accent/20 text-foreground'}`}>
                                {status.symbol}
                            </span>
                            <span className="min-w-0">
                                <span className="block text-sm font-bold">{step.label}</span>
                                <span className="mt-0.5 block text-xs text-muted">{step.detail}</span>
                                <span className="mt-1 block text-xs font-semibold text-foreground">{status.text}</span>
                            </span>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

function AuditFields({ form, prefix }) {
    return (
        <div className="mt-3 grid gap-3 sm:grid-cols-2">
            <label className="text-xs font-bold uppercase tracking-[0.1em] text-muted">
                Administrative reference
                <input required aria-label={`${prefix} administrative reference`} value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} className={control} />
                <InputError message={form.errors?.reference} className="mt-1 normal-case tracking-normal" />
            </label>
            <label className="text-xs font-bold uppercase tracking-[0.1em] text-muted">
                Reason
                <input required aria-label={`${prefix} reason`} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={control} />
                <InputError message={form.errors?.reason} className="mt-1 normal-case tracking-normal" />
            </label>
        </div>
    );
}

function PanelForm({ item, form, onSubmit, title = 'Judging panel', archived = false }) {
    const judges = item.actions?.judge_options ?? [];
    const toggleJudge = (id) => {
        const value = String(id);
        form.setData('judge_ids', form.data.judge_ids.includes(value) ? form.data.judge_ids.filter((judgeId) => judgeId !== value) : [...form.data.judge_ids, value]);
    };

    return (
        <form onSubmit={onSubmit} className="rounded-sm border border-border bg-surface p-4">
            <fieldset disabled={archived}>
                <legend className="font-bold">{title}</legend>
                <p className="mt-1 text-sm text-muted">Choose the Judges who score every entry in this panel.</p>
                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                    {judges.map((judge) => (
                        <label key={judge.id} className="flex min-h-10 items-center gap-2 rounded-sm border border-border px-3 text-sm">
                            <input type="checkbox" checked={form.data.judge_ids.includes(String(judge.id))} onChange={() => toggleJudge(judge.id)} className="rounded border-border text-primary focus:ring-accent" />
                            {judge.name}
                        </label>
                    ))}
                </div>
                <InputError message={form.errors?.judge_ids} className="mt-2" />
                <button type="submit" disabled={archived || !form.data.judge_ids.length || form.processing} className={`${action} mt-3`}>
                    {form.processing ? 'Saving...' : 'Save panel'}
                </button>
            </fieldset>
        </form>
    );
}

function TieResolutionForm({ tie, archived = false }) {
    const form = useForm({ tied_entry_ids: tie.entry_ids, authorized_order: tie.entry_ids, reference: '', reason: '' });
    const name = (id) => tie.entries.find((entry) => Number(entry.id) === Number(id))?.name ?? `Entry ${id}`;
    const move = (index, direction) => {
        const order = [...form.data.authorized_order];
        const target = index + direction;
        if (target < 0 || target >= order.length) return;
        [order[index], order[target]] = [order[target], order[index]];
        form.setData('authorized_order', order);
    };

    return (
        <form onSubmit={(eventObject) => { eventObject.preventDefault(); form.post(tie.action, { preserveScroll: true }); }} className="rounded-sm border border-accent bg-accent/10 p-4">
            <fieldset disabled={archived}>
                <legend className="font-bold">Resolve tied entries</legend>
                <ol className="mt-3 space-y-2">
                    {form.data.authorized_order.map((id, index) => <li key={id} className="flex items-center gap-2 rounded-sm bg-surface p-2"><span className="w-7 font-condensed font-bold">{index + 1}</span><span className="flex-1 text-sm font-bold">{name(id)}</span><button type="button" aria-label={`Move ${name(id)} up`} disabled={archived || index === 0} onClick={() => move(index, -1)} className="min-h-9 rounded border border-border px-3 disabled:opacity-30">↑</button><button type="button" aria-label={`Move ${name(id)} down`} disabled={archived || index === form.data.authorized_order.length - 1} onClick={() => move(index, 1)} className="min-h-9 rounded border border-border px-3 disabled:opacity-30">↓</button></li>)}
                </ol>
                <AuditFields form={form} prefix="Tie resolution" />
                <button type="submit" disabled={archived || form.processing} className={`${action} mt-3`}>Authorize tie order</button>
            </fieldset>
        </form>
    );
}

function LockPanelModal({ item, processing, onClose, onLock }) {
    return (
        <Modal show maxWidth="md" onClose={onClose}>
            <div className="p-5">
                <DialogTitle as="h4" className="text-xl font-bold">Lock judging panel</DialogTitle>
                <p className="mt-2 text-sm leading-6 text-muted">Locking freezes this panel for scoring.</p>
                <p className="mt-3 text-sm font-semibold">
                    {item.counts?.judges ?? 0} Judges &middot; {item.counts?.entries ?? 0} Entries &middot; {(item.counts?.judges ?? 0) * (item.counts?.entries ?? 0)} scorecards expected
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={onClose} className="min-h-10 rounded-sm border border-border px-4 text-sm font-bold">Cancel</button>
                    <button type="button" disabled={processing} onClick={onLock} className="min-h-10 rounded-sm bg-danger px-4 text-sm font-bold text-white">Lock judging panel</button>
                </div>
            </div>
        </Modal>
    );
}

function ScheduleCard({ schedule }) {
    if (!schedule?.title && !schedule?.venue) return null;

    return <div className="rounded-sm border border-border bg-background/70 p-4">
        <p className="text-xs font-bold uppercase tracking-[0.1em] text-primary">Schedule</p>
        <p className="mt-1 font-bold">{schedule.title ?? 'Scheduled scoring window'}</p>
        <p className="mt-1 text-sm text-muted">{schedule.venue?.name ?? 'Venue to be confirmed'}{schedule.venue?.location ? ` · ${schedule.venue.location}` : ''}</p>
    </div>;
}

function ReadinessActions({ item, event, archived }) {
    const simple = useForm({});
    const panel = useForm({ judge_ids: (item.current_judge_ids ?? []).map((id) => String(id)) });
    const aggregation = useForm({ method: 'average', reference: '', reason: '' });
    const deduction = useForm({ rounding_policy: 'ceiling', reference: '', reason: '' });
    const tabulator = useForm({ user_id: '' });
    const [editingPanel, setEditingPanel] = useState(false);
    const [locking, setLocking] = useState(false);
    const nextAction = item.next_action_key;
    const panelAvailable = Boolean(item.actions?.panel);
    const tabulatorOptions = item.actions?.tabulator_options ?? [];
    const tabulatorAvailable = item.tabulator_available !== false;
    const post = (form, href, options = {}) => form.post(href, { preserveScroll: true, ...options });

    if (item.state === 'blocked') {
        return (
            <div className="rounded-sm border border-danger bg-danger-surface p-4 text-sm text-danger">
                <p className="font-bold uppercase tracking-[0.1em]">Scoring source blocked</p>
                <p className="mt-2">{item.source?.blocker ?? item.next_blocker}</p>
                {item.source?.pages?.length ? <p className="mt-2 text-xs">Source pages: {item.source.pages.join(', ')} &middot; Reliability: {item.source.reliability}</p> : null}
            </div>
        );
    }

    const peopleHref = route('admin.staff.index', { event: event.id, section: 'people' });
    const noTabulatorMessage = (
        <div className="mt-3 rounded-sm bg-accent/10 p-3 text-sm text-foreground">
            <p>No active Tabulators are available. Add or reactivate a Tabulator before locking the judging panel.</p>
            <a href={peopleHref} className="mt-2 inline-block font-bold text-primary">Open People</a>
        </div>
    );

    return (
        <div className="space-y-4">
            {nextAction === 'prepare' ? <button type="button" disabled={archived || simple.processing} onClick={() => post(simple, item.actions.prepare)} className={action}>Prepare official judged Contest</button> : null}
            {nextAction === 'panel' ? <PanelForm item={item} form={panel} archived={archived} onSubmit={(eventObject) => { eventObject.preventDefault(); post(panel, item.actions.panel); }} /> : null}
            {nextAction === 'aggregation' ? (
                <form onSubmit={(eventObject) => { eventObject.preventDefault(); post(aggregation, item.actions.aggregation); }} className="rounded-sm border border-border bg-surface p-4">
                    <h4 className="font-bold">Judge score aggregation</h4>
                    <p className="mt-1 text-sm text-muted">Record the approved method before the panel is locked.</p>
                    <p className="mt-3 rounded-sm bg-surface-muted p-3 text-sm font-semibold">Average of Judge totals</p>
                    <AuditFields form={aggregation} prefix="Aggregation" />
                    <InputError message={aggregation.errors?.method} className="mt-2" />
                    <button type="submit" disabled={archived || aggregation.processing} className={`${action} mt-3`}>Confirm method</button>
                </form>
            ) : null}
            {nextAction === 'deduction' ? (
                <form onSubmit={(eventObject) => { eventObject.preventDefault(); post(deduction, item.actions.deduction); }} className="rounded-sm border border-accent bg-accent/10 p-4">
                    <h4 className="font-bold">Deduction calculation authority</h4>
                    <p className="mt-1 text-sm text-muted">Choose how partial intervals are counted.</p>
                    <label className="mt-3 block text-xs font-bold uppercase tracking-[0.1em] text-muted">Rounding policy
                        <select aria-label="Partial interval rounding" value={deduction.data.rounding_policy} onChange={(eventObject) => deduction.setData('rounding_policy', eventObject.target.value)} className={control}>
                            <option value="ceiling">Count any partial interval, treated as full</option>
                            <option value="floor">Count completed intervals only</option>
                            <option value="nearest">Round to nearest interval</option>
                        </select>
                    </label>
                    <AuditFields form={deduction} prefix="Deduction" />
                    <button type="submit" disabled={archived || deduction.processing} className={`${action} mt-3`}>Authorize calculation</button>
                </form>
            ) : null}
            {nextAction === 'tabulator' ? (
                <form onSubmit={(eventObject) => { eventObject.preventDefault(); const selected = tabulatorOptions.find((option) => String(option.id) === String(tabulator.data.user_id)); if (selected) post(tabulator, selected.href); }} className="rounded-sm border border-border bg-surface p-4">
                    <label className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Assign Tabulator
                        <select required aria-label="Assign Tabulator" value={tabulator.data.user_id} onChange={(eventObject) => tabulator.setData('user_id', eventObject.target.value)} className={control} disabled={!tabulatorAvailable}>
                            <option value="">Choose Tabulator</option>
                            {tabulatorOptions.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                        </select>
                    </label>
                    {tabulatorAvailable && tabulatorOptions.length ? <button type="submit" disabled={archived || !tabulator.data.user_id || tabulator.processing} className={`${action} mt-3`}>Assign Tabulator</button> : noTabulatorMessage}
                </form>
            ) : null}
            {nextAction === 'lock' && tabulatorAvailable ? (
                <>
                    <button type="button" disabled={archived} onClick={() => setLocking(true)} className={action}>Lock judging panel</button>
                    {locking ? <LockPanelModal item={item} processing={simple.processing} onClose={() => setLocking(false)} onLock={() => { setLocking(false); post(simple, item.actions.lock); }} /> : null}
                </>
            ) : null}
            {!tabulatorAvailable && nextAction !== 'tabulator' ? noTabulatorMessage : null}
            {panelAvailable && nextAction !== 'panel' && !item.readiness_steps?.find((step) => step.key === 'lock' && step.state === 'locked') ? (
                <div className="rounded-sm border border-border bg-surface p-3">
                    <button type="button" disabled={archived} onClick={() => setEditingPanel((value) => !value)} className="text-sm font-bold text-primary disabled:opacity-50">{editingPanel ? 'Close panel editor' : 'Edit judging panel'}</button>
                    {editingPanel ? <div className="mt-3"><PanelForm item={item} form={panel} archived={archived} title="Edit judging panel" onSubmit={(eventObject) => { eventObject.preventDefault(); post(panel, item.actions.panel, { onSuccess: () => setEditingPanel(false) }); }} /></div> : null}
                </div>
            ) : null}
        </div>
    );
}

function hasUnavailableTabulator(item) {
    return item.next_action_key === 'tabulator' && item.tabulator_available === false;
}

function readinessSummary(item) {
    if (item.state === 'blocked' || hasUnavailableTabulator(item)) return 'Blocked';
    if (!item.next_action_key || liveKeys.includes(item.next_action_key) || item.next_action_key === 'tie') return 'Ready';
    return 'Need Setup';
}

export default function ReadinessSection({ readiness = [], event }) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');
    const focusId = useMemo(() => typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('focus'), []);
    const focusedDetails = useRef(null);
    const summaryCounts = useMemo(() => ['Ready', 'Need Setup', 'Blocked'].map((label) => [label, readiness.filter((item) => readinessSummary(item) === label).length]), [readiness]);
    const visible = useMemo(() => readiness.filter((item) => {
        const haystack = `${item.name} ${item.competition ?? ''} ${item.division ?? ''} ${item.next_blocker ?? ''}`.toLowerCase();
        return (!query || haystack.includes(query.toLowerCase())) && (!status || readinessSummary(item) === status);
    }), [readiness, query, status]);

    useEffect(() => {
        if (!focusedDetails.current) return;
        focusedDetails.current.open = true;
        focusedDetails.current.scrollIntoView?.({ block: 'center' });
    }, [focusId, visible]);

    return (
        <section aria-labelledby="readiness-heading" className="space-y-5">
            <header>
                <p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Control room</p>
                <h2 id="readiness-heading" className="mt-1 text-3xl font-bold">Scoring readiness</h2>
                <p className="mt-2 max-w-3xl text-sm leading-6 text-muted">See what each activity needs before scoring can proceed. The next step comes from the server workflow.</p>
            </header>
            {event.archived ? <div className="rounded-sm border border-accent bg-accent/10 p-4 text-sm text-foreground"><strong>Archived event.</strong> Readiness history remains available, but this event can no longer be modified.</div> : null}
            <dl className="grid overflow-hidden rounded-sm border border-border bg-surface sm:grid-cols-3">
                {summaryCounts.map(([label, value]) => <div key={label} className="flex items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0 sm:border-r sm:border-b-0 sm:last:border-r-0"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{label}</dt><dd className="text-xl font-bold">{value}</dd></div>)}
            </dl>
            <section aria-label="Scoring readiness filters" className="grid gap-3 rounded-sm border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                <label><span className="sr-only">Search scoring readiness</span><input type="search" value={query} onChange={(eventObject) => setQuery(eventObject.target.value)} placeholder="Search activity or blocker" className={control} /></label>
                <label><span className="sr-only">Filter readiness status</span><select value={status} onChange={(eventObject) => setStatus(eventObject.target.value)} className={control}><option value="">All statuses</option><option value="Ready">Ready</option><option value="Need Setup">Need Setup</option><option value="Blocked">Blocked</option></select></label>
            </section>
            <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-muted">Showing {visible.length} of {readiness.length} activities</p><p className="text-xs font-semibold text-muted">Open an activity to inspect setup and live operations.</p></div>
            {visible.length ? <div className="space-y-3">{visible.map((item) => {
                const summary = readinessSummary(item);
                const focused = String(item.id) === String(focusId);
                return <details key={item.id ?? item.name} ref={focused ? focusedDetails : null} open={(focused || summary === 'Blocked') || undefined} className="group overflow-hidden rounded-sm border border-border bg-surface">
                    <summary className="grid cursor-pointer list-none gap-3 p-5 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                        <div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="text-xl font-bold">{item.name}</h3><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${summary === 'Blocked' ? 'bg-danger-surface text-danger' : summary === 'Ready' ? 'bg-primary/10 text-primary' : 'bg-accent/20 text-foreground'}`}>{summary}</span></div><p className="mt-1 text-sm text-muted">{item.division ? `${item.competition} / ${item.division}` : item.competition} / {item.counts?.entries ?? 0} entries / {item.counts?.judges ?? 0} Judges / {item.counts?.tabulators ?? 0} Tabulators</p><p className={`mt-2 text-sm font-semibold ${summary === 'Blocked' ? 'text-danger' : 'text-muted'}`}>{item.state === 'blocked' ? 'Setup controls are unavailable until the source is resolved.' : item.next_blocker ?? 'Ready for scoring operations.'}</p></div>
                        <span className="text-sm font-bold text-primary group-open:hidden">Open setup</span><span className="hidden text-sm font-bold text-primary group-open:inline">Close setup</span>
                    </summary>
                    <div className="grid gap-4 border-t border-border bg-surface-muted p-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                        <div className="space-y-4"><ScheduleCard schedule={item.schedule}/><ReadinessSteps title="Setup readiness" steps={stepsFor(item, setupKeys)} currentKey={item.next_action_key} /><ReadinessActions item={item} event={event} archived={event.archived} /></div>
                        <div className="space-y-4"><ReadinessSteps title="Live readiness" steps={stepsFor(item, liveKeys)} currentKey={item.next_action_key} />{item.tie ? <TieResolutionForm tie={item.tie} archived={event.archived} /> : <div className="rounded-sm border border-border bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-primary">Live operations</p><p className="mt-2 text-sm text-muted">Judge score progress and tabulation stay here after the panel is locked.</p></div>}</div>
                    </div>
                </details>;
            })}</div> : <div className="rounded-sm border border-dashed border-border bg-surface p-8 text-center"><h3 className="text-xl font-bold">No activities match</h3><p className="mt-2 text-sm text-muted">Clear the search or status filter to see the full readiness list.</p></div>}
        </section>
    );
}
