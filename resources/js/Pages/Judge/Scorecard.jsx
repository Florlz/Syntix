import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LiveProgress from '@/Components/LiveDesk/LiveProgress';
import OperationalStatus from '@/Components/LiveDesk/OperationalStatus';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useRef, useState } from 'react';

const stateTone = { approved: 'ready', submitted: 'ready', rejected: 'danger', draft: 'live' };

function editable(state) {
    return state === 'draft' || state === 'rejected';
}

function formatSchedule(schedule) {
    if (!schedule?.starts_at) return schedule?.fallback ?? 'Schedule not confirmed';
    return new Intl.DateTimeFormat(undefined, {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(new Date(schedule.starts_at));
}

function formatNumber(value) {
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
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

function fieldErrorIndex(criteria, errors) {
    return criteria.findIndex((criterion, index) => (
        errors?.[`values.${index}.raw_value`] || errors?.[`values.${index}.notes`]
    ));
}

function initialCriterionId(scorecard, values, errors = {}) {
    const invalidIndex = fieldErrorIndex(scorecard.criteria, errors);
    if (invalidIndex >= 0) return scorecard.criteria[invalidIndex].id;

    const incomplete = scorecard.criteria.find((criterion, index) => (
        criterion.required && String(values[index]?.raw_value ?? '').trim() === ''
    ));
    return incomplete?.id ?? scorecard.criteria[0]?.id ?? null;
}

export default function Scorecard({ scorecard }) {
    const flash = usePage().props.flash;
    const canEdit = editable(scorecard.state);
    const allowNavigation = useRef(false);
    const scoreInputRef = useRef(null);
    const synchronizedScorecard = useRef({ id: scorecard.id, revision: scorecard.revision });
    const draftForm = useForm(draftData(scorecard));
    const submitForm = useForm({});
    const [activeCriterionId, setActiveCriterionId] = useState(() => initialCriterionId(scorecard, draftForm.data.values, draftForm.errors));
    const [focusRequest, setFocusRequest] = useState(0);
    const [openNotes, setOpenNotes] = useState(() => new Set(
        draftForm.data.values.filter((value) => String(value.notes ?? '').trim() !== '').map((value) => String(value.criterion_id)),
    ));

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
        if (!scorecard.criteria.some((criterion) => String(criterion.id) === String(activeCriterionId))) {
            setActiveCriterionId(initialCriterionId(scorecard, draftForm.data.values, draftForm.errors));
        }
    }, [scorecard.id, scorecard.criteria, activeCriterionId]);

    const invalidIndex = fieldErrorIndex(scorecard.criteria, draftForm.errors);
    const invalidCriterionId = invalidIndex >= 0 ? scorecard.criteria[invalidIndex].id : null;

    const focusCriterion = (criterionId) => {
        setActiveCriterionId(criterionId);
        setFocusRequest((request) => request + 1);
    };

    const focusFirstFieldError = (errors) => {
        const errorIndex = fieldErrorIndex(scorecard.criteria, errors);
        if (errorIndex < 0) return;
        const criterionId = scorecard.criteria[errorIndex].id;
        if (errors[`values.${errorIndex}.notes`]) {
            setOpenNotes((current) => new Set([...current, String(criterionId)]));
        }
        focusCriterion(criterionId);
    };

    useEffect(() => {
        if (invalidCriterionId === null) return;
        setActiveCriterionId(invalidCriterionId);
        if (draftForm.errors[`values.${invalidIndex}.notes`]) {
            setOpenNotes((current) => new Set([...current, String(invalidCriterionId)]));
        }
    }, [invalidCriterionId]);

    useEffect(() => {
        if (focusRequest === 0) return;
        scoreInputRef.current?.focus({ preventScroll: true });
    }, [activeCriterionId, focusRequest]);

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
            onError: focusFirstFieldError,
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
            onError: (errors) => {
                allowNavigation.current = false;
                focusFirstFieldError(errors);
            },
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
    const activeIndex = Math.max(0, scorecard.criteria.findIndex((criterion) => String(criterion.id) === String(activeCriterionId)));
    const activeCriterion = scorecard.criteria[activeIndex];
    const activeValue = draftForm.data.values[activeIndex] ?? { raw_value: '', notes: '' };
    const activeFieldError = draftForm.errors[`values.${activeIndex}.raw_value`];
    const activeNotesError = draftForm.errors[`values.${activeIndex}.notes`];
    const notesAreOpen = openNotes.has(String(activeCriterion?.id)) || Boolean(activeValue.notes) || Boolean(activeNotesError);
    const minimum = Number(activeCriterion?.minimum ?? 0);
    const maximum = Number(activeCriterion?.maximum ?? 100);
    const midpoint = minimum + ((maximum - minimum) / 2);

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Judge live desk</p><h1 className="text-xl font-bold text-foreground">{scorecard.competition}</h1></div>}>
            <Head title={`${scorecard.entry} scorecard`} />
            <main className="min-h-[calc(100vh-4rem)] bg-background px-3 pb-48 pt-3 text-foreground sm:px-6 sm:pb-36 lg:px-8">
                <form onSubmit={save} className="mx-auto max-w-[90rem] space-y-4">
                    <EntryHeader scorecard={scorecard} />

                    {scorecard.state === 'rejected' && scorecard.rejection_reason ? <div role="alert" className="border border-danger/35 bg-danger-surface px-4 py-3 text-sm text-danger"><strong>Correction required:</strong> {scorecard.rejection_reason}</div> : null}
                    {flash?.status ? <div aria-live="polite" className="border border-primary/35 bg-primary/5 px-4 py-3 text-sm text-foreground">{flash.status}</div> : null}
                    {pageErrors.length ? <div role="alert" className="border border-danger/35 bg-danger-surface px-4 py-3 text-sm text-danger"><ul className="space-y-1">{pageErrors.map(([key, message]) => <li key={key}>{message}</li>)}</ul></div> : null}

                    {activeCriterion ? <div className="grid items-start gap-4 xl:grid-cols-[12rem_minmax(0,1fr)_18rem]">
                        <CriteriaIndex criteria={scorecard.criteria} values={draftForm.data.values} errors={draftForm.errors} activeCriterionId={activeCriterionId} onSelect={focusCriterion} completedRequired={completedRequired} requiredCount={requiredCriteria.length} />

                        <section aria-labelledby="active-criterion-heading" className="border border-border bg-surface">
                            <div className="grid gap-4 border-b border-border px-4 py-4 sm:grid-cols-[minmax(0,1fr)_14rem] sm:items-end sm:px-5">
                                <div><p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-primary">Criterion {activeIndex + 1} of {scorecard.criteria.length}</p><h3 id="active-criterion-heading" className="mt-1 text-2xl font-bold">Score this criterion</h3><p className="mt-1 text-sm text-muted">Focus on one requirement at a time. Your other entries stay in this draft.</p></div>
                                <LiveProgress label="Required criteria" value={completedRequired} max={requiredCriteria.length} detail={progressDetail} tone={completedRequired === requiredCriteria.length ? 'primary' : 'accent'} />
                            </div>

                            <fieldset aria-label={activeCriterion.name} disabled={!canEdit || busy} className="p-4 sm:p-6">
                                <legend className="w-full border-b border-border pb-4">
                                    <span className="flex flex-wrap items-start justify-between gap-3"><span><span className="block text-xl font-bold text-foreground">{activeCriterion.name}</span>{activeCriterion.source_label && activeCriterion.source_label !== activeCriterion.name ? <span className="mt-1 block text-xs text-muted">Source: {activeCriterion.source_label}</span> : null}</span><span className="border border-border bg-surface-muted px-3 py-1.5 font-condensed text-lg font-bold text-primary">{activeCriterion.weight ? `${formatNumber(activeCriterion.weight)}%` : 'Points'}</span></span>
                                </legend>

                                <div className="grid gap-6 py-6 md:grid-cols-[minmax(0,1fr)_13rem] md:items-center">
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Allowed range</p>
                                        <div className="mt-4 grid grid-cols-3 border-y border-border py-3 text-center font-condensed text-lg font-bold tabular-nums text-foreground"><span>{formatNumber(minimum)}</span><span className="border-x border-border">{formatNumber(midpoint)}</span><span>{formatNumber(maximum)}</span></div>
                                        <p className="mt-3 text-sm text-muted">{activeCriterion.required ? 'A score is required before submission.' : 'This criterion is optional.'}</p>
                                    </div>
                                    <div>
                                        <label htmlFor={`criterion-${activeCriterion.id}`} className="block text-center text-xs font-bold uppercase tracking-[0.14em] text-muted">Score</label>
                                        <input ref={scoreInputRef} id={`criterion-${activeCriterion.id}`} aria-label={`${activeCriterion.name} Score`} aria-describedby={`range-${activeCriterion.id}${activeFieldError ? ` error-${activeCriterion.id}` : ''}`} aria-invalid={Boolean(activeFieldError)} aria-required={activeCriterion.required} type="number" inputMode="decimal" step={activeCriterion.input_scale ? `0.${'0'.repeat(Math.max(0, activeCriterion.input_scale - 1))}1` : '1'} min={activeCriterion.minimum ?? undefined} max={activeCriterion.maximum ?? undefined} value={activeValue.raw_value} onChange={(event) => updateValue(activeIndex, 'raw_value', event.target.value)} className="mt-2 min-h-20 w-full border-2 border-primary bg-background px-3 text-center font-condensed text-6xl font-bold leading-none tabular-nums text-foreground focus:border-primary focus:ring-4 focus:ring-accent/40" />
                                        <span id={`range-${activeCriterion.id}`} className="sr-only">Enter a score from {minimum} to {maximum}</span>
                                        {activeFieldError ? <p id={`error-${activeCriterion.id}`} className="mt-2 text-sm font-semibold text-danger">{activeFieldError}</p> : null}
                                    </div>
                                </div>

                                <div className="border-t border-border pt-4">
                                    {!notesAreOpen ? <button type="button" onClick={() => setOpenNotes((current) => new Set([...current, String(activeCriterion.id)]))} className="min-h-11 text-sm font-bold text-primary hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Add notes</button> : <div>
                                        <div className="flex items-center justify-between gap-3"><label htmlFor={`notes-${activeCriterion.id}`} className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{activeCriterion.name} notes</label>{!activeValue.notes && !activeNotesError ? <button type="button" onClick={() => setOpenNotes((current) => { const next = new Set(current); next.delete(String(activeCriterion.id)); return next; })} className="min-h-11 px-2 text-xs font-bold text-primary hover:underline">Hide notes</button> : null}</div>
                                        <textarea id={`notes-${activeCriterion.id}`} aria-label={`${activeCriterion.name} notes`} rows={3} value={activeValue.notes} onChange={(event) => updateValue(activeIndex, 'notes', event.target.value)} className="mt-2 w-full resize-y border border-control-border bg-surface-muted text-sm text-foreground focus:border-primary focus:ring-primary" />
                                        {activeNotesError ? <p className="mt-2 text-sm font-semibold text-danger">{activeNotesError}</p> : null}
                                    </div>}
                                </div>
                            </fieldset>

                            <RemainingCriteria criteria={scorecard.criteria} values={draftForm.data.values} errors={draftForm.errors} activeCriterionId={activeCriterionId} onSelect={focusCriterion} />
                        </section>

                        <OfficialSummary scorecard={scorecard} />
                    </div> : <section className="border border-border bg-surface p-6"><h2 className="text-lg font-bold">No scoring criteria configured</h2><p className="mt-2 text-sm text-muted">Ask an event organizer to configure this contest before judging.</p></section>}

                    <ActionStrip scorecard={scorecard} canEdit={canEdit} busy={busy} isDirty={draftForm.isDirty} submit={submit} />
                </form>
            </main>
        </AuthenticatedLayout>
    );
}

