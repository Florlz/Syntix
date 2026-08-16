import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SportContextNav from '@/Components/SportContextNav';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const dateTime = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : 'Time unavailable';

const buttonPrimary = 'inline-flex min-h-11 items-center justify-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-primary-foreground transition hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const buttonDanger = 'inline-flex min-h-11 items-center justify-center rounded-xl border border-danger bg-danger-surface px-4 py-2.5 text-sm font-bold text-danger transition hover:border-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const noteInput = 'min-h-11 min-w-0 w-full rounded-xl border-border bg-surface text-sm text-foreground focus:border-primary focus:ring-primary';

function StatusPill({ children, tone = 'amber' }) {
    const tones = {
        amber: 'border-amber-200 bg-amber-50 text-amber-800',
        green: 'border-emerald-200 bg-emerald-50 text-emerald-800',
        navy: 'border-primary/20 bg-primary/5 text-primary',
    };

    return <span className={`inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.1em] ${tones[tone]}`}>{children}</span>;
}

function ScopeHeader({ event, scope }) {
    const isScoped = Boolean(scope?.competition_id || scope?.division_id);

    return (
        <section className="overflow-hidden rounded-3xl bg-sidebar p-6 text-white shadow-sm sm:p-8">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.14em] text-accent">
                        <span>{event.name}</span>
                        {isScoped ? <><span className="text-white/35">/</span><span>{scope.competition}</span>{scope.division ? <><span className="text-white/35">/</span><span>{scope.division}</span></> : null}</> : null}
                    </div>
                    <h2 className="mt-3 font-serif text-3xl font-bold sm:text-4xl">Results</h2>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-white/65">Confirm match outcomes first. When a division is complete, confirm its final standings and award the event points.</p>
                </div>
                {!isScoped ? <Link href={route('dashboard', { event: event.id })} className="inline-flex min-h-11 items-center justify-center rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-white/15">Back to overview</Link> : null}
            </div>
        </section>
    );
}

function canonicalSportHref(event, scope) {
    if (!scope?.competition_id) return null;

    const href = route('admin.sports.show', [event.id, scope.competition_id]);
    return scope.division_id ? `${href}?division=${encodeURIComponent(scope.division_id)}` : href;
}

function OutcomeReview({ submission }) {
    const approve = useForm({ reason: '' });
    const reject = useForm({ reason: '' });
    const homeScore = submission.home?.score;
    const awayScore = submission.away?.score;
    const hasScore = homeScore !== null && homeScore !== undefined && awayScore !== null && awayScore !== undefined;

    return (
        <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-primary">
                        <span>{submission.competition}</span><span className="text-slate-300">/</span><span>{submission.division}</span>
                    </div>
                    <h3 className="mt-2 font-serif text-xl font-bold text-foreground">{submission.contest || 'Match result'}</h3>
                    <p className="mt-2 text-sm text-muted">Revision {submission.revision} · submitted by {submission.submitted_by || 'Unknown'} · {dateTime(submission.submitted_at)}</p>
                </div>
                <StatusPill>Needs review</StatusPill>
            </div>

            <div className="mt-5 grid gap-3 rounded-2xl bg-surface-muted p-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                <div><p className="text-xs font-bold uppercase tracking-[0.1em] text-muted">{submission.home?.name || 'Home side'}</p><p className="mt-1 font-mono text-3xl font-bold text-foreground">{homeScore ?? '—'}</p></div>
                <span className="text-center text-xs font-bold uppercase tracking-[0.14em] text-muted">{hasScore ? 'Final' : 'Result'}</span>
                <div className="text-left sm:text-right"><p className="text-xs font-bold uppercase tracking-[0.1em] text-muted">{submission.away?.name || 'Away side'}</p><p className="mt-1 font-mono text-3xl font-bold text-foreground">{awayScore ?? '—'}</p></div>
            </div>
            <p className="mt-3 text-sm text-muted">Winner: <span className="font-bold text-foreground">{submission.winner || (submission.result === 'draw' ? 'Draw' : 'Not recorded')}</span></p>

            <details className="mt-5 rounded-xl border border-border bg-surface">
                <summary className="cursor-pointer px-4 py-3 text-sm font-bold text-foreground">Technical details</summary>
                <pre className="max-h-56 overflow-auto border-t border-border bg-surface-muted p-4 text-xs leading-5 text-muted">{JSON.stringify(submission.technical_payload || {}, null, 2)}</pre>
            </details>

            <div className="mt-5 grid gap-4 border-t border-slate-100 pt-5 lg:grid-cols-2">
                <form onSubmit={(event) => { event.preventDefault(); approve.post(route('admin.results.approve', submission.id), { preserveScroll: true }); }} className="space-y-2">
                    <label className="block text-xs font-bold uppercase tracking-[0.1em] text-slate-500" htmlFor={`approve-${submission.id}`}>Optional note</label>
                    <div className="flex flex-col gap-2 sm:flex-row"><input id={`approve-${submission.id}`} value={approve.data.reason} onChange={(event) => approve.setData('reason', event.target.value)} className={noteInput} placeholder="What did you verify?"/><button disabled={approve.processing || reject.processing} className={buttonPrimary}>Confirm result</button></div>
                </form>
                <form onSubmit={(event) => { event.preventDefault(); reject.post(route('admin.results.reject', submission.id), { preserveScroll: true }); }} className="space-y-2">
                    <label className="block text-xs font-bold uppercase tracking-[0.1em] text-slate-500" htmlFor={`reject-${submission.id}`}>Required correction reason</label>
                    <div className="flex flex-col gap-2 sm:flex-row"><input id={`reject-${submission.id}`} required value={reject.data.reason} onChange={(event) => reject.setData('reason', event.target.value)} className={noteInput} placeholder="Tell the tabulator what to fix"/><button disabled={approve.processing || reject.processing} className={buttonDanger}>Return for correction</button></div>
                </form>
            </div>
        </article>
    );
}

