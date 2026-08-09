import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

function stateTone(state) {
    if (state === 'submitted' || state === 'approved') return 'bg-emerald-100 text-emerald-800';
    if (state === 'rejected') return 'bg-rose-100 text-rose-800';
    return 'bg-amber-100 text-amber-800';
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
        function warnBeforeLeaving(event) {
            if (form.isDirty && !locked) {
                event.preventDefault();
                event.returnValue = '';
            }
        }

        function warnBeforeInertiaNavigation(event) {
            if (form.isDirty && !locked && !allowNavigation.current && !window.confirm('Leave this scorecard and discard unsaved changes?')) {
                event.preventDefault();
            }
        }

        window.addEventListener('beforeunload', warnBeforeLeaving);
        document.addEventListener('inertia:before', warnBeforeInertiaNavigation);
        return () => {
            window.removeEventListener('beforeunload', warnBeforeLeaving);
            document.removeEventListener('inertia:before', warnBeforeInertiaNavigation);
        };
    }, [form.isDirty, locked]);

    function updateValue(index, field, value) {
        form.setData('values', form.data.values.map((item, itemIndex) => (
            itemIndex === index ? { ...item, [field]: value } : item
        )));
    }

    function save(event) {
        event.preventDefault();
        allowNavigation.current = true;
        form.patch(route('judge.scorecards.update', scorecard.id), {
            preserveScroll: true,
            onFinish: () => { allowNavigation.current = false; },
        });
    }

    function submit() {
        allowNavigation.current = true;
        form.patch(route('judge.scorecards.update', scorecard.id), {
            preserveScroll: true,
            onSuccess: () => router.post(route('judge.scorecards.submit', scorecard.id), {}, {
                preserveScroll: true,
                onFinish: () => { allowNavigation.current = false; },
            }),
            onError: () => { allowNavigation.current = false; },
        });
    }

    return (
        <AuthenticatedLayout header={<h1 className="text-2xl font-semibold tracking-tight text-slate-900">Judge scorecard</h1>}>
            <Head title={`${scorecard.entry} scorecard`} />
            <main className="min-h-[calc(100vh-9rem)] bg-[#eef2f4] px-4 pb-36 pt-6 sm:px-6 lg:px-10">
                <form onSubmit={save} className="mx-auto max-w-5xl">
                    <section className="relative overflow-hidden bg-[#0b2e4f] px-5 py-6 text-white shadow-xl sm:rounded-[2rem] sm:px-8 sm:py-8">
                        <div className="absolute -right-14 -top-16 size-48 rounded-full border-[18px] border-[#d5a21f]/25" />
                        <div className="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[#d5a21f]">{scorecard.competition} / {scorecard.division}</p>
                                <h2 className="mt-2 text-3xl font-semibold tracking-tight">{scorecard.entry}</h2>
                                <p className="mt-2 text-sm text-white/60">{scorecard.delegation} · {scorecard.contest}</p>
                            </div>
                            <span className={`w-fit rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] ${stateTone(scorecard.state)}`}>{scorecard.state}</span>
                        </div>
                    </section>

                    {scorecard.rejection_reason ? <div className="mt-4 border-l-4 border-rose-500 bg-rose-50 p-4 text-sm text-rose-900"><strong>Correction required:</strong> {scorecard.rejection_reason}</div> : null}
                    {flash?.status ? <div aria-live="polite" className="mt-4 border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm text-emerald-900">{flash.status}</div> : null}
                    {form.errors.scorecard ? <div role="alert" className="mt-4 border-l-4 border-rose-500 bg-rose-50 p-4 text-sm text-rose-900">{form.errors.scorecard}</div> : null}

                    <div className="mt-6 divide-y divide-slate-200 border-y border-slate-200 bg-white sm:overflow-hidden sm:rounded-2xl sm:border">
                        {scorecard.criteria.map((criterion, index) => (
                            <fieldset key={criterion.id} disabled={locked || form.processing} className="grid gap-4 p-5 sm:grid-cols-[1fr_9rem_9rem] sm:items-start sm:p-6">
                                <legend className="sr-only">{criterion.name}</legend>
                                <div>
                                    <p className="font-semibold text-slate-900">{criterion.name}</p>
                                    <p className="mt-1 text-xs uppercase tracking-[0.14em] text-slate-400">{criterion.source_label}</p>
                                    <p className="mt-3 text-sm text-slate-500">{criterion.weight ? `${Number(criterion.weight)}% weight` : 'Point value'}{criterion.maximum ? ` · ${Number(criterion.minimum ?? 0)}-${Number(criterion.maximum)}` : ''}</p>
                                    <label className="mt-4 block text-sm text-slate-600">Notes<input value={form.data.values[index].notes} onChange={(event) => updateValue(index, 'notes', event.target.value)} className="mt-2 w-full rounded-xl border-slate-300 text-sm focus:border-sky-700 focus:ring-sky-700" /></label>
                                </div>
                                <label className="text-sm font-medium text-slate-700">Score<input required={criterion.required} type="number" step={criterion.input_scale ? `0.${'0'.repeat(Math.max(0, criterion.input_scale - 1))}1` : '1'} min={criterion.minimum ?? undefined} max={criterion.maximum ?? undefined} value={form.data.values[index].raw_value} onChange={(event) => updateValue(index, 'raw_value', event.target.value)} className="mt-2 min-h-14 w-full rounded-xl border-slate-300 text-2xl font-semibold tabular-nums focus:border-sky-700 focus:ring-sky-700" /></label>
                                <label className="text-sm font-medium text-slate-700">Deduction<input type="number" min="0" step={criterion.input_scale ? `0.${'0'.repeat(Math.max(0, criterion.input_scale - 1))}1` : '1'} value={form.data.values[index].deduction} onChange={(event) => updateValue(index, 'deduction', event.target.value)} className="mt-2 min-h-14 w-full rounded-xl border-slate-300 text-2xl font-semibold tabular-nums focus:border-sky-700 focus:ring-sky-700" /></label>
                            </fieldset>
                        ))}
                    </div>

                    <div className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white/95 p-4 shadow-[0_-12px_30px_rgba(15,23,42,0.08)] backdrop-blur">
                        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4">
                            <div><p className="text-xs uppercase tracking-[0.14em] text-slate-400">Server total · revision {scorecard.revision}</p><p className="text-2xl font-semibold tabular-nums text-slate-900">{scorecard.calculated_total}</p></div>
                            <div className="flex gap-2">
                                <button type="submit" disabled={locked || form.processing} className="min-h-12 rounded-full border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2 disabled:opacity-40">Save draft</button>
                                <button type="button" onClick={submit} disabled={locked || form.processing} className="min-h-12 rounded-full bg-[#d5a21f] px-5 text-sm font-semibold text-[#17212b] hover:bg-[#bc8d16] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#9a7210] focus-visible:ring-offset-2 disabled:opacity-40">Save & submit</button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </AuthenticatedLayout>
    );
}