function EntryHeader({ scorecard }) {
    return <header className="border border-border bg-surface">
        <div className="grid gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1fr)_minmax(30rem,0.9fr)] lg:items-center lg:px-5">
            <div className="min-w-0 border-t-2 border-primary pt-3">
                <div className="flex flex-wrap items-center gap-2"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">{scorecard.competition} · {scorecard.division}</p><OperationalStatus label={scorecard.state === 'rejected' ? 'Correction required' : scorecard.state} tone={stateTone[scorecard.state] ?? 'neutral'} /></div>
                <h2 className="mt-1 text-2xl font-bold leading-tight text-foreground">{scorecard.entry}</h2>
                <p className="mt-1 text-sm text-muted">{scorecard.delegation} · {scorecard.contest}</p>
            </div>
            <dl className="grid grid-cols-2 divide-x divide-border text-sm sm:grid-cols-3">
                <div className="pr-3"><dt className="text-[0.65rem] font-bold uppercase tracking-[0.1em] text-muted">Venue</dt><dd className="mt-1 font-semibold text-foreground">{scorecard.schedule.venue ?? 'Not scheduled'}</dd></div>
                <div className="px-3"><dt className="text-[0.65rem] font-bold uppercase tracking-[0.1em] text-muted">Call time</dt><dd className="mt-1 font-condensed text-lg font-bold text-foreground">{formatSchedule(scorecard.schedule)}</dd></div>
                <div className="col-span-2 mt-3 border-t border-border pt-3 sm:col-span-1 sm:mt-0 sm:border-t-0 sm:pl-3 sm:pt-0"><dt className="text-[0.65rem] font-bold uppercase tracking-[0.1em] text-muted">Assignment</dt><dd className="mt-1 font-condensed text-lg font-bold text-foreground">Entry {scorecard.navigation.position ?? '—'} of {scorecard.navigation.total}</dd></div>
            </dl>
        </div>
        <nav aria-label="Assigned entries" className="flex items-center justify-between border-t border-border px-2 py-1.5 sm:px-4">
            {scorecard.navigation.previous_id ? <Link href={route('judge.scorecards.show', scorecard.navigation.previous_id)} className="min-h-11 px-3 py-2.5 text-sm font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">← Previous entry</Link> : <span />}
            {scorecard.navigation.next_id ? <Link href={route('judge.scorecards.show', scorecard.navigation.next_id)} className="min-h-11 px-3 py-2.5 text-sm font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Next entry →</Link> : null}
        </nav>
    </header>;
}

function CriteriaIndex({ criteria, values, errors, activeCriterionId, onSelect, completedRequired, requiredCount }) {
    return <nav aria-label="Scoring criteria" className="border border-border bg-surface xl:sticky xl:top-4">
        <div className="border-b border-border px-3 py-3"><p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-muted">Criteria</p><p className="mt-1 font-condensed text-2xl font-bold tabular-nums text-primary">{completedRequired}/{requiredCount}</p></div>
        <ol className="grid grid-cols-2 divide-x divide-y divide-border sm:grid-cols-3 xl:block xl:divide-x-0">
            {criteria.map((criterion, index) => {
                const value = values[index];
                const complete = String(value?.raw_value ?? '').trim() !== '';
                const invalid = Boolean(errors[`values.${index}.raw_value`] || errors[`values.${index}.notes`]);
                const active = String(criterion.id) === String(activeCriterionId);
                const state = invalid ? 'needs correction' : complete ? `scored ${value.raw_value}` : 'not scored';
                return <li key={criterion.id}><button type="button" aria-current={active ? 'step' : undefined} aria-label={`Score ${criterion.name}, ${state}`} onClick={() => onSelect(criterion.id)} className={`flex min-h-16 w-full items-center gap-3 px-3 py-3 text-left focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent ${active ? 'bg-primary text-primary-foreground' : 'bg-surface text-foreground hover:bg-surface-muted'} ${invalid ? 'outline outline-2 -outline-offset-2 outline-danger' : ''}`}><span className={`grid size-7 shrink-0 place-items-center border font-condensed text-sm font-bold ${active ? 'border-primary-foreground/50' : 'border-border text-primary'}`}>{index + 1}</span><span className="min-w-0"><span className="block break-words text-xs font-bold">{criterion.name}</span><span className={`mt-0.5 block font-condensed text-xs ${active ? 'text-primary-foreground/80' : invalid ? 'text-danger' : 'text-muted'}`}>{criterion.weight ? `${formatNumber(criterion.weight)}%` : 'Points'} · {state}</span></span></button></li>;
            })}
        </ol>
    </nav>;
}

function RemainingCriteria({ criteria, values, errors, activeCriterionId, onSelect }) {
    if (criteria.length <= 1) return null;
    return <div className="border-t border-border">
        <div className="px-4 py-3 sm:px-5"><h4 className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Remaining criteria</h4></div>
        <div className="divide-y divide-border border-t border-border">
            {criteria.map((criterion, index) => {
                if (String(criterion.id) === String(activeCriterionId)) return null;
                const value = values[index];
                const complete = String(value?.raw_value ?? '').trim() !== '';
                const invalid = Boolean(errors[`values.${index}.raw_value`] || errors[`values.${index}.notes`]);
                return <button key={criterion.id} type="button" aria-label={`Open ${criterion.name}`} onClick={() => onSelect(criterion.id)} className="grid min-h-14 w-full grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-4 py-3 text-left hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent sm:px-5"><span><span className="font-bold text-foreground">{criterion.name}</span><span className={`ml-2 text-xs font-semibold ${invalid ? 'text-danger' : complete ? 'text-primary' : 'text-muted'}`}>{invalid ? 'Needs correction' : complete ? 'Scored' : 'Not scored'}</span></span><span className="flex items-center gap-4"><span className="font-condensed text-sm font-bold text-muted">{criterion.weight ? `${formatNumber(criterion.weight)}%` : 'Points'}</span><span className="min-w-10 text-right font-condensed text-xl font-bold tabular-nums text-foreground">{complete ? value.raw_value : '—'}</span></span></button>;
            })}
        </div>
    </div>;
}

function OfficialSummary({ scorecard }) {
    return <aside className="space-y-4 xl:sticky xl:top-4">
        <section aria-labelledby="saved-score-heading" className="border border-border bg-surface p-4"><p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-muted">Official summary</p><h3 id="saved-score-heading" className="mt-3 text-sm font-bold text-foreground">Saved weighted score</h3><p className="mt-1 font-condensed text-5xl font-bold tabular-nums text-primary">{Number(scorecard.calculated_total).toFixed(2)}</p><p className="mt-1 text-xs text-muted">Server calculated · revision {scorecard.revision}</p></section>
        <section aria-labelledby="source-heading" className="hidden border border-border bg-surface xl:block">
            <div className="p-4"><p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-muted">Scoring authority</p><h3 id="source-heading" className="mt-1 text-base font-bold">Approved proposal {scorecard.source.pages.length ? `pp. ${scorecard.source.pages.join('–')}` : ''}</h3><p className="mt-2 text-sm text-muted">{scorecard.source.reference ?? 'Approved event scoring source'}</p></div>
            <div className="border-t border-border px-4 py-3"><span className="text-xs font-bold uppercase tracking-[0.08em] text-primary">{scorecard.source.reliability}</span></div>
            <div className="border-t border-border px-4 py-4"><h4 className="text-sm font-bold text-foreground">Contest instructions</h4><Instructions scorecard={scorecard} /></div>
        </section>
        <section aria-labelledby="adjustments-heading" className="hidden border border-border bg-surface p-4 xl:block">
            <div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-muted">Official adjustments</p><h3 id="adjustments-heading" className="mt-1 text-base font-bold">Tabulator record</h3></div><strong className="font-condensed text-2xl tabular-nums text-danger">−{Number(scorecard.official_deduction_total).toFixed(2)}</strong></div>
            <Adjustments scorecard={scorecard} />
        </section>
        <div className="space-y-3 xl:hidden">
            <details className="border border-border bg-surface"><summary className="flex min-h-11 cursor-pointer items-center px-4 py-3 text-sm font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent">Scoring authority</summary><div className="border-t border-border p-4"><h3 className="text-base font-bold">Approved proposal {scorecard.source.pages.length ? `pp. ${scorecard.source.pages.join('–')}` : ''}</h3><p className="mt-2 text-sm text-muted">{scorecard.source.reference ?? 'Approved event scoring source'}</p><p className="mt-3 text-xs font-bold uppercase tracking-[0.08em] text-primary">{scorecard.source.reliability}</p></div></details>
            <details className="border border-border bg-surface"><summary className="flex min-h-11 cursor-pointer items-center px-4 py-3 text-sm font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent">Contest instructions</summary><div className="border-t border-border p-4"><Instructions scorecard={scorecard} /></div></details>
            <details className="border border-border bg-surface"><summary className="flex min-h-11 cursor-pointer items-center justify-between gap-3 px-4 py-3 text-sm font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent"><span>Official adjustments</span><span className="font-condensed text-lg tabular-nums text-danger">−{Number(scorecard.official_deduction_total).toFixed(2)}</span></summary><div className="border-t border-border p-4"><Adjustments scorecard={scorecard} /></div></details>
        </div>
    </aside>;
}

function Instructions({ scorecard }) {
    return scorecard.instructions.length ? <ul className="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-muted">{scorecard.instructions.map((item) => <li key={item}>{item}</li>)}</ul> : <p className="mt-2 text-sm text-muted">No additional proposal controls are recorded for this contest.</p>;
}

function Adjustments({ scorecard }) {
    return scorecard.official_adjustments.length ? <ul className="mt-4 divide-y divide-border border-t border-border">{scorecard.official_adjustments.map((item) => <li key={item.id} className="py-3 text-sm"><div className="flex justify-between gap-2"><strong>{item.label}</strong><span className="font-condensed font-bold tabular-nums text-danger">−{Number(item.points).toFixed(2)}</span></div><p className="mt-1 text-muted">{item.input} · {item.recorded_by}</p></li>)}</ul> : <p className="mt-3 text-sm text-muted">No official deductions recorded. Judges cannot edit shared operational penalties.</p>;
}

function ActionStrip({ scorecard, canEdit, busy, isDirty, submit }) {
    return <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface p-3 lg:left-64">
        <div className="mx-auto flex max-w-[90rem] flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex items-end justify-between gap-3 sm:items-center"><div><p className="text-[0.65rem] font-bold uppercase tracking-[0.12em] text-muted">Saved weighted score · revision {scorecard.revision}</p><p className="font-condensed text-3xl font-bold tabular-nums text-foreground">{Number(scorecard.calculated_total).toFixed(2)}</p></div>{canEdit ? <p aria-live="polite" className={`shrink-0 whitespace-nowrap text-xs font-semibold ${isDirty ? 'text-danger' : 'text-muted'}`}>{isDirty ? 'Unsaved changes' : 'Draft saved'}</p> : null}</div>
            {canEdit ? <div className="grid grid-cols-2 gap-2 sm:flex"><button type="submit" disabled={busy} className="min-h-11 whitespace-nowrap border border-control-border bg-surface px-3 text-xs font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-45 sm:px-5 sm:text-sm">Save draft</button><button type="button" onClick={submit} disabled={busy} className="min-h-11 whitespace-nowrap bg-primary px-3 text-xs font-bold text-primary-foreground hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:opacity-45 sm:px-5 sm:text-sm">{scorecard.state === 'rejected' ? 'Resubmit scorecard' : 'Review and submit'}</button></div> : <p className="text-sm font-semibold text-muted">{scorecard.state === 'approved' ? 'Approved scorecard · read only' : 'Submitted for review · read only'}</p>}
        </div>
    </div>;
}
