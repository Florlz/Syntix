import React from 'react';
import { AdminEmptyState, AdminMasthead } from '@/Components/Admin/AdminSurface';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SportWorkspaceShell from '@/Components/Sports/SportWorkspaceShell';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { adminStyles } from '@/Support/adminStyles';

const dateTime = (value) => value
    ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
    : 'Time unavailable';

const buttonPrimary = adminStyles.primaryAction;
const buttonDanger = adminStyles.dangerAction;
const noteInput = adminStyles.field;

function StatusPill({ children, tone = 'amber' }) {
    const tones = {
        amber: 'border-accent/40 bg-accent/10 text-accent-foreground',
        green: 'border-primary/30 bg-primary/10 text-primary',
        navy: 'border-primary/20 bg-primary/5 text-primary',
    };

    return <span className={`inline-flex rounded-full border px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.1em] ${tones[tone]}`}>{children}</span>;
}

function ScopeHeader({ event, scope }) {
    const isScoped = Boolean(scope?.competition_id || scope?.division_id);

    if (isScoped) {
        return <header className="flex flex-col gap-2 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">Results</p><h2 className="font-serif text-3xl font-bold text-foreground">Review submitted outcomes</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-muted">Confirm scores first, then approve final standings for this division.</p></div><span className="text-sm font-semibold text-muted">{scope.division || scope.competition}</span></header>;
    }

    return <AdminMasthead eyebrow={event.name} title="Results" description="Confirm match outcomes first. When a division is complete, confirm its final standings and award the event points." actions={<Link href={route('dashboard', { event: event.id })} className={adminStyles.secondaryAction}>Back to overview</Link>} />;
}

function ResultsWorkspace({ event, workspace, scope, children }) {
    if (!workspace?.sport) return children;

    const selectedDivision = workspace.divisions?.find((division) => String(division.id) === String(scope?.division_id)) || null;

    return <SportWorkspaceShell event={event} sport={workspace.sport} division={selectedDivision} divisions={workspace.divisions || []} activeSection="results">{children}</SportWorkspaceShell>;
}

function canonicalSportHref(event, scope) {
    if (!scope?.competition_id) return null;

    const href = route('admin.sports.show', [event.id, scope.competition_id]);
    return scope.division_id ? `${href}?division=${encodeURIComponent(scope.division_id)}` : href;
}

