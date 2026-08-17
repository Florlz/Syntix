import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import React from 'react';
import { useEffect, useRef } from 'react';

const tone = {
    approved: 'border-primary/30 bg-primary/10 text-primary',
    submitted: 'border-primary/30 bg-primary/10 text-primary',
    rejected: 'border-danger/30 bg-danger-surface text-danger',
    draft: 'border-accent/50 bg-accent/15 text-foreground',
};

function formatSchedule(schedule) {
    if (!schedule?.starts_at) return schedule?.fallback ?? 'Venue not scheduled yet';
    const start = new Date(schedule.starts_at);
    return new Intl.DateTimeFormat(undefined, {
        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
    }).format(start);
}

export default function Scorecard({ scorecard }) {
    const flash = usePage().props.flash;
    const locked = !['draft', 'rejected'].includes(scorecard.state);
    const allowNavigation = useRef(false);
    const form = useForm({
        expected_revision: scorecard.revision,
        values: scorecard.criteria.map((criterion) => ({
            criterion_id: criterion.id,
            raw_value: scorecard.values[criterion.id]?.raw_value ?? '',
            deduction: scorecard.values[criterion.id]?.deduction ?? '0',
            notes: scorecard.values[criterion.id]?.notes ?? '',
        })),
    });

    useEffect(() => {
        const warnBeforeLeaving = (event) => {
            if (form.isDirty && !locked) {
                event.preventDefault();
                event.returnValue = '';
            }
        };
        const warnBeforeInertiaNavigation = (event) => {
            if (form.isDirty && !locked && !allowNavigation.current && !window.confirm('Leave this scorecard and discard unsaved changes?')) event.preventDefault();
        };
        window.addEventListener('beforeunload', warnBeforeLeaving);
        document.addEventListener('inertia:before', warnBeforeInertiaNavigation);
        return () => {
            window.removeEventListener('beforeunload', warnBeforeLeaving);
            document.removeEventListener('inertia:before', warnBeforeInertiaNavigation);
        };
    }, [form.isDirty, locked]);

    const updateValue = (index, field, value) => form.setData('values', form.data.values.map((item, itemIndex) => (
        itemIndex === index ? { ...item, [field]: value } : item
    )));

    const save = (event) => {
        event.preventDefault();
        allowNavigation.current = true;
        form.patch(route('judge.scorecards.update', scorecard.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                const revision = page.props.scorecard?.revision ?? form.data.expected_revision + 1;
                form.defaults({ expected_revision: revision, values: form.data.values });
                form.reset();
            },
            onFinish: () => { allowNavigation.current = false; },
        });
    };

    const submit = () => {
        allowNavigation.current = true;
        form.patch(route('judge.scorecards.update', scorecard.id), {
            preserveScroll: true,
            onSuccess: (page) => {
                const revision = page.props.scorecard?.revision ?? form.data.expected_revision + 1;
                form.defaults({ expected_revision: revision, values: form.data.values });
                form.reset();
                router.post(route('judge.scorecards.submit', scorecard.id), {}, {
                    preserveScroll: true,
                    onFinish: () => { allowNavigation.current = false; },
                });
            },
            onError: () => { allowNavigation.current = false; },
        });
    };

    return (
        <AuthenticatedLayout header={<div><p className="text-[0.68rem] font-bold uppercase tracking-[0.16em] text-primary">My judging</p><h1 className="font-serif text-2xl font-bold text-foreground">{scorecard.competition}</h1></div>}>
            <Head title={`${scorecard.entry} scorecard`} />
            <main className="min-h-[calc(100vh-4rem)] bg-background px-4 pb-40 pt-5 text-foreground sm:px-7 lg:px-8">
                <form onSubmit={save} className="mx-auto max-w-5xl space-y-5">
                    <header className="overflow-hidden rounded-2xl border border-border bg-primary text-primary-foreground shadow-sm">
                        <div className="grid gap-6 px-5 py-6 sm:px-7 lg:grid-cols-[1fr_auto] lg:items-end">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={`rounded-full border px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-[0.13em] ${tone[scorecard.state] ?? tone.draft}`}>{scorecard.state}</span>
                                    <span className="text-xs font-semibold text-primary-foreground/70">Entry {scorecard.navigation.position ?? '—'} of {scorecard.navigation.total}</span>
                                </div>
                                <h2 className="mt-4 font-serif text-3xl font-bold leading-none sm:text-4xl">{scorecard.entry}</h2>
                                <p className="mt-2 text-sm text-primary-foreground/75">{scorecard.delegation} · {scorecard.contest}</p>
                            </div>
                            <dl className="grid gap-3 text-sm sm:grid-cols-2 lg:text-right">
                                <div><dt className="text-xs font-bold uppercase tracking-[0.12em] text-primary-foreground/55">Venue</dt><dd className="mt-1 font-semibold">{scorecard.schedule.venue ?? 'Not scheduled'}</dd></div>
                                <div><dt className="text-xs font-bold uppercase tracking-[0.12em] text-primary-foreground/55">Call time</dt><dd className="mt-1 font-semibold">{formatSchedule(scorecard.schedule)}</dd></div>
                            </dl>
                        </div>
                        <nav aria-label="Assigned entries" className="flex items-center justify-between border-t border-primary-foreground/15 px-5 py-3 sm:px-7">
                            {scorecard.navigation.previous_id ? <Link href={route('judge.scorecards.show', scorecard.navigation.previous_id)} className="min-h-11 rounded-lg px-3 py-2.5 text-sm font-bold hover:bg-primary-foreground/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">← Previous entry</Link> : <span />}
                            {scorecard.navigation.next_id ? <Link href={route('judge.scorecards.show', scorecard.navigation.next_id)} className="min-h-11 rounded-lg px-3 py-2.5 text-sm font-bold hover:bg-primary-foreground/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Next entry →</Link> : null}
                        </nav>
                    </header>

                    {scorecard.rejection_reason ? <div role="alert" className="border-l-4 border-danger bg-danger-surface px-4 py-3 text-sm text-danger"><strong>Correction required:</strong> {scorecard.rejection_reason}</div> : null}
                    {flash?.status ? <div aria-live="polite" className="border-l-4 border-primary bg-primary/10 px-4 py-3 text-sm text-foreground">{flash.status}</div> : null}
                    {form.errors.scorecard ? <div role="alert" className="border-l-4 border-danger bg-danger-surface px-4 py-3 text-sm text-danger">{form.errors.scorecard}</div> : null}

                    <section aria-labelledby="source-heading" className="rounded-xl border border-border bg-surface">
                        <div className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                            <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Scoring authority</p><h3 id="source-heading" className="mt-1 font-serif text-xl font-bold">Approved proposal {scorecard.source.pages.length ? `pp. ${scorecard.source.pages.join('–')}` : ''}</h3></div>
                            <span className="rounded-full border border-primary/30 bg-primary/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.12em] text-primary">{scorecard.source.reliability}</span>
                        </div>
                        <details className="border-t border-border px-5 py-4">
                            <summary className="cursor-pointer font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Contest instructions</summary>
                            {scorecard.instructions.length ? <ul className="mt-3 list-disc space-y-1 pl-5 text-sm leading-6 text-muted">{scorecard.instructions.map((item) => <li key={item}>{item}</li>)}</ul> : <p className="mt-3 text-sm text-muted">No additional proposal controls are recorded for this contest.</p>}
                        </details>
                    </section>

                    <section aria-labelledby="rubric-heading" className="overflow-hidden rounded-xl border border-border bg-surface">
                        <div className="border-b border-border px-5 py-4"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Official rubric</p><h3 id="rubric-heading" className="mt-1 font-serif text-2xl font-bold">Score this entry</h3></div>
                        <div className="divide-y divide-border">
                            {scorecard.criteria.map((criterion, index) => (
                                <fieldset key={criterion.id} disabled={locked || form.processing} className="grid gap-4 p-5 sm:grid-cols-[1fr_10rem] sm:items-start sm:p-6">
                                    <legend className="sr-only">{criterion.name}</legend>
                                    <div>
                                        <div className="flex flex-wrap items-baseline justify-between gap-2"><label htmlFor={`criterion-${criterion.id}`} className="font-bold text-foreground">{criterion.name}</label><span className="text-sm font-bold tabular-nums text-primary">{criterion.weight ? `${Number(criterion.weight)}%` : 'Points'}</span></div>
                                        <p className="mt-1 text-sm text-muted">Allowed {Number(criterion.minimum ?? 0)}–{Number(criterion.maximum ?? 100)}</p>
                                        <label htmlFor={`notes-${criterion.id}`} className="mt-4 block text-xs font-bold uppercase tracking-[0.12em] text-muted">Judge notes</label>
                                        <input id={`notes-${criterion.id}`} value={form.data.values[index].notes} onChange={(event) => updateValue(index, 'notes', event.target.value)} className="mt-2 min-h-11 w-full rounded-lg border-border bg-surface-muted text-sm text-foreground focus:border-primary focus:ring-primary" />
                                    </div>
                                    <div>
                                        <label htmlFor={`criterion-${criterion.id}`} className="block text-xs font-bold uppercase tracking-[0.12em] text-muted">Score</label>
                                        <input id={`criterion-${criterion.id}`} aria-describedby={`range-${criterion.id}`} required={criterion.required} type="number" inputMode="decimal" step={criterion.input_scale ? `0.${'0'.repeat(Math.max(0, criterion.input_scale - 1))}1` : '1'} min={criterion.minimum ?? undefined} max={criterion.maximum ?? undefined} value={form.data.values[index].raw_value} onChange={(event) => updateValue(index, 'raw_value', event.target.value)} className="mt-2 min-h-14 w-full rounded-lg border-border bg-background text-center text-2xl font-bold tabular-nums text-foreground focus:border-primary focus:ring-primary" />
                                        <span id={`range-${criterion.id}`} className="sr-only">Enter a score from {criterion.minimum ?? 0} to {criterion.maximum ?? 100}</span>
                                    </div>
                                </fieldset>
                            ))}
                        </div>
                    </section>

                    <section aria-labelledby="adjustments-heading" className="rounded-xl border border-border bg-surface p-5">
                        <div className="flex flex-wrap items-end justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Official adjustments</p><h3 id="adjustments-heading" className="mt-1 font-serif text-xl font-bold">Recorded by the Tabulator</h3></div><strong className="text-xl tabular-nums text-danger">−{Number(scorecard.official_deduction_total).toFixed(2)}</strong></div>
                        {scorecard.official_adjustments.length ? <ul className="mt-4 divide-y divide-border border-t border-border">{scorecard.official_adjustments.map((item) => <li key={item.id} className="flex flex-wrap justify-between gap-2 py-3 text-sm"><span><strong>{item.label}</strong><span className="ml-2 text-muted">{item.input} · {item.recorded_by}</span></span><span className="font-bold tabular-nums text-danger">−{Number(item.points).toFixed(2)}</span></li>)}</ul> : <p className="mt-3 text-sm text-muted">No official deductions recorded. Judges cannot edit shared operational penalties.</p>}
                    </section>

                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-border bg-surface/95 p-3 shadow-[0_-12px_30px_rgb(15_23_42/0.08)] backdrop-blur lg:left-64">
                        <div className="mx-auto flex max-w-5xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Weighted Judge score · revision {scorecard.revision}</p><p className="font-serif text-3xl font-bold tabular-nums text-foreground">{Number(scorecard.calculated_total).toFixed(2)}</p></div>
                            <div className="grid grid-cols-2 gap-2 sm:flex">
                                <button type="submit" disabled={locked || form.processing} className="min-h-11 rounded-lg border border-border bg-surface px-5 text-sm font-bold text-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent disabled:opacity-45">Save draft</button>
                                <button type="button" onClick={submit} disabled={locked || form.processing} className="min-h-11 rounded-lg bg-primary px-5 text-sm font-bold text-primary-foreground hover:bg-primary/90 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:opacity-45">Submit scorecard</button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
