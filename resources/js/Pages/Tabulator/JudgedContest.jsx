import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { presentBlockers } from '@/lib/scoringBlockers';
import { useState } from 'react';

function CriteriaDetail({ card }) {
    if (!card.criteria?.length) return null;
    return <details className="mt-2"><summary className="cursor-pointer text-xs font-bold text-primary">Criteria</summary><dl className="mt-2 space-y-1 text-xs text-muted">{card.criteria.map((criterion) => <div key={criterion.criterion_id} className="flex justify-between gap-2"><dt>{criterion.criterion}</dt><dd className="font-mono">{criterion.weighted_value}</dd></div>)}</dl></details>;
}

function AdjustmentForm({ contestId, entry, configuration, locked }) {
    const [value, setValue] = useState('');
    const disabled = locked || (entry.adjustments?.length ?? 0) > 0 || !configuration.code || configuration.calculation_status !== 'authorized';
    const submit = (event) => {
        event.preventDefault();
        router.post(route('tabulator.judged.adjustments.store', [contestId, entry.entry_id]), {
            code: configuration.code, input_value: Number(value), input_unit: configuration.input_unit,
        }, { preserveScroll: true, onSuccess: () => setValue('') });
    };
    return <form onSubmit={submit} className="flex flex-wrap items-end gap-2"><label className="min-w-40 flex-1 text-xs font-bold uppercase tracking-[0.1em] text-muted">Objective {configuration.input_unit}<input disabled={disabled} required type="number" inputMode="numeric" min="0" value={value} onChange={(event) => setValue(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border-border bg-background text-foreground focus:border-primary focus:ring-primary"/></label><button disabled={disabled || value === ''} className="min-h-11 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground disabled:opacity-45">Record adjustment</button>{configuration.calculation_status !== 'authorized' ? <p className="w-full text-xs font-semibold text-danger">Automatic calculation is blocked until its rounding/calculation policy is authorized.</p> : null}</form>;
}

function AdjustmentHistory({ items = [] }) {
    const voided = items.filter((item) => item.status === 'voided');
    if (!voided.length) return null;

    return <div className="mt-3 border-t border-border pt-3"><p className="text-xs font-bold uppercase tracking-[0.1em] text-muted">Adjustment history</p>{voided.map((item) => <div key={item.id} className="mt-2 rounded-lg border border-danger/30 bg-danger-surface/40 p-3 text-sm"><div className="flex items-start justify-between gap-3"><p className="font-bold">{item.label}</p><span className="rounded-full bg-danger-surface px-2 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] text-danger">VOIDED</span></div><p className="mt-1 text-xs text-muted">{item.input_value} {item.input_unit} · {item.recorded_by || 'Tabulator'}</p><p className="mt-2 text-xs text-danger">Reason: {item.void_reason || 'Correction recorded'}</p></div>)}</div>;
}

const stateLabels = {
    waiting: 'Waiting for Judge submissions',
    adjustment_required: 'Judge scoring complete · adjustment evidence required',
    tie: 'Tabulation blocked · Admin tie resolution required',
    ready: 'Ready to finalize',
    completed: 'Final score recorded',
    submitted: 'Result submitted for Global Admin review.',
    approved: 'Official result approved',
};

export default function JudgedContest({ contest, tabulation, adjustment_configuration: configuration = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const entries = tabulation.entries ?? [];
    const judges = entries[0]?.scorecards ?? [];
    const operationalState = tabulation.operational_state ?? (tabulation.readiness.ready ? 'ready' : 'waiting');
    const readOnly = ['completed', 'submitted', 'approved'].includes(operationalState) || ['cancelled', 'suspended'].includes(contest.state);
    const blockers = presentBlockers(tabulation.readiness);
    const locked = readOnly;
    const finalize = () => router.post(route('tabulator.judged.finalize', contest.id), {}, { preserveScroll: true });
    const voidAdjustment = (id) => { const reason = window.prompt('Why is this adjustment being corrected?'); if (reason?.trim()) router.patch(route('tabulator.judged.adjustments.void', [contest.id, id]), { reason }, { preserveScroll: true }); };

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Judged tabulation</p><h1 className="font-serif text-2xl font-bold">{contest.name}</h1></div>}>
        <Head title={`${contest.name} tabulation`}/>
        <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8"><div className="mx-auto max-w-[96rem] space-y-6">
            {flash.status ? <div role="status" className="border-l-4 border-primary bg-primary/10 p-4 text-sm">{flash.status}</div> : null}
            {Object.keys(errors).length ? <div role="alert" className="border-l-4 border-danger bg-danger-surface p-4 text-sm text-danger">{Object.values(errors).join(' ')}</div> : null}
            <section aria-label="Tabulation readiness" className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-3"><div className="bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Panel</p><p className="mt-1 font-serif text-2xl font-bold">{judges.length} Judges</p></div><div className="bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Entries</p><p className="mt-1 font-serif text-2xl font-bold">{entries.length}</p></div><div className="bg-surface p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Status</p><p aria-live="polite" className={`mt-1 font-bold ${['ready', 'submitted', 'approved'].includes(operationalState) ? 'text-primary' : 'text-danger'}`}>{stateLabels[operationalState] ?? operationalState}</p>{blockers.length ? <ul className="mt-2 space-y-1 text-xs text-danger">{blockers.map((blocker) => <li key={blocker}>{blocker}</li>)}</ul> : null}</div></section>
            <section aria-labelledby="matrix-heading" className="overflow-hidden rounded-xl border border-border bg-surface"><header className="border-b border-border p-5"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Locked evidence</p><h2 id="matrix-heading" className="mt-1 font-serif text-2xl font-bold">Judge score matrix</h2><p className="mt-1 text-sm text-muted">Raw Judge values are read-only. Expand a cell to inspect weighted criteria.</p></header>
                <div className="hidden overflow-x-auto md:block"><table className="w-full min-w-[48rem] text-left text-sm"><thead className="bg-surface-muted text-xs uppercase tracking-[0.1em] text-muted"><tr><th className="px-5 py-3">Entry</th>{judges.map((judge) => <th key={judge.judge_id} className="px-4 py-3">{judge.judge ?? `Judge ${judge.judge_id}`}</th>)}<th className="px-4 py-3">Aggregate</th><th className="px-4 py-3">Adjustments</th><th className="px-5 py-3">Final</th></tr></thead><tbody className="divide-y divide-border">{entries.map((entry) => <tr key={entry.entry_id}><th className="px-5 py-4"><span className="block font-bold">{entry.entry}</span><span className="text-xs font-normal text-muted">{entry.delegation}</span></th>{entry.scorecards.map((card) => <td key={card.judge_id} className="px-4 py-4 align-top"><span className="font-mono tabular-nums">{card.raw_total ?? '—'}</span><CriteriaDetail card={card}/></td>)}<td className="px-4 py-4 font-mono tabular-nums">{entry.aggregate_raw_total ?? '—'}</td><td className="px-4 py-4 font-mono tabular-nums text-danger">−{entry.adjustment_total}</td><td className="px-5 py-4 font-mono font-bold tabular-nums">{entry.final_total ?? '—'}</td></tr>)}</tbody></table></div>
                <div className="divide-y divide-border md:hidden">{entries.map((entry) => <details key={entry.entry_id} className="p-4"><summary className="flex cursor-pointer items-center justify-between gap-3 font-bold"><span>{entry.entry}</span><span className="font-mono tabular-nums">{entry.final_total ?? 'Waiting'}</span></summary><div className="mt-4 grid grid-cols-2 gap-3 text-sm">{entry.scorecards.map((card) => <div key={card.judge_id} className="rounded-lg bg-surface-muted p-3"><p className="text-xs text-muted">{card.judge ?? `Judge ${card.judge_id}`}</p><p className="mt-1 font-mono font-bold">{card.raw_total ?? 'Missing'}</p><CriteriaDetail card={card}/></div>)}</div></details>)}</div>
            </section>
            {configuration.code ? <section className="rounded-xl border border-border bg-surface p-5"><h2 className="font-serif text-xl font-bold">Official adjustments</h2><div className="mt-4 grid gap-4 lg:grid-cols-2">{entries.map((entry) => <div key={entry.entry_id} className="rounded-lg border border-border p-4"><h3 className="font-bold">{entry.entry}</h3>{entry.adjustments?.map((item) => <div key={item.id} className="mt-3 rounded-lg bg-surface-muted p-3 text-sm"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{item.label} · −{item.points}</p><p className="mt-1 text-xs text-muted">{item.input_value} {item.input_unit} · {item.recorded_by || 'Tabulator'}</p></div><button disabled={locked} onClick={() => voidAdjustment(item.id)} className="text-xs font-bold text-danger disabled:opacity-40">Void &amp; replace</button></div></div>)}{!entry.adjustments?.length ? <p className="mt-3 text-sm text-muted">No active adjustment recorded.</p> : null}<AdjustmentHistory items={entry.adjustment_history}/><div className="mt-3"><AdjustmentForm contestId={contest.id} entry={entry} configuration={configuration} locked={locked}/></div></div>)}</div></section> : null}
            <div className="sticky bottom-3 flex justify-end"><button onClick={finalize} disabled={operationalState !== 'ready' || locked} className="min-h-12 rounded-lg bg-primary px-6 text-sm font-bold text-primary-foreground shadow-lg focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-45">Finalize &amp; submit result</button></div>
        </div></main>
    </AuthenticatedLayout>;
}
