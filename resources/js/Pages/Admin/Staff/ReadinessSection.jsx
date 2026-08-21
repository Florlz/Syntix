import React, { useMemo, useState } from 'react';
import { useForm } from '@inertiajs/react';
import InputError from '@/Components/InputError';

const control = 'mt-1 w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-primary focus:ring-primary';
const action = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50';
const completeStates = ['complete', 'prepared', 'configured', 'confirmed', 'ready', 'assigned', 'locked'];

function ReadinessSteps({ steps = [] }) {
    if (!steps.length) return null;

    return <ol aria-label="Sequential scoring readiness" className="grid gap-2 sm:grid-cols-2">
        {steps.map((step, index) => {
            const complete = completeStates.includes(step.state);
            return <li key={step.key} className="flex gap-3 rounded-lg border border-border bg-background/70 p-3"><span className={`flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${complete ? 'bg-primary text-primary-foreground' : step.state === 'blocked' ? 'bg-danger-surface text-danger' : 'bg-accent/20 text-foreground'}`}>{complete ? '✓' : index + 1}</span><span className="min-w-0"><span className="block text-sm font-bold">{step.label}</span><span className="mt-0.5 block text-xs text-muted">{step.detail}</span></span></li>;
        })}
    </ol>;
}

function AuditFields({ form, prefix }) {
    return <div className="mt-3 grid gap-3 sm:grid-cols-2"><label className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Administrative reference<input required aria-label={`${prefix} administrative reference`} value={form.data.reference} onChange={(event) => form.setData('reference', event.target.value)} className={control}/><InputError message={form.errors?.reference} className="mt-1 normal-case tracking-normal"/></label><label className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Reason<input required aria-label={`${prefix} reason`} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={control}/><InputError message={form.errors?.reason} className="mt-1 normal-case tracking-normal"/></label></div>;
}

function PanelForm({ item, form, onSubmit, title = 'Judging panel', archived = false }) {
    const toggleJudge = (id) => {
        const value = String(id);
        form.setData('judge_ids', form.data.judge_ids.includes(value) ? form.data.judge_ids.filter((itemId) => itemId !== value) : [...form.data.judge_ids, value]);
    };
    const judges = item.actions?.judge_options ?? [];

    return <form onSubmit={onSubmit} className="rounded-xl border border-border bg-surface p-4"><fieldset disabled={archived}><legend className="font-bold">{title}</legend><p className="mt-1 text-sm text-muted">Choose the Judges who score every entry in this panel.</p><div className="mt-3 grid gap-2 sm:grid-cols-2">{judges.map((judge) => <label key={judge.id} className="flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 text-sm"><input type="checkbox" checked={form.data.judge_ids.includes(String(judge.id))} onChange={() => toggleJudge(judge.id)} className="rounded border-border text-primary focus:ring-accent"/>{judge.name}</label>)}</div><InputError message={form.errors?.judge_ids} className="mt-2"/><button type="submit" disabled={archived || !form.data.judge_ids.length || form.processing} className={`${action} mt-3`}>{form.processing ? 'Saving…' : 'Save panel'}</button></fieldset></form>;
}

function TieResolutionForm({ tie, archived = false }) {
    const form = useForm({ tied_entry_ids: tie.entry_ids, authorized_order: tie.entry_ids, reference: '', reason: '' });
    const name = (id) => tie.entries.find((entry) => Number(entry.id) === Number(id))?.name ?? `Entry ${id}`;
    function move(index, direction) {
        const order = [...form.data.authorized_order];
        const target = index + direction;
        if (target < 0 || target >= order.length) return;
        [order[index], order[target]] = [order[target], order[index]];
        form.setData('authorized_order', order);
    }

    return <form onSubmit={(event) => { event.preventDefault(); form.post(tie.action, { preserveScroll: true }); }} className="rounded-xl border border-accent bg-accent/10 p-4"><fieldset disabled={archived}><legend className="font-bold">Resolve tied entries</legend><ol className="mt-3 space-y-2">{form.data.authorized_order.map((id, index) => <li key={id} className="flex items-center gap-2 rounded-lg bg-surface p-2"><span className="w-7 font-mono font-bold">{index + 1}</span><span className="flex-1 text-sm font-bold">{name(id)}</span><button type="button" aria-label={`Move ${name(id)} up`} disabled={archived || index === 0} onClick={() => move(index, -1)} className="min-h-9 rounded border border-border px-3 disabled:opacity-30">↑</button><button type="button" aria-label={`Move ${name(id)} down`} disabled={archived || index === form.data.authorized_order.length - 1} onClick={() => move(index, 1)} className="min-h-9 rounded border border-border px-3 disabled:opacity-30">↓</button></li>)}</ol><AuditFields form={form} prefix="Tie resolution"/><button type="submit" disabled={archived || form.processing} className={`${action} mt-3`}>Authorize tie order</button></fieldset></form>;
}