function PlacementReview({ placement }) {
    const form = useForm({ reason: '' });

    return (
        <article className="rounded-2xl border border-border bg-surface p-5 shadow-sm sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-accent-foreground"><span>{placement.competition}</span><span className="text-muted">/</span><span>{placement.division}</span></div><h3 className="mt-2 font-serif text-xl font-bold text-foreground">Final standings</h3><p className="mt-2 text-sm text-muted">Revision {placement.revision} · submitted by {placement.submitted_by || 'Unknown'} · {dateTime(placement.submitted_at)}</p></div>
                <StatusPill>Needs review</StatusPill>
            </div>
            <div className="mt-5 overflow-x-auto rounded-xl border border-slate-200"><table className="w-full min-w-[34rem] text-left text-sm"><thead className="bg-slate-50 text-xs uppercase tracking-[0.1em] text-slate-500"><tr><th className="px-4 py-3">Rank</th><th className="px-4 py-3">Entry</th><th className="px-4 py-3">Delegation</th><th className="px-4 py-3 text-right">Points</th></tr></thead><tbody className="divide-y divide-slate-100">{placement.items.map((item) => <tr key={item.id}><td className="px-4 py-3 font-mono font-bold">{item.rank}</td><td className="px-4 py-3 font-semibold">{item.entry || 'Entry'}</td><td className="px-4 py-3 text-slate-600">{item.delegation || 'Delegation'}</td><td className="px-4 py-3 text-right font-mono font-bold">{item.points}</td></tr>)}</tbody></table></div>
            <form onSubmit={(event) => { event.preventDefault(); if (window.confirm('Confirm these final standings and award the listed event points?')) form.post(route('admin.placements.approve', placement.id), { preserveScroll: true }); }} className="mt-5 flex flex-col gap-2 sm:flex-row"><input aria-label="Standings confirmation note" placeholder="Optional confirmation note" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={noteInput}/><button disabled={form.processing} className={buttonPrimary}>Confirm standings &amp; award points</button></form>
        </article>
    );
}

