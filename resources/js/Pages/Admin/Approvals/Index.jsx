import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

function formatDate(value) {
    return value ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Time unavailable';
}

function OutcomeReview({ submission }) {
    const approve = useForm({ reason: '' });
    const reject = useForm({ reason: '' });

    return (
        <article className="border-b border-slate-200 bg-white p-5 last:border-b-0 sm:p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-sky-700">Contest outcome · revision {submission.revision}</p><h3 className="mt-1 text-lg font-semibold text-slate-900">{submission.competition} / {submission.division} / {submission.contest}</h3><p className="mt-2 text-sm text-slate-500">Submitted by {submission.submitted_by ?? 'Unknown'} · {formatDate(submission.submitted_at)}</p></div>
                <span className="w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-amber-800">Awaiting review</span>
            </div>
            <dl className="mt-5 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-slate-200 sm:grid-cols-4">
                {Object.entries(submission.payload).map(([key, value]) => <div key={key} className="bg-slate-50 p-3"><dt className="text-[10px] uppercase tracking-[0.12em] text-slate-400">{key.replaceAll('_', ' ')}</dt><dd className="mt-1 break-words text-sm font-semibold text-slate-800">{typeof value === 'object' ? JSON.stringify(value) : String(value)}</dd></div>)}
            </dl>
            <div className="mt-5 grid gap-3 lg:grid-cols-2">
                <form onSubmit={(event) => { event.preventDefault(); approve.post(route('admin.results.approve', submission.id), { preserveScroll: true }); }} className="flex gap-2"><input aria-label="Approval reason" name={`approval-reason-${submission.id}`} autoComplete="off" placeholder="Approval note (optional)…" value={approve.data.reason} onChange={(event) => approve.setData('reason', event.target.value)} className="min-w-0 flex-1 rounded-xl border-slate-300 text-sm focus:border-sky-700 focus:ring-sky-700" /><button disabled={approve.processing || reject.processing} className="rounded-full bg-[#0b2e4f] px-5 text-sm font-semibold text-white hover:bg-sky-950 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-700 focus-visible:ring-offset-2 disabled:opacity-40">Approve</button></form>
                <form onSubmit={(event) => { event.preventDefault(); reject.post(route('admin.results.reject', submission.id), { preserveScroll: true }); }} className="flex gap-2"><input required aria-label="Rejection reason" name={`rejection-reason-${submission.id}`} autoComplete="off" placeholder="Required correction reason…" value={reject.data.reason} onChange={(event) => reject.setData('reason', event.target.value)} className="min-w-0 flex-1 rounded-xl border-slate-300 text-sm focus:border-rose-600 focus:ring-rose-600" /><button disabled={approve.processing || reject.processing} className="rounded-full border border-rose-300 px-5 text-sm font-semibold text-rose-800 hover:bg-rose-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2 disabled:opacity-40">Reject</button></form>
            </div>
        </article>
    );
}

function PlacementReview({ placement }) {
    const form = useForm({ reason: '' });

    return (
        <article className="border-b border-slate-200 bg-white p-5 last:border-b-0 sm:p-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#9a7210]">Final Division Placement · revision {placement.revision}</p><h3 className="mt-1 text-lg font-semibold text-slate-900">{placement.competition} / {placement.division}</h3><p className="mt-2 text-sm text-slate-500">Approval commits signed championship-ledger entries.</p></div><span className="w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-amber-800">Ledger pending</span></div>
            <div className="mt-5 overflow-x-auto"><table className="w-full min-w-[34rem] text-left text-sm"><thead className="border-b border-slate-200 text-xs uppercase tracking-[0.12em] text-slate-400"><tr><th className="py-2">Rank</th><th>Entry</th><th>Delegation</th><th className="text-right">Points</th></tr></thead><tbody className="divide-y divide-slate-100">{placement.items.map((item) => <tr key={item.id}><td className="py-3 font-semibold">{item.rank}</td><td>{item.entry}</td><td>{item.delegation}</td><td className="text-right font-semibold tabular-nums">{item.points}</td></tr>)}</tbody></table></div>
            <form onSubmit={(event) => { event.preventDefault(); if (window.confirm('Approve this final Division Placement and commit its signed ledger entries?')) form.post(route('admin.placements.approve', placement.id), { preserveScroll: true }); }} className="mt-5 flex flex-col gap-2 sm:flex-row"><input aria-label="Placement approval reason" name={`placement-reason-${placement.id}`} autoComplete="off" placeholder="Approval note (optional)…" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className="min-w-0 flex-1 rounded-xl border-slate-300 text-sm focus:border-sky-700 focus:ring-sky-700" /><button disabled={form.processing} className="min-h-11 rounded-full bg-[#d5a21f] px-5 text-sm font-semibold text-[#17212b] hover:bg-[#bc8d16] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#9a7210] focus-visible:ring-offset-2 disabled:opacity-40">Approve placement & commit ledger</button></form>
        </article>
    );
}

export default function Index({ event, result_submissions: submissions = [], division_placements: placements = [] }) {
    const { flash, errors } = usePage().props;
    const empty = submissions.length === 0 && placements.length === 0;

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">{event.name}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Approval review desk</h1></div>}>
            <Head title="Approval review desk" />
            <main className="min-h-[calc(100vh-9rem)] bg-[#eef2f4] px-4 py-8 sm:px-6 lg:px-10"><div className="mx-auto max-w-6xl space-y-8">
                {flash?.status ? <div aria-live="polite" className="border-l-4 border-emerald-500 bg-emerald-50 p-4 text-sm text-emerald-900">{flash.status}</div> : null}
                {errors?.approval ? <div role="alert" className="border-l-4 border-rose-500 bg-rose-50 p-4 text-sm text-rose-900">{errors.approval}</div> : null}
                <section className="grid gap-4 sm:grid-cols-2"><div className="bg-[#0b2e4f] p-6 text-white sm:rounded-2xl"><p className="text-xs uppercase tracking-[0.16em] text-white/55">Contest outcomes</p><p className="mt-2 text-4xl font-semibold">{submissions.length}</p></div><div className="bg-[#d5a21f] p-6 text-[#17212b] sm:rounded-2xl"><p className="text-xs uppercase tracking-[0.16em] text-black/55">Division Placements</p><p className="mt-2 text-4xl font-semibold">{placements.length}</p></div></section>
                {empty ? <div className="border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500 sm:rounded-2xl">Nothing is waiting for review. Approved and rejected records remain in the audit history.</div> : null}
                {submissions.length ? <section><h2 className="mb-3 text-lg font-semibold text-slate-900">Outcome review</h2><div className="overflow-hidden border border-slate-200 sm:rounded-2xl">{submissions.map((submission) => <OutcomeReview key={submission.id} submission={submission} />)}</div></section> : null}
                {placements.length ? <section><h2 className="mb-3 text-lg font-semibold text-slate-900">Championship ledger gate</h2><div className="overflow-hidden border border-slate-200 sm:rounded-2xl">{placements.map((placement) => <PlacementReview key={placement.id} placement={placement} />)}</div></section> : null}
            </div></main>
        </AuthenticatedLayout>
    );
}