function ReadinessActions({ item, archived }) {
    const simple = useForm({});
    const panel = useForm({ judge_ids: (item.current_judge_ids ?? []).map((id) => String(id)) });
    const aggregation = useForm({ method: 'average', reference: '', reason: '' });
    const deduction = useForm({ rounding_policy: 'ceiling', reference: '', reason: '' });
    const tabulator = useForm({ user_id: '' });
    const [editingPanel, setEditingPanel] = useState(false);
    const [locking, setLocking] = useState(false);
    const nextAction = item.next_action_key;
    const post = (form, href, options = {}) => form.post(href, { preserveScroll: true, ...options });
    const panelAvailable = Boolean(item.actions?.panel);
    const tabulatorOptions = item.actions?.tabulator_options ?? [];
    const schedule = item.schedule?.title || item.schedule?.venue ? <div className="rounded-xl border border-border bg-background/70 p-4"><p className="text-xs font-bold uppercase tracking-[0.1em] text-primary">Schedule</p><p className="mt-1 font-bold">{item.schedule.title ?? 'Scheduled scoring window'}</p><p className="mt-1 text-sm text-muted">{item.schedule.venue?.name ?? 'Venue to be confirmed'}{item.schedule.venue?.location ? ` · ${item.schedule.venue.location}` : ''}</p></div> : null;

    if (item.state === 'blocked') return <div className="space-y-4">{schedule}<ReadinessSteps steps={item.readiness_steps}/><div className="rounded-xl border border-danger bg-danger-surface p-4 text-sm text-danger"><p className="font-bold uppercase tracking-[0.1em]">Scoring source blocked</p><p className="mt-2">{item.source?.blocker ?? item.next_blocker}</p>{item.source?.pages?.length ? <p className="mt-2 text-xs">Source pages: {item.source.pages.join(', ')} · Reliability: {item.source.reliability}</p> : null}</div></div>;

    return <div className="space-y-4">
        <ReadinessSteps steps={item.readiness_steps}/>
        {schedule}
        {nextAction === 'prepare' ? <button type="button" disabled={archived || simple.processing} onClick={() => post(simple, item.actions.prepare)} className={action}>Prepare official judged Contest</button> : null}
        {nextAction === 'panel' ? <PanelForm item={item} form={panel} archived={archived} onSubmit={(event) => { event.preventDefault(); post(panel, item.actions.panel); }}/> : null}
        {nextAction === 'aggregation' ? <form onSubmit={(event) => { event.preventDefault(); post(aggregation, item.actions.aggregation); }} className="rounded-xl border border-border bg-surface p-4"><h4 className="font-bold">Judge score aggregation</h4><p className="mt-1 text-sm text-muted">Record the approved method before the panel is locked.</p><label className="mt-3 block text-xs font-bold uppercase tracking-[0.1em] text-muted">Aggregation method<select aria-label="Aggregation method" value={aggregation.data.method} onChange={(event) => aggregation.setData('method', event.target.value)} className={control}><option value="average">Average of Judge totals</option></select></label><AuditFields form={aggregation} prefix="Aggregation"/><InputError message={aggregation.errors?.method} className="mt-2"/><button type="submit" disabled={archived || aggregation.processing} className={`${action} mt-3`}>Confirm method</button></form> : null}
        {nextAction === 'deduction' ? <form onSubmit={(event) => { event.preventDefault(); post(deduction, item.actions.deduction); }} className="rounded-xl border border-accent bg-accent/10 p-4"><h4 className="font-bold">Deduction calculation authority</h4><p className="mt-1 text-sm text-muted">Choose how partial intervals are counted.</p><label className="mt-3 block text-xs font-bold uppercase tracking-[0.1em] text-muted">Rounding policy<select aria-label="Partial interval rounding" value={deduction.data.rounding_policy} onChange={(event) => deduction.setData('rounding_policy', event.target.value)} className={control}><option value="ceiling">Count any partial interval · treated as full</option><option value="floor">Count completed intervals only</option><option value="nearest">Round to nearest interval</option></select></label><AuditFields form={deduction} prefix="Deduction"/><button type="submit" disabled={archived || deduction.processing} className={`${action} mt-3`}>Authorize calculation</button></form> : null}
        {nextAction === 'tabulator' ? <form onSubmit={(event) => { event.preventDefault(); const selected = tabulatorOptions.find((option) => String(option.id) === String(tabulator.data.user_id)); if (selected) post(tabulator, selected.href); }} className="rounded-xl border border-border bg-surface p-4"><label className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Assign Tabulator<select required aria-label="Assign Tabulator" value={tabulator.data.user_id} onChange={(event) => tabulator.setData('user_id', event.target.value)} className={control}><option value="">Choose Tabulator</option>{tabulatorOptions.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select></label>{tabulatorOptions.length ? <button type="submit" disabled={archived || !tabulator.data.user_id || tabulator.processing} className={`${action} mt-3`}>Assign Tabulator</button> : <p className="mt-3 rounded-lg bg-accent/10 p-3 text-sm text-foreground">No active Tabulators are available. Invite a Tabulator or reactivate an existing account.</p>}</form> : null}
        {nextAction === 'lock' ? <><button type="button" disabled={archived} onClick={() => setLocking(true)} className={action}>Lock judging panel</button>{locking ? <div className="fixed inset-0 z-[60] grid place-items-center bg-foreground/45 p-4" role="dialog" aria-modal="true" aria-labelledby="lock-panel-title"><div className="w-full max-w-md rounded-xl border border-border bg-surface p-5 shadow-2xl"><h4 id="lock-panel-title" className="font-serif text-xl font-bold">Lock judging panel</h4><p className="mt-2 text-sm leading-6 text-muted">Locking freezes this panel for scoring.</p><p className="mt-3 text-sm font-semibold">{item.counts?.judges ?? 0} Judges · {item.counts?.entries ?? 0} Entries · {(item.counts?.judges ?? 0) * (item.counts?.entries ?? 0)} scorecards expected</p><div className="mt-5 flex justify-end gap-2"><button type="button" onClick={() => setLocking(false)} className="min-h-10 rounded-lg border border-border px-4 text-sm font-bold">Cancel</button><button type="button" disabled={simple.processing} onClick={() => { setLocking(false); post(simple, item.actions.lock); }} className="min-h-10 rounded-lg bg-danger px-4 text-sm font-bold text-white">Lock judging panel</button></div></div></div> : null}</> : null}
        {panelAvailable && nextAction !== 'panel' && !item.readiness_steps?.find((step) => step.key === 'lock' && step.state === 'locked') ? <div className="rounded-lg border border-border bg-surface p-3"><button type="button" disabled={archived} onClick={() => setEditingPanel((value) => !value)} className="text-sm font-bold text-primary disabled:opacity-50">{editingPanel ? 'Close panel editor' : 'Edit judging panel'}</button>{editingPanel ? <div className="mt-3"><PanelForm item={item} form={panel} archived={archived} title="Edit judging panel" onSubmit={(event) => { event.preventDefault(); post(panel, item.actions.panel, { onSuccess: () => setEditingPanel(false) }); }}/></div> : null}</div> : null}
    </div>;
}

function labelForState(state) {
    const text = state === 'needs_attention' ? 'Needs attention' : state.replaceAll('_', ' ');
    return text.charAt(0).toUpperCase() + text.slice(1);
}

export default function ReadinessSection({ readiness = [], event }) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');
    const states = useMemo(() => [...new Set(readiness.map((item) => item.state))].sort(), [readiness]);
    const visible = useMemo(() => readiness.filter((item) => {
        const haystack = `${item.name} ${item.competition ?? ''} ${item.division ?? ''} ${item.next_blocker ?? ''}`.toLowerCase();
        return (!query || haystack.includes(query.toLowerCase())) && (!status || item.state === status);
    }), [readiness, query, status]);

    return <section aria-labelledby="readiness-heading" className="space-y-5">
        <header><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Control room</p><h2 id="readiness-heading" className="mt-1 font-serif text-3xl font-bold">Scoring readiness</h2><p className="mt-2 max-w-3xl text-sm leading-6 text-muted">See what each activity needs before scoring can proceed. The highlighted next step comes from the server workflow, not from whichever controls happen to be available.</p></header>
        {event.archived ? <div className="rounded-xl border border-accent bg-accent/10 p-4 text-sm text-foreground"><strong>Archived event.</strong> Readiness history remains available, but this event can no longer be modified.</div> : null}
        <section aria-label="Scoring readiness filters" className="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_14rem]"><label><span className="sr-only">Search scoring readiness</span><input type="search" value={query} onChange={(input) => setQuery(input.target.value)} placeholder="Search activity or blocker" className={control}/></label><label><span className="sr-only">Filter readiness status</span><select value={status} onChange={(input) => setStatus(input.target.value)} className={control}><option value="">All statuses</option>{states.map((state) => <option key={state} value={state}>{state === 'blocked' ? 'Blocked activities' : labelForState(state)}</option>)}</select></label></section>
        <div className="flex flex-wrap items-center justify-between gap-3"><p className="text-sm text-muted">Showing {visible.length} of {readiness.length} activities</p><p className="text-xs font-semibold text-muted">Open an activity to inspect setup and live operations.</p></div>
        {visible.length ? <div className="space-y-3">{visible.map((item) => <details key={item.id ?? item.name} className="group overflow-hidden rounded-xl border border-border bg-surface"><summary className="grid cursor-pointer list-none gap-3 p-5 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-serif text-xl font-bold">{item.name}</h3><span className={`rounded-full px-3 py-1 text-xs font-bold uppercase ${item.state === 'blocked' ? 'bg-danger-surface text-danger' : item.state === 'ready' ? 'bg-primary/10 text-primary' : 'bg-accent/20 text-foreground'}`}>{labelForState(item.state)}</span></div><p className="mt-1 text-sm text-muted">{item.division ? `${item.competition} · ${item.division}` : item.competition} · {item.counts?.entries ?? 0} entries · {item.counts?.judges ?? 0} Judges · {item.counts?.tabulators ?? 0} Tabulators</p><p className={`mt-2 text-sm font-semibold ${item.state === 'blocked' ? 'text-danger' : 'text-muted'}`}>{item.state === 'blocked' ? 'Setup controls are unavailable until the source is resolved.' : item.next_blocker ?? 'Ready for scoring operations.'}</p></div><span className="text-sm font-bold text-primary group-open:hidden">Open setup</span><span className="hidden text-sm font-bold text-primary group-open:inline">Close setup</span></summary><div className="grid gap-4 border-t border-border bg-surface-muted p-5 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]"><div><div className="mb-3 flex items-center gap-2"><span className="text-xs font-bold uppercase tracking-[0.12em] text-primary">{item.next_action_key ? `Next: ${item.next_action_key.replaceAll('_', ' ')}` : 'Workflow status'}</span></div><ReadinessActions item={item} archived={event.archived}/></div>{item.tie ? <TieResolutionForm tie={item.tie} archived={event.archived}/> : <div className="rounded-xl border border-border bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-primary">Live operations</p><p className="mt-2 text-sm text-muted">{item.readiness_steps?.find((step) => step.key === 'scores')?.detail ?? 'Judge score progress appears here after the panel is locked.'}</p><p className="mt-3 text-sm text-muted">{item.readiness_steps?.find((step) => step.key === 'tabulation')?.detail ?? 'Tabulation waits for complete Judge scorecards.'}</p></div>}</div></details>)}</div> : <div className="rounded-xl border border-dashed border-border bg-surface p-8 text-center"><h3 className="font-serif text-xl font-bold">No activities match</h3><p className="mt-2 text-sm text-muted">Clear the search or status filter to see the full readiness list.</p></div>}
    </section>;
}