function JudgedEvidence({ payload, selectedScorecards, onSelectionChange }) {
    const rows = payload.ranked_entries || [];
    const panel = rows[0]?.scorecards || [];
    const toggle = (id) => onSelectionChange(selectedScorecards.includes(id)
        ? selectedScorecards.filter((item) => item !== id)
        : [...selectedScorecards, id]);

    return <section aria-labelledby="judged-evidence-heading" className="mt-5 overflow-hidden border border-border">
        <header className="bg-surface-muted p-4"><p className="text-xs font-bold uppercase tracking-[0.12em] text-primary">Judged evidence</p><h4 id="judged-evidence-heading" className="mt-1 font-serif text-xl font-bold">Panel totals and final ranking</h4><p className="mt-1 text-sm text-muted">{payload.aggregation_method} aggregation · {payload.source_reference || 'Source reference unavailable'}</p></header>
        <div className="overflow-x-auto"><table className="w-full min-w-[48rem] text-left text-sm"><thead className="border-y border-border bg-surface text-xs uppercase tracking-[0.1em] text-muted"><tr><th className="px-4 py-3">Rank / entry</th>{panel.map((card) => <th key={card.judge_id} className="px-3 py-3">{card.judge || `Judge ${card.judge_id}`}</th>)}<th className="px-3 py-3">Aggregate</th><th className="px-3 py-3">Adjustments</th><th className="px-4 py-3">Final</th></tr></thead><tbody className="divide-y divide-border">{rows.map((row) => <tr key={row.entry_id}><th className="px-4 py-4"><span className="mr-2 font-mono text-primary">#{row.rank}</span>{row.entry}<span className="block text-xs font-normal text-muted">{row.delegation}</span></th>{row.scorecards.map((card) => <td key={card.scorecard_id} className="px-3 py-4 align-top"><label className="flex items-start gap-2"><input type="checkbox" checked={selectedScorecards.includes(Number(card.scorecard_id))} onChange={() => toggle(Number(card.scorecard_id))} className="mt-0.5 rounded border-border text-primary focus:ring-accent"/><span><span className="block font-mono font-bold tabular-nums">{card.raw_total}</span><span className="text-[0.65rem] text-muted">rev. {card.revision} · return for correction</span></span></label><details className="mt-2"><summary className="cursor-pointer text-xs font-bold text-primary">Criteria</summary><dl className="mt-1 space-y-1 text-xs text-muted">{(card.criteria || []).map((criterion) => <div key={criterion.criterion_id} className="flex justify-between gap-2"><dt>{criterion.criterion}</dt><dd className="font-mono">{criterion.weighted_value}</dd></div>)}</dl></details></td>)}<td className="px-3 py-4 font-mono tabular-nums">{row.aggregate_raw_total}</td><td className="px-3 py-4 font-mono text-danger">−{row.adjustment_total}</td><td className="px-4 py-4 font-mono font-bold tabular-nums">{row.final_total}</td></tr>)}</tbody></table></div>
        {payload.tie_resolution ? <div className="border-t border-border bg-accent/10 p-4 text-sm"><strong>Tie authority:</strong> {payload.tie_resolution.reference} · {payload.tie_resolution.reason}</div> : null}
    </section>;
}

function OutcomeReview({ submission }) {
    const approve = useForm({ reason: '' });
    const reject = useForm({ reason: '', scorecard_ids: [] });
    const isJudged = submission.technical_payload?.scoring_mode === 'judged';
    const homeScore = submission.home?.score;
    const awayScore = submission.away?.score;
    const hasScore = homeScore !== null && homeScore !== undefined && awayScore !== null && awayScore !== undefined;

    return (
        <article className="border border-border bg-surface p-5 sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-primary">
                        <span>{submission.competition}</span><span className="text-border">/</span><span>{submission.division}</span>
                    </div>
                    <h3 className="mt-2 font-serif text-xl font-bold text-foreground">{submission.contest || 'Match result'}</h3>
                    <p className="mt-2 text-sm text-muted">Revision {submission.revision} · submitted by {submission.submitted_by || 'Unknown'} · {dateTime(submission.submitted_at)}</p>
                </div>
                <StatusPill>Needs review</StatusPill>
            </div>

            {!isJudged ? <><div className="mt-5 grid gap-3 rounded-2xl bg-surface-muted p-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
                <div><p className="text-xs font-bold uppercase tracking-[0.1em] text-muted">{submission.home?.name || 'Home side'}</p><p className="mt-1 font-mono text-3xl font-bold text-foreground">{homeScore ?? '—'}</p></div>
                <span className="text-center text-xs font-bold uppercase tracking-[0.14em] text-muted">{hasScore ? 'Final' : 'Result'}</span>
                <div className="text-left sm:text-right"><p className="text-xs font-bold uppercase tracking-[0.1em] text-muted">{submission.away?.name || 'Away side'}</p><p className="mt-1 font-mono text-3xl font-bold text-foreground">{awayScore ?? '—'}</p></div>
            </div>
            <p className="mt-3 text-sm text-muted">Winner: <span className="font-bold text-foreground">{submission.winner || (submission.result === 'draw' ? 'Draw' : 'Not recorded')}</span></p></> : <JudgedEvidence payload={submission.technical_payload} selectedScorecards={reject.data.scorecard_ids} onSelectionChange={(ids) => reject.setData('scorecard_ids', ids)}/>}

            <details className="mt-5 border border-border bg-surface">
                <summary className="cursor-pointer px-4 py-3 text-sm font-bold text-foreground">Technical details</summary>
                <pre className="max-h-56 overflow-auto border-t border-border bg-surface-muted p-4 text-xs leading-5 text-muted">{JSON.stringify(submission.technical_payload || {}, null, 2)}</pre>
            </details>

            <div className="mt-5 grid gap-4 border-t border-border pt-5 lg:grid-cols-2">
                <form onSubmit={(event) => { event.preventDefault(); approve.post(route('admin.results.approve', submission.id), { preserveScroll: true }); }} className="flex flex-col gap-2">
                    <label className="block text-xs font-bold uppercase tracking-[0.1em] text-muted" htmlFor={`approve-${submission.id}`}>Optional note</label>
                    <div className="flex flex-col gap-2 sm:flex-row"><input id={`approve-${submission.id}`} value={approve.data.reason} onChange={(event) => approve.setData('reason', event.target.value)} className={noteInput} placeholder="What did you verify?"/><button disabled={approve.processing || reject.processing} className={buttonPrimary}>Confirm result</button></div>
                </form>
                <form onSubmit={(event) => { event.preventDefault(); reject.post(route('admin.results.reject', submission.id), { preserveScroll: true }); }} className="flex flex-col gap-2">
                    <label className="block text-xs font-bold uppercase tracking-[0.1em] text-muted" htmlFor={`reject-${submission.id}`}>Required correction reason</label>
                    <div className="flex flex-col gap-2 sm:flex-row"><input id={`reject-${submission.id}`} required value={reject.data.reason} onChange={(event) => reject.setData('reason', event.target.value)} className={noteInput} placeholder={isJudged ? 'Explain the correction; select affected Judge cards above if needed' : 'Tell the tabulator what to fix'}/><button disabled={approve.processing || reject.processing} className={buttonDanger}>Return for correction</button></div>
                </form>
            </div>
        </article>
    );
}

function PlacementReview({ placement }) {
    const form = useForm({ reason: '' });

    return (
        <article className="border border-border bg-surface p-5 sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div><div className="flex flex-wrap items-center gap-2 text-xs font-bold uppercase tracking-[0.12em] text-accent-foreground"><span>{placement.competition}</span><span className="text-muted">/</span><span>{placement.division}</span></div><h3 className="mt-2 font-serif text-xl font-bold text-foreground">Final standings</h3><p className="mt-2 text-sm text-muted">Revision {placement.revision} · submitted by {placement.submitted_by || 'Unknown'} · {dateTime(placement.submitted_at)}</p></div>
                <StatusPill>Needs review</StatusPill>
            </div>
            <div className="mt-5 overflow-x-auto rounded-xl border border-border"><table className="w-full min-w-[34rem] text-left text-sm"><thead className="bg-surface-muted text-xs uppercase tracking-[0.1em] text-muted"><tr><th className="px-4 py-3">Rank</th><th className="px-4 py-3">Entry</th><th className="px-4 py-3">Delegation</th><th className="px-4 py-3 text-right">Points</th></tr></thead><tbody className="divide-y divide-border">{placement.items.map((item) => <tr key={item.id}><td className="px-4 py-3 font-mono font-bold">{item.rank}</td><td className="px-4 py-3 font-semibold">{item.entry || 'Entry'}</td><td className="px-4 py-3 text-muted">{item.delegation || 'Delegation'}</td><td className="px-4 py-3 text-right font-mono font-bold">{item.points}</td></tr>)}</tbody></table></div>
            <form onSubmit={(event) => { event.preventDefault(); if (window.confirm('Confirm these final standings and award the listed event points?')) form.post(route('admin.placements.approve', placement.id), { preserveScroll: true }); }} className="mt-5 flex flex-col gap-2 sm:flex-row"><input aria-label="Standings confirmation note" placeholder="Optional confirmation note" value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} className={noteInput}/><button disabled={form.processing} className={buttonPrimary}>Confirm standings &amp; award points</button></form>
        </article>
    );
}

export default function Index({ event, result_submissions: submissions = [], division_placements: placements = [], scope = {}, workspace = null }) {
    const { flash, errors } = usePage().props;
    const [queue, setQueue] = useState('needs');
    const needsCount = submissions.length + placements.length;
    const scopeLabel = useMemo(() => [scope.competition, scope.division].filter(Boolean).join(' / '), [scope.competition, scope.division]);
    const isScoped = Boolean(scope.competition_id || scope.division_id);
    const officialSportHref = canonicalSportHref(event, scope);

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{scopeLabel || event.name}</p><h1 className="font-serif text-2xl font-bold">Results</h1></div>}>
            <Head title={`${event.name} Results`} />
            <main className={adminStyles.page}><ResultsWorkspace event={event} workspace={workspace} scope={scope}><div className="mx-auto flex max-w-6xl flex-col gap-6">
                <ScopeHeader event={event} scope={scope}/>
                {flash?.status ? <div role="status" className="rounded-xl border border-primary bg-primary/10 px-4 py-3 text-sm font-semibold text-primary">{flash.status}</div> : null}
                {errors?.approval ? <div role="alert" className="rounded-xl border border-danger bg-danger-surface px-4 py-3 text-sm font-semibold text-danger">{errors.approval}</div> : null}

                <div className="flex flex-wrap gap-2 border border-border bg-surface p-2" role="tablist" aria-label="Results queues">
                    <button type="button" role="tab" aria-selected={queue === 'needs'} onClick={() => setQueue('needs')} className={`rounded-sm px-4 py-2.5 text-sm font-bold ${queue === 'needs' ? 'bg-primary text-primary-foreground' : 'text-muted hover:bg-surface-muted'}`}>Needs review <span className="ml-1 opacity-70">{needsCount}</span></button>
                    <button type="button" role="tab" aria-selected={queue === 'official'} onClick={() => setQueue('official')} className={`rounded-sm px-4 py-2.5 text-sm font-bold ${queue === 'official' ? 'bg-primary text-primary-foreground' : 'text-muted hover:bg-surface-muted'}`}>Official results</button>
                </div>

                {queue === 'needs' ? <>
                    <section className="grid gap-3 sm:grid-cols-3"><div className="border border-border bg-surface p-5"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Needs review</p><p className="mt-2 font-condensed text-4xl font-bold text-primary">{needsCount}</p><p className="mt-1 text-sm text-muted">Items blocking official results.</p></div><div className="border border-border bg-surface p-5"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Match results</p><p className="mt-2 font-condensed text-4xl font-bold text-primary">{submissions.length}</p><p className="mt-1 text-sm text-muted">Confirm score and winner.</p></div><div className="border border-border bg-surface p-5"><p className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Final standings</p><p className="mt-2 font-condensed text-4xl font-bold text-primary">{placements.length}</p><p className="mt-1 text-sm text-muted">Confirm rank and points.</p></div></section>
                    {needsCount === 0 ? <AdminEmptyState title="Nothing is waiting for review" description="Approved and returned records remain in their sport’s history." /> : null}
                    {submissions.length ? <section><div className="mb-3"><h2 className="font-serif text-2xl font-bold text-[#17212b]">Match results</h2><p className="mt-1 text-sm text-slate-500">Confirm the score and winner, or return the submission with a correction reason.</p></div><div className="space-y-4">{submissions.map((submission) => <OutcomeReview key={submission.id} submission={submission}/>)}</div></section> : null}
                    {placements.length ? <section><div className="mb-3"><h2 className="font-serif text-2xl font-bold text-[#17212b]">Final standings</h2><p className="mt-1 text-sm text-slate-500">This step awards the listed event points after the ranking is complete.</p></div><div className="space-y-4">{placements.map((placement) => <PlacementReview key={placement.id} placement={placement}/>)}</div></section> : null}
                </> : <section className="border border-border bg-surface p-8"><h2 className="text-2xl font-bold text-foreground">Official results</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-muted">Approved scores, brackets, and standings are kept in the selected sport’s workspace. Open a sport to review its official history without mixing it with pending work.</p>{officialSportHref ? <Link href={officialSportHref} className={`${buttonPrimary} mt-5 w-fit`}>Open sport results</Link> : null}</section>}
            </div></ResultsWorkspace></main>
        </AuthenticatedLayout>
    );
}
