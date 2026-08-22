import React, { useState } from 'react';
import LiveProgress from '@/Components/LiveDesk/LiveProgress';
import OperationalStatus from '@/Components/LiveDesk/OperationalStatus';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { presentBlockers } from '@/lib/scoringBlockers';

function CriteriaDetail({ card }) {
    if (!card.criteria?.length) return null;

    return (
        <details className="mt-2 text-left">
            <summary className="cursor-pointer text-xs font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Criteria</summary>
            <dl className="mt-2 space-y-1 text-xs text-muted">
                {card.criteria.map((criterion) => (
                    <div key={criterion.criterion_id} className="flex justify-between gap-2">
                        <dt>{criterion.criterion}</dt>
                        <dd className="font-mono tabular-nums">{criterion.weighted_value}</dd>
                    </div>
                ))}
            </dl>
        </details>
    );
}

function AdjustmentForm({ contestId, entry, configuration, locked }) {
    const [value, setValue] = useState('');
    const disabled = locked || (entry.adjustments?.length ?? 0) > 0 || !configuration.code || configuration.calculation_status !== 'authorized';
    const submit = (event) => {
        event.preventDefault();
        router.post(route('tabulator.judged.adjustments.store', [contestId, entry.entry_id]), {
            code: configuration.code,
            input_value: Number(value),
            input_unit: configuration.input_unit,
        }, { preserveScroll: true, onSuccess: () => setValue('') });
    };

    return (
        <form onSubmit={submit} className="flex flex-wrap items-end gap-2">
            <label className="min-w-40 flex-1 text-xs font-semibold text-muted">
                Objective {configuration.input_unit}
                <input disabled={disabled} required type="number" inputMode="numeric" min="0" value={value} onChange={(event) => setValue(event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border-border bg-background text-foreground focus:border-primary focus:ring-primary"/>
            </label>
            <button disabled={disabled || value === ''} className="min-h-11 rounded-lg border border-border bg-surface px-4 text-sm font-bold text-primary transition-colors hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-45">Record adjustment</button>
            {configuration.calculation_status !== 'authorized' ? <p className="w-full text-xs font-semibold text-danger">Automatic calculation is blocked until its rounding/calculation policy is authorized.</p> : null}
        </form>
    );
}

function AdjustmentHistory({ items = [] }) {
    const voided = items.filter((item) => item.status === 'voided');
    if (!voided.length) return null;

    return (
        <div className="mt-3 border-t border-border pt-3">
            <p className="text-xs font-semibold text-muted">Adjustment history</p>
            {voided.map((item) => (
                <div key={item.id} className="mt-2 rounded-lg bg-danger-surface/40 p-3 text-sm">
                    <div className="flex items-start justify-between gap-3"><p className="font-bold">{item.label}</p><span className="text-xs font-bold text-danger">VOIDED</span></div>
                    <p className="mt-1 text-xs text-muted">{item.input_value} {item.input_unit} · {item.recorded_by || 'Tabulator'}</p>
                    <p className="mt-2 text-xs text-danger">Reason: {item.void_reason || 'Correction recorded'}</p>
                </div>
            ))}
        </div>
    );
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

function stateTone(state) {
    if (['ready', 'submitted', 'approved', 'completed'].includes(state)) return 'ready';
    if (state === 'waiting') return 'neutral';
    return 'danger';
}

export default function JudgedContest({ contest, tabulation, adjustment_configuration: configuration = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const entries = tabulation.entries ?? [];
    const judges = entries[0]?.scorecards ?? [];
    const operationalState = tabulation.operational_state ?? (tabulation.readiness.ready ? 'ready' : 'waiting');
    const readOnly = ['completed', 'submitted', 'approved'].includes(operationalState) || ['cancelled', 'suspended'].includes(contest.state);
    const blockers = presentBlockers(tabulation.readiness);
    const totalScorecards = entries.reduce((total, entry) => total + (entry.scorecards?.length ?? 0), 0);
    const receivedScorecards = entries.reduce((total, entry) => total + (entry.scorecards?.filter((card) => card.raw_total !== null && card.raw_total !== undefined && card.raw_total !== '').length ?? 0), 0);
    const readyToFinalize = operationalState === 'ready' && !readOnly;
    const finalizationStatus = readyToFinalize
        ? 'All evidence received. Finalize when the ranking is verified.'
        : blockers[0] ?? stateLabels[operationalState] ?? operationalState;
    const finalize = () => router.post(route('tabulator.judged.finalize', contest.id), {}, { preserveScroll: true });
    const voidAdjustment = (id) => {
        const reason = window.prompt('Why is this adjustment being corrected?');
        if (reason?.trim()) router.patch(route('tabulator.judged.adjustments.void', [contest.id, id]), { reason }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<div><h1 className="text-xl font-bold text-foreground sm:text-2xl">{contest.name}</h1><p className="mt-0.5 text-xs font-semibold text-muted">Judged tabulation · Live evidence desk</p></div>}>
            <Head title={`${contest.name} tabulation`}/>
            <main className="min-h-[calc(100vh-4rem)] bg-background p-4 pb-40 text-foreground sm:p-7 sm:pb-32 lg:p-8 lg:pb-32">
                <div className="mx-auto max-w-[96rem] space-y-6">
                    {flash.status ? <div role="status" className="border-l-4 border-primary bg-primary/10 p-4 text-sm">{flash.status}</div> : null}
                    {Object.keys(errors).length ? <div role="alert" className="border-l-4 border-danger bg-danger-surface p-4 text-sm text-danger">{Object.values(errors).join(' ')}</div> : null}

                    <section aria-label="Tabulation readiness" className="grid gap-4 border-y border-border py-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                        <LiveProgress label="Judge submissions" value={receivedScorecards} max={totalScorecards} detail={`${receivedScorecards} of ${totalScorecards} Judge submissions received`} tone={blockers.length ? 'danger' : 'primary'}/>
                        <div className="flex flex-wrap items-center gap-x-5 gap-y-2 lg:justify-end">
                            <p className="text-sm text-muted"><span className="font-condensed text-lg font-bold tabular-nums text-foreground">{judges.length}</span> Judges</p>
                            <p className="text-sm text-muted"><span className="font-condensed text-lg font-bold tabular-nums text-foreground">{entries.length}</span> Entries</p>
                            <OperationalStatus label={stateLabels[operationalState] ?? operationalState} tone={stateTone(operationalState)}/>
                        </div>
                    </section>

                    <section aria-labelledby="matrix-heading" className="overflow-hidden rounded-xl border border-border bg-surface">
                        <header className="border-b border-border p-5">
                            <h2 id="matrix-heading" className="text-xl font-bold text-foreground sm:text-2xl">Judge score matrix</h2>
                            <p className="mt-1 text-sm text-muted">Verify submitted scores and expand any cell to inspect its weighted criteria.</p>
                        </header>
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full min-w-[48rem] text-left text-sm">
                                <thead className="bg-surface-muted text-xs text-muted">
                                    <tr>
                                        <th className="px-5 py-3">Entry</th>
                                        {judges.map((judge) => <th key={judge.judge_id} className="px-4 py-3 text-right">{judge.judge ?? `Judge ${judge.judge_id}`}</th>)}
                                        <th className="px-4 py-3 text-right">Aggregate</th>
                                        <th className="px-4 py-3 text-right">Adjustments</th>
                                        <th className="px-5 py-3 text-right">Final</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {entries.map((entry) => (
                                        <tr key={entry.entry_id}>
                                            <th className="px-5 py-4"><span className="block font-bold">{entry.entry}</span><span className="text-xs font-normal text-muted">{entry.delegation}</span></th>
                                            {entry.scorecards.map((card) => <td key={card.judge_id} className={`px-4 py-4 text-right align-top ${card.raw_total == null ? 'font-semibold text-danger' : ''}`}><span className="font-mono tabular-nums">{card.raw_total ?? 'Missing'}</span><CriteriaDetail card={card}/></td>)}
                                            <td className="px-4 py-4 text-right font-mono tabular-nums">{entry.aggregate_raw_total ?? 'Waiting'}</td>
                                            <td className="px-4 py-4 text-right font-mono tabular-nums text-danger">−{entry.adjustment_total}</td>
                                            <td className="px-5 py-4 text-right font-mono font-bold tabular-nums">{entry.final_total ?? 'Waiting'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <div className="divide-y divide-border md:hidden">
                            {entries.map((entry) => (
                                <details key={entry.entry_id} className="p-4">
                                    <summary className="cursor-pointer list-none focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
                                        <span className="flex items-center justify-between gap-3 font-bold"><span>{entry.entry}</span><span className="font-mono tabular-nums">{entry.final_total ?? 'Waiting'}</span></span>
                                        <span className="mt-1 block text-xs font-normal text-muted">{entry.delegation}</span>
                                    </summary>
                                    <div className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                                        {entry.scorecards.map((card) => <div key={card.judge_id} className="rounded-lg bg-surface-muted p-3"><p className="text-xs text-muted">{card.judge ?? `Judge ${card.judge_id}`}</p><p className={`mt-1 font-mono font-bold ${card.raw_total == null ? 'text-danger' : ''}`}>{card.raw_total ?? 'Missing'}</p><CriteriaDetail card={card}/></div>)}
                                    </div>
                                </details>
                            ))}
                        </div>
                    </section>

                    {configuration.code ? (
                        <section aria-labelledby="adjustments-heading" className="rounded-xl border border-border bg-surface">
                            <div className="border-b border-border p-5"><h2 id="adjustments-heading" className="text-xl font-bold">Official adjustments</h2><p className="mt-1 text-sm text-muted">Record only authorized objective evidence. Existing evidence must be voided before replacement.</p></div>
                            <div className="divide-y divide-border lg:grid lg:grid-cols-2 lg:divide-x lg:divide-y-0">
                                {entries.map((entry) => (
                                    <div key={entry.entry_id} className="p-5">
                                        <h3 className="font-bold">{entry.entry}</h3>
                                        {entry.adjustments?.map((item) => <div key={item.id} className="mt-3 bg-surface-muted p-3 text-sm"><div className="flex items-start justify-between gap-3"><div><p className="font-bold">{item.label} · −{item.points}</p><p className="mt-1 text-xs text-muted">{item.input_value} {item.input_unit} · {item.recorded_by || 'Tabulator'}</p></div><button disabled={readOnly} onClick={() => voidAdjustment(item.id)} className="text-xs font-bold text-danger focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-40">Void &amp; replace</button></div></div>)}
                                        {!entry.adjustments?.length ? <p className="mt-3 text-sm text-muted">No active adjustment recorded.</p> : null}
                                        <AdjustmentHistory items={entry.adjustment_history}/>
                                        <div className="mt-3"><AdjustmentForm contestId={contest.id} entry={entry} configuration={configuration} locked={readOnly}/></div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    ) : null}
                </div>
            </main>

            <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface/95 p-3 shadow-lg backdrop-blur lg:left-64">
                <div className="mx-auto flex max-w-[96rem] flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="finalization-status" aria-live="polite" className={`text-sm font-semibold ${readyToFinalize ? 'text-primary' : 'text-danger'}`}>{finalizationStatus}</p>
                    <button type="button" onClick={finalize} aria-describedby="finalization-status" disabled={!readyToFinalize} className="min-h-12 shrink-0 rounded-lg bg-primary px-6 text-sm font-bold text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:opacity-45">Finalize and submit result</button>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
