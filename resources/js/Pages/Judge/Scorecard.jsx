import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LiveProgress from '@/Components/LiveDesk/LiveProgress';
import OperationalStatus from '@/Components/LiveDesk/OperationalStatus';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useRef } from 'react';

const stateTone = {
    approved: 'ready',
    submitted: 'ready',
    rejected: 'danger',
    draft: 'live',
};

function editable(state) {
    return state === 'draft' || state === 'rejected';
}

function formatSchedule(schedule) {
    if (!schedule?.starts_at) return schedule?.fallback ?? 'Schedule not confirmed';
    const start = new Date(schedule.starts_at);
    return new Intl.DateTimeFormat(undefined, {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(start);
}

function draftData(scorecard) {
    return {
        expected_revision: scorecard.revision,
        values: scorecard.criteria.map((criterion) => ({
            criterion_id: criterion.id,
            raw_value: scorecard.values[criterion.id]?.raw_value ?? '',
            deduction: scorecard.values[criterion.id]?.deduction ?? '0',
            notes: scorecard.values[criterion.id]?.notes ?? '',
        })),
    };
}

function draftPayload(data) {
    return {
        expected_revision: data.expected_revision,
        values: data.values
            .filter((value) => String(value.raw_value ?? '').trim() !== '')
            .map((value) => ({
                criterion_id: value.criterion_id,
                raw_value: value.raw_value,
                deduction: 0,
                notes: String(value.notes ?? '').trim() || null,
            })),
    };
}

function topLevelErrors(errors) {
    return Object.entries(errors ?? {}).filter(([key]) => !/^values\.\d+\./.test(key));
}

export default function Scorecard({ scorecard }) {
    const flash = usePage().props.flash;
    const canEdit = editable(scorecard.state);
    const allowNavigation = useRef(false);
    const synchronizedScorecard = useRef({ id: scorecard.id, revision: scorecard.revision });
    const draftForm = useForm(draftData(scorecard));
    const submitForm = useForm({});

    const synchronizeFromServer = (freshScorecard) => {
        if (!freshScorecard || freshScorecard.revision === undefined) return;

        const nextData = draftData(freshScorecard);
        draftForm.defaults(nextData);
        draftForm.reset();
        synchronizedScorecard.current = { id: freshScorecard.id, revision: freshScorecard.revision };
    };

    useEffect(() => {
        const freshIdentity = synchronizedScorecard.current.id !== scorecard.id
            || synchronizedScorecard.current.revision !== scorecard.revision;

        if (freshIdentity && !draftForm.isDirty) synchronizeFromServer(scorecard);
    }, [scorecard.id, scorecard.revision, draftForm.isDirty]);

    useEffect(() => {
        const warnBeforeLeaving = (event) => {
            if (draftForm.isDirty && canEdit) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        const warnBeforeInertiaNavigation = (event) => {
            if (draftForm.isDirty && canEdit && !allowNavigation.current && !window.confirm('Leave this scorecard and discard unsaved changes?')) event.preventDefault();
        };
        window.addEventListener('beforeunload', warnBeforeLeaving);
        document.addEventListener('inertia:before', warnBeforeInertiaNavigation);
        return () => {
            window.removeEventListener('beforeunload', warnBeforeLeaving);
            document.removeEventListener('inertia:before', warnBeforeInertiaNavigation);
        };
    }, [draftForm.isDirty, canEdit]);

    const updateValue = (index, field, value) => draftForm.setData('values', draftForm.data.values.map((item, itemIndex) => (
        itemIndex === index ? { ...item, [field]: value } : item
    )));

    const save = (event) => {
        event.preventDefault();
        if (!canEdit) return;

        allowNavigation.current = true;
        draftForm.transform(draftPayload).patch(route('judge.scorecards.update', scorecard.id), {
            preserveScroll: true,
            onSuccess: (page) => synchronizeFromServer(page.props.scorecard),
            onFinish: () => { allowNavigation.current = false; },
        });
    };

    const submit = () => {
        if (!canEdit) return;

        allowNavigation.current = true;
        draftForm.transform(draftPayload).patch(route('judge.scorecards.update', scorecard.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                synchronizeFromServer(page.props.scorecard);
                submitForm.post(route('judge.scorecards.submit', scorecard.id), {
                    preserveScroll: true,
                    onFinish: () => { allowNavigation.current = false; },
                });
            },
            onError: () => { allowNavigation.current = false; },
        });
    };

    const pageErrors = [
        ...topLevelErrors(draftForm.errors).map(([key, message]) => [`draft-${key}`, message]),
        ...topLevelErrors(submitForm.errors).map(([key, message]) => [`submit-${key}`, message]),
    ];
    const busy = draftForm.processing || submitForm.processing;
    const requiredCriteria = scorecard.criteria.filter((criterion) => criterion.required);
    const completedRequired = requiredCriteria.filter((criterion) => {
        const value = draftForm.data.values.find((item) => String(item.criterion_id) === String(criterion.id));
        return String(value?.raw_value ?? '').trim() !== '';
    }).length;
    const progressDetail = `${completedRequired} of ${requiredCriteria.length} required criteria scored`;

    return (
        <AuthenticatedLayout header={<div><p className="text-sm font-semibold text-muted">Judge live desk</p><h1 className="text-xl font-bold text-foreground">{scorecard.competition}</h1></div>}>
            <Head title={`${scorecard.entry} scorecard`} />
            <main className="min-h-[calc(100vh-4rem)] bg-background px-4 pb-44 pt-4 text-foreground sm:px-7 sm:pb-36 lg:px-8">
                <form onSubmit={save} className="mx-auto max-w-6xl space-y-5">
                    <header className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                        <div className="grid gap-5 px-5 py-5 lg:grid-cols-[minmax(0,1fr)_minmax(24rem,0.8fr)] lg:items-center lg:px-6">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <p className="text-sm font-semibold text-muted">{scorecard.competition} · {scorecard.division}</p>
                                    <OperationalStatus label={scorecard.state === 'rejected' ? 'Correction required' : scorecard.state} tone={stateTone[scorecard.state] ?? 'neutral'} />
                                </div>
                                <h2 className="mt-2 text-2xl font-bold leading-tight text-foreground">{scorecard.entry}</h2>
                                <p className="mt-2 text-sm text-muted">{scorecard.delegation} · {scorecard.contest}</p>
                            </div>
                            <dl className="grid grid-cols-2 gap-x-5 gap-y-3 text-sm sm:grid-cols-3">
                                <div><dt className="text-xs font-semibold text-muted">Venue</dt><dd className="mt-1 font-semibold text-foreground">{scorecard.schedule.venue ?? 'Not scheduled'}</dd></div>
                                <div><dt className="text-xs font-semibold text-muted">Call time</dt><dd className="mt-1 font-semibold text-foreground">{formatSchedule(scorecard.schedule)}</dd></div>
                                <div><dt className="text-xs font-semibold text-muted">Assignment</dt><dd className="mt-1 font-semibold text-foreground">Entry {scorecard.navigation.position ?? '—'} of {scorecard.navigation.total}</dd></div>
                            </dl>
                        </div>
                        <nav aria-label="Assigned entries" className="flex items-center justify-between border-t border-border px-3 py-2 sm:px-5">
                            {scorecard.navigation.previous_id ? <Link href={route('judge.scorecards.show', scorecard.navigation.previous_id)} className="min-h-11 rounded-lg px-3 py-2.5 text-sm font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">← Previous entry</Link> : <span />}
                            {scorecard.navigation.next_id ? <Link href={route('judge.scorecards.show', scorecard.navigation.next_id)} className="min-h-11 rounded-lg px-3 py-2.5 text-sm font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Next entry →</Link> : null}
                        </nav>
                    </header>

                    {scorecard.state === 'rejected' && scorecard.rejection_reason ? <div role="alert" className="rounded-lg border border-danger/30 bg-danger-surface px-4 py-3 text-sm text-danger"><strong>Correction required:</strong> {scorecard.rejection_reason}</div> : null}
                    {flash?.status ? <div aria-live="polite" className="rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-foreground">{flash.status}</div> : null}
                    {pageErrors.length ? <div role="alert" className="rounded-lg border border-danger/30 bg-danger-surface px-4 py-3 text-sm text-danger"><ul className="space-y-1">{pageErrors.map(([key, message]) => <li key={key}>{message}</li>)}</ul></div> : null}

                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
                        <section aria-labelledby="rubric-heading" className="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
                            <div className="grid gap-4 border-b border-border px-5 py-5 sm:grid-cols-[minmax(0,1fr)_16rem] sm:items-end sm:px-6">
                                <div><h3 id="rubric-heading" className="text-xl font-bold">Score this entry</h3><p className="mt-1 text-sm text-muted">Enter each required score. Notes are optional and stay with your scorecard.</p></div>
                                <LiveProgress label="Required criteria" value={completedRequired} max={requiredCriteria.length} detail={progressDetail} tone={completedRequired === requiredCriteria.length ? 'primary' : 'accent'} />
                            </div>
                            <div className="divide-y divide-border">
                                {scorecard.criteria.map((criterion, index) => {
                                    const value = draftForm.data.values[index] ?? { raw_value: '', notes: '' };
                                    const fieldError = draftForm.errors[`values.${index}.raw_value`];
                                    const rangeId = `range-${criterion.id}`;
                                    const errorId = `error-${criterion.id}`;

                                    return (
                                        <fieldset key={criterion.id} disabled={!canEdit || busy} className="grid gap-4 p-5 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-start sm:p-6">
                                            <legend className="sr-only">{criterion.name}</legend>
                                            <div>
                                                <div className="flex flex-wrap items-baseline justify-between gap-2"><label htmlFor={`criterion-${criterion.id}`} className="font-bold text-foreground">{criterion.name}</label><span className="text-sm font-bold tabular-nums text-primary">{criterion.weight ? `${Number(criterion.weight)}%` : 'Points'}</span></div>
                                                <p className="mt-1 text-sm text-muted">Allowed {Number(criterion.minimum ?? 0)}–{Number(criterion.maximum ?? 100)}{criterion.required ? ' · Required to submit' : ''}</p>
                                                <label htmlFor={`notes-${criterion.id}`} className="mt-4 block text-xs font-semibold text-muted">Judge notes</label>
                                                <textarea id={`notes-${criterion.id}`} rows={2} value={value.notes} onChange={(event) => updateValue(index, 'notes', event.target.value)} className="mt-2 w-full resize-y rounded-lg border-border bg-surface-muted text-sm text-foreground focus:border-primary focus:ring-primary" />
                                            </div>
                                            <div>
                                                <label htmlFor={`criterion-${criterion.id}`} className="block text-xs font-semibold text-muted">Score</label>
                                                <input id={`criterion-${criterion.id}`} aria-label={`${criterion.name} Score`} aria-describedby={`${rangeId}${fieldError ? ` ${errorId}` : ''}`} aria-invalid={Boolean(fieldError)} aria-required={criterion.required} type="number" inputMode="decimal" step={criterion.input_scale ? `0.${'0'.repeat(Math.max(0, criterion.input_scale - 1))}1` : '1'} min={criterion.minimum ?? undefined} max={criterion.maximum ?? undefined} value={value.raw_value} onChange={(event) => updateValue(index, 'raw_value', event.target.value)} className="mt-2 min-h-14 w-full rounded-lg border-border bg-background text-center text-2xl font-bold tabular-nums text-foreground focus:border-primary focus:ring-primary" />
                                                <span id={rangeId} className="sr-only">Enter a score from {criterion.minimum ?? 0} to {criterion.maximum ?? 100}</span>
                                                {fieldError ? <p id={errorId} className="mt-2 text-sm font-semibold text-danger">{fieldError}</p> : null}
                                            </div>
                                        </fieldset>
                                    );
                                })}
                            </div>
                        </section>

                        <aside className="space-y-5">
                            <section aria-labelledby="source-heading" className="rounded-xl border border-border bg-surface">
                                <div className="px-5 py-4"><p className="text-xs font-semibold text-muted">Scoring authority</p><h3 id="source-heading" className="mt-1 text-lg font-bold">Approved proposal {scorecard.source.pages.length ? `pp. ${scorecard.source.pages.join('–')}` : ''}</h3><p className="mt-2 text-sm text-muted">{scorecard.source.reference ?? 'Approved event scoring source'}</p></div>
                                <div className="border-t border-border px-5 py-3"><span className="text-xs font-bold text-primary">{scorecard.source.reliability}</span></div>
                                <details className="border-t border-border px-5 py-4">
                                    <summary className="cursor-pointer font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Contest instructions</summary>
                                    {scorecard.instructions.length ? <ul className="mt-3 list-disc space-y-1 pl-5 text-sm leading-6 text-muted">{scorecard.instructions.map((item) => <li key={item}>{item}</li>)}</ul> : <p className="mt-3 text-sm text-muted">No additional proposal controls are recorded for this contest.</p>}
                                </details>
                            </section>

                            <section aria-labelledby="adjustments-heading" className="rounded-xl border border-border bg-surface p-5">
                                <div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-semibold text-muted">Official adjustments</p><h3 id="adjustments-heading" className="mt-1 text-lg font-bold">Tabulator record</h3></div><strong className="text-xl tabular-nums text-danger">−{Number(scorecard.official_deduction_total).toFixed(2)}</strong></div>
                                {scorecard.official_adjustments.length ? <ul className="mt-4 divide-y divide-border border-t border-border">{scorecard.official_adjustments.map((item) => <li key={item.id} className="py-3 text-sm"><div className="flex justify-between gap-2"><strong>{item.label}</strong><span className="font-bold tabular-nums text-danger">−{Number(item.points).toFixed(2)}</span></div><p className="mt-1 text-muted">{item.input} · {item.recorded_by}</p></li>)}</ul> : <p className="mt-3 text-sm text-muted">No official deductions recorded. Judges cannot edit shared operational penalties.</p>}
                            </section>
                        </aside>
                    </div>

                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface/95 p-3 shadow-lg backdrop-blur lg:left-64">
                        <div className="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-end justify-between gap-5 sm:block"><div><p className="text-xs font-semibold text-muted">Weighted judge score · revision {scorecard.revision}</p><p className="text-2xl font-bold tabular-nums text-foreground">{Number(scorecard.calculated_total).toFixed(2)}</p></div>{canEdit ? <p aria-live="polite" className={`text-sm font-semibold ${draftForm.isDirty ? 'text-danger' : 'text-muted'}`}>{draftForm.isDirty ? 'Unsaved changes' : 'Draft saved'}</p> : null}</div>
                            {canEdit ? <div className="grid grid-cols-2 gap-2 sm:flex">
                                <button type="submit" disabled={busy} className="min-h-11 rounded-lg border border-border bg-surface px-5 text-sm font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-45">Save draft</button>
                                <button type="button" onClick={submit} disabled={busy} className="min-h-11 rounded-lg bg-primary px-5 text-sm font-bold text-primary-foreground hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:opacity-45">{scorecard.state === 'rejected' ? 'Resubmit scorecard' : 'Review and submit'}</button>
                            </div> : <p className="text-sm font-semibold text-muted">{scorecard.state === 'approved' ? 'Approved scorecard · read only' : 'Submitted for review · read only'}</p>}
                        </div>
                    </div>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
