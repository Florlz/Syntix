import { Head, Link } from '@inertiajs/react';

function Node({ node }) {
    const official = node.official?.state === 'approved';

    return (
        <article className="w-full border-l-4 border-slate-300 bg-white p-4 shadow-sm sm:min-w-64 sm:max-w-72">
            <div className="flex items-center justify-between gap-3"><p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{node.key} · {node.type.replaceAll('_', ' ')}</p><span className={`rounded-full px-2 py-1 text-[9px] font-bold uppercase tracking-[0.12em] ${official ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}`}>{official ? 'Official' : 'Pending'}</span></div>
            <p className="mt-2 text-sm font-semibold text-slate-800">{node.contest ?? (node.type === 'bye' ? 'BYE advancement' : 'Bracket position')}</p>
            <div className="mt-3 divide-y divide-slate-100 border-y border-slate-100">{node.slots.map((slot) => <div key={slot.number} className="flex items-center gap-3 py-2 text-sm"><span className="grid size-6 shrink-0 place-items-center rounded-full bg-[#0b2e4f] text-[10px] font-bold text-white">{slot.number}</span><span className="font-medium text-slate-700">{slot.label}</span></div>)}</div>
            {node.official ? <p className="mt-3 text-xs text-slate-500">Approved {node.official.outcome_type.replaceAll('_', ' ')} · revision {node.official.revision}</p> : null}
        </article>
    );
}

export default function Bracket({ event, division, bracket }) {
    const rounds = bracket.nodes.reduce((grouped, node) => ({
        ...grouped,
        [node.round]: [...(grouped[node.round] ?? []), node],
    }), {});

    return (
        <main className="min-h-screen bg-[#e9eef1] text-slate-900">
            <Head title={`${division.competition} ${division.name} bracket`} />
            <header className="relative overflow-hidden bg-[#0b2e4f] text-white"><div className="absolute -right-24 -top-32 size-96 rounded-full border-[34px] border-[#d5a21f]/20" /><div className="relative mx-auto max-w-7xl px-5 py-9 sm:px-8 sm:py-12"><Link href={route('public.scoreboard', event.slug)} className="text-xs font-semibold uppercase tracking-[0.18em] text-[#d5a21f] underline underline-offset-4">{event.name} scoreboard</Link><h1 className="mt-4 text-4xl font-semibold tracking-tight sm:text-6xl">{division.competition}</h1><div className="mt-3 flex flex-wrap items-center gap-3"><span className="text-lg text-white/70">{division.name}</span><span className="rounded-full border border-white/20 px-3 py-1 text-xs uppercase tracking-[0.14em]">Published bracket v{bracket.version}</span></div></div></header>
            <section className="mx-auto max-w-[96rem] px-4 py-8 sm:px-8"><div className="mb-6 border-l-4 border-[#d5a21f] bg-white p-4 text-sm text-slate-600"><strong className="text-slate-900">Published topology.</strong> Pending nodes remain unofficial until an Admin approves the contest outcome. Participant names are not exposed.</div><div className="grid gap-8 sm:flex sm:items-stretch sm:overflow-x-auto sm:pb-6">{Object.entries(rounds).map(([round, nodes]) => <section key={round} className="sm:min-w-72"><h2 className="mb-3 text-xs font-bold uppercase tracking-[0.18em] text-sky-800">Round {round}</h2><div className="flex h-[calc(100%-2rem)] flex-col justify-around gap-4">{nodes.map((node) => <Node key={node.id} node={node} />)}</div></section>)}</div></section>
        </main>
    );
}