export default function Index({ event, result_submissions: submissions = [], division_placements: placements = [], scope = {} }) {
    const { flash, errors } = usePage().props;
    const [queue, setQueue] = useState('needs');
    const needsCount = submissions.length + placements.length;
    const scopeLabel = useMemo(() => [scope.competition, scope.division].filter(Boolean).join(' / '), [scope.competition, scope.division]);
    const isScoped = Boolean(scope.competition_id || scope.division_id);
    const officialSportHref = canonicalSportHref(event, scope);

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{scopeLabel || event.name}</p><h1 className="font-serif text-2xl font-bold">Results</h1></div>}>
            <Head title={`${event.name} Results`} />
            <main className="min-h-[calc(100vh-4rem)] bg-background p-4 sm:p-7 lg:p-8"><div className="mx-auto max-w-6xl space-y-6">
                <ScopeHeader event={event} scope={scope}/>
                {isScoped ? <SportContextNav event={event} competitionId={scope.competition_id} competitionName={scope.competition} divisionId={scope.division_id} divisionName={scope.division} currentTask="results" /> : null}
                {flash?.status ? <div role="status" className="rounded-xl border border-primary bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{flash.status}</div> : null}
                {errors?.approval ? <div role="alert" className="rounded-xl border border-danger bg-danger-surface px-4 py-3 text-sm font-semibold text-danger">{errors.approval}</div> : null}

                <div className="flex flex-wrap gap-2 rounded-2xl border border-border bg-surface p-2 shadow-sm" role="tablist" aria-label="Results queues">
                    <button type="button" role="tab" aria-selected={queue === 'needs'} onClick={() => setQueue('needs')} className={`rounded-xl px-4 py-2.5 text-sm font-bold ${queue === 'needs' ? 'bg-primary text-primary-foreground' : 'text-muted hover:bg-surface-muted'}`}>Needs review <span className="ml-1 opacity-70">{needsCount}</span></button>
                    <button type="button" role="tab" aria-selected={queue === 'official'} onClick={() => setQueue('official')} className={`rounded-xl px-4 py-2.5 text-sm font-bold ${queue === 'official' ? 'bg-primary text-primary-foreground' : 'text-muted hover:bg-surface-muted'}`}>Official results</button>
                </div>

                {queue === 'needs' ? <>
                    <section className="grid gap-3 sm:grid-cols-3"><div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Needs review</p><p className="mt-2 font-serif text-3xl font-bold text-[#17212b]">{needsCount}</p><p className="mt-1 text-sm text-slate-500">Items blocking official results.</p></div><div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Match results</p><p className="mt-2 font-serif text-3xl font-bold text-[#17212b]">{submissions.length}</p><p className="mt-1 text-sm text-slate-500">Confirm score and winner.</p></div><div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p className="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Final standings</p><p className="mt-2 font-serif text-3xl font-bold text-[#17212b]">{placements.length}</p><p className="mt-1 text-sm text-slate-500">Confirm rank and points.</p></div></section>
                    {needsCount === 0 ? <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">Nothing is waiting for review. Approved and returned records remain in their sport’s history.</div> : null}
                    {submissions.length ? <section><div className="mb-3"><h2 className="font-serif text-2xl font-bold text-[#17212b]">Match results</h2><p className="mt-1 text-sm text-slate-500">Confirm the score and winner, or return the submission with a correction reason.</p></div><div className="space-y-4">{submissions.map((submission) => <OutcomeReview key={submission.id} submission={submission}/>)}</div></section> : null}
                    {placements.length ? <section><div className="mb-3"><h2 className="font-serif text-2xl font-bold text-[#17212b]">Final standings</h2><p className="mt-1 text-sm text-slate-500">This step awards the listed event points after the ranking is complete.</p></div><div className="space-y-4">{placements.map((placement) => <PlacementReview key={placement.id} placement={placement}/>)}</div></section> : null}
                </> : <section className="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm"><h2 className="font-serif text-2xl font-bold text-[#17212b]">Official results</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Approved scores, brackets, and standings are kept in the selected sport’s workspace. Open a sport to review its official history without mixing it with pending work.</p>{officialSportHref ? <Link href={officialSportHref} className={`${buttonPrimary} mt-5 w-fit`}>Open sport results</Link> : null}</section>}
            </div></main>
        </AuthenticatedLayout>
    );
}
