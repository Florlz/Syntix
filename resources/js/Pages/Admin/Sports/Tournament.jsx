import Link from '@/Components/PrefetchLink';
import SportContextNav from '@/Components/SportContextNav';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';

const panel = 'border border-[#D6DDDA] bg-white shadow-[0_8px_24px_rgba(17,38,51,0.06)]';
const primary = 'inline-flex min-h-10 items-center justify-center rounded-md bg-[#0B536D] px-4 text-sm font-bold text-white transition hover:bg-[#083e52] disabled:cursor-not-allowed disabled:opacity-45';
const quiet = 'inline-flex min-h-10 items-center justify-center rounded-md border border-[#C6D0CC] bg-white px-4 text-sm font-bold text-[#17333F] transition hover:border-[#0B536D] disabled:cursor-not-allowed disabled:opacity-45';

export function SportRail({ event, sport, sports, division, discipline }) {
    const scopedSports = sports.filter((item) => String(item.id) === String(sport.id));

    return <aside className={`${panel} h-fit lg:sticky lg:top-24`} aria-label="Draw scopes">
        <div className="border-b border-[#E2E7E4] px-4 py-4"><p className="font-condensed text-xs font-bold uppercase tracking-[0.18em] text-[#6C7B82]">{sport.name} divisions</p><p className="mt-1 text-sm font-bold text-[#17333F]">{event.name}</p></div>
        <nav className="max-h-[calc(100vh-12rem)] overflow-y-auto p-2">
            {scopedSports.map((scopedSport) => <div key={scopedSport.id} className="mb-3 last:mb-0">{scopedSport.divisions.map((item) => <div key={item.id} className="mb-1">
                <Link href={route('admin.sports.tournament', [event.id, item.id])} className={`flex items-center justify-between gap-2 rounded px-3 py-2 text-sm font-bold ${division.id === item.id && !discipline ? 'bg-[#EAF0EE] text-[#0B536D]' : 'text-[#3C515A] hover:bg-[#F1F5F3]'}`}><span className="truncate">{item.name}</span><span className="font-condensed text-[0.65rem] uppercase text-[#809096]">{item.format?.replaceAll('_', ' ') ?? 'setup'}</span></Link>
                {item.id === division.id && item.disciplines?.length ? <div className="ml-3 border-l-2 border-[#DDE5E1] pl-2">{item.disciplines.map((itemDiscipline) => <Link key={itemDiscipline.id} href={route('admin.sports.discipline-tournament', [event.id, item.id, itemDiscipline.id])} className={`block rounded px-3 py-1.5 text-sm ${discipline?.id === itemDiscipline.id ? 'bg-[#FFF5D9] font-bold text-[#6D5300]' : 'text-[#66767C] hover:bg-[#F8FAF9]'}`}>{itemDiscipline.name}</Link>)}</div> : null}
            </div>)}</div>)}
        </nav>
    </aside>;
}

function MatchCard({ node, nodeRef }) {
    const contestState = node.contest?.state ?? node.state;
    return <article ref={nodeRef} data-node-id={node.id} className="relative z-10 w-[13.5rem] border border-[#AEBBB7] bg-white text-[#172B34] shadow-[0_2px_0_rgba(17,38,51,0.08)]">
        <div className="flex items-center justify-between border-b border-[#E3E8E5] bg-[#F7F9F8] px-3 py-2"><span className="font-condensed text-[0.68rem] font-bold uppercase tracking-[0.12em] text-[#6A7A80]">{node.key}</span><span className={`font-condensed text-[0.65rem] font-bold uppercase ${contestState === 'completed' ? 'text-[#16845B]' : contestState === 'cancelled' ? 'text-[#A44242]' : 'text-[#6A7A80]'}`}>{contestState}</span></div>
        <div className="divide-y divide-[#E8ECEA]">{node.slots.map((slot) => <div key={slot.number} className={`flex min-h-10 items-center justify-between gap-2 px-3 text-sm ${slot.source_result === 'winner' ? 'font-bold' : ''}`}><span className="min-w-0 truncate">{slot.label}</span><span className="font-condensed text-xs text-[#9AA6A9]">{slot.source_result === 'winner' ? 'W' : slot.source_result === 'loser' ? 'L' : ''}</span></div>)}</div>
        {node.contest ? <div className="border-t border-[#E3E8E5] px-3 py-1.5 font-condensed text-[0.65rem] uppercase tracking-[0.1em] text-[#809096]">{node.contest.name}</div> : null}
    </article>;
}

function BracketCanvas({ nodes = [], side = 'winners' }) {
    const boardRef = useRef(null);
    const refs = useRef({});
    const [paths, setPaths] = useState([]);
    const visible = nodes.filter((node) => node.side === side || (side === 'winners' && !['losers', 'championship'].includes(node.side)));
    const rounds = useMemo(() => [...new Set(visible.map((node) => node.round))].sort((a, b) => a - b), [visible]);

    useLayoutEffect(() => {
        const measure = () => {
            const root = boardRef.current?.getBoundingClientRect();
            if (!root) return;
            const next = [];
            visible.forEach((target) => target.slots.filter((slot) => slot.source_node_id && refs.current[slot.source_node_id]).forEach((slot) => {
                const source = refs.current[slot.source_node_id].getBoundingClientRect();
                const targetRect = refs.current[target.id]?.getBoundingClientRect();
                if (!targetRect) return;
                const x1 = source.right - root.left;
                const y1 = source.top + source.height / 2 - root.top;
                const x2 = targetRect.left - root.left;
                const y2 = targetRect.top + targetRect.height / 2 - root.top;
                const bend = Math.max(20, (x2 - x1) / 2);
                next.push({ key: `${slot.source_node_id}-${target.id}-${slot.number}`, d: `M ${x1} ${y1} C ${x1 + bend} ${y1}, ${x2 - bend} ${y2}, ${x2} ${y2}`, tone: target.side === 'losers' ? 'teal' : 'gold' });
            }));
            setPaths(next);
        };
        measure();
        const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(measure);
        if (observer) { observer.observe(boardRef.current); Object.values(refs.current).forEach((element) => element && observer.observe(element)); }
        window.addEventListener('resize', measure);
        return () => { observer?.disconnect(); window.removeEventListener('resize', measure); };
    }, [visible]);

    if (!visible.length) return <div className={`${panel} p-8 text-center text-sm text-[#68767E]`}>No matches are in this bracket lane yet.</div>;
    return <div ref={boardRef} className={`${panel} relative overflow-x-auto bg-white`}><svg aria-hidden="true" className="pointer-events-none absolute inset-0 z-0 hidden h-full w-full overflow-visible sm:block">{paths.map((path) => <path key={path.key} d={path.d} fill="none" stroke={path.tone === 'teal' ? '#0F7C7A' : '#D5A21F'} strokeWidth="2.5" />)}</svg><div className="relative z-10 grid min-w-max grid-flow-col auto-cols-[13.5rem] gap-10 p-8">{rounds.map((round) => <section key={round} className="space-y-5"><p className="font-condensed text-xs font-bold uppercase tracking-[0.16em] text-[#76868A]">Round {round}</p><div className="space-y-7">{visible.filter((node) => node.round === round).map((node) => <MatchCard key={node.id} node={node} nodeRef={(element) => { refs.current[node.id] = element; }} />)}</div></section>)}</div></div>;
}

function RoundRobinBoard({ nodes = [] }) {
    const matches = nodes.filter((node) => node.contest).flatMap((node) => [{ node, slot: node.slots[0], opponent: node.slots[1] }]);
    const teams = [...new Set(matches.flatMap((match) => [match.slot?.label, match.opponent?.label]).filter(Boolean))];
    return <div className="space-y-5"><div className={`${panel} overflow-x-auto`}><table className="min-w-[42rem] w-full text-left text-sm"><thead className="bg-[#F3F6F4] font-condensed text-xs uppercase tracking-[0.14em] text-[#63757B]"><tr><th className="px-4 py-3">Team</th>{teams.map((team) => <th key={team} className="px-4 py-3">{team}</th>)}<th className="px-4 py-3">Pts</th></tr></thead><tbody className="divide-y divide-[#E3E8E5]">{teams.map((team) => <tr key={team}><th className="px-4 py-3 font-bold">{team}</th>{teams.map((opponent) => <td key={opponent} className="px-4 py-3 text-[#6C7B82]">{team === opponent ? '—' : '·'}</td>)}<td className="px-4 py-3 font-condensed font-bold">0</td></tr>)}</tbody></table></div><div className={`${panel} divide-y divide-[#E3E8E5]`}>{matches.map(({ node, slot, opponent }) => <div key={node.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"><span className="font-condensed text-xs font-bold uppercase tracking-[0.12em] text-[#76868A]">{node.key}</span><span className="font-bold">{slot?.label ?? 'TBD'} <span className="px-2 text-[#B3BFBC]">vs</span> {opponent?.label ?? 'TBD'}</span><span className="text-xs text-[#76868A]">{node.contest.state}</span></div>)}</div></div>;
}

function DisciplineEntryEditor({ event, discipline, entry }) {
    const initial = entry.discipline_entry?.members ?? [];
    const [selected, setSelected] = useState(initial.map((member) => member.participant_id));
    const [starter, setStarter] = useState(initial.find((member) => member.is_starter)?.participant_id ?? '');
    const form = useForm({ members: [] });
    const save = (state = 'draft') => form.transform(() => ({ members: selected.map((id) => ({ participant_id: id, is_starter: String(id) === String(starter) })), state })).patch(route('admin.discipline-entries.update', [event.id, discipline.id, entry.id]), { preserveScroll: true });
    return <div className="border border-[#DCE4E0] bg-white p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="font-bold">{entry.delegation?.abbreviation ?? entry.delegation?.name ?? entry.name}</p><p className="text-xs text-[#748388]">Parent Entry: {entry.status} · Discipline Entry: {entry.discipline_entry?.state ?? 'not assigned'}</p></div><div className="flex gap-2"><button type="button" onClick={() => save('draft')} disabled={form.processing} className={quiet}>Save draft</button><button type="button" onClick={() => save('locked')} disabled={form.processing || !selected.length} className={primary}>Lock assignment</button></div></div><div className="mt-3 grid gap-2 sm:grid-cols-2">{entry.participants.map((participant) => <label key={participant.id} className={`flex items-center gap-2 border px-3 py-2 text-sm ${selected.includes(participant.id) ? 'border-[#0B536D] bg-[#F2F8F6]' : 'border-[#E0E7E3]'}`}><input type="checkbox" checked={selected.includes(participant.id)} disabled={!participant.active || form.processing} onChange={() => setSelected((current) => current.includes(participant.id) ? current.filter((id) => id !== participant.id) : [...current, participant.id])} /><span className="min-w-0 flex-1 truncate">{participant.name}</span><input aria-label={`Starter for ${participant.name}`} type="radio" name={`starter-${entry.id}`} checked={String(starter) === String(participant.id)} disabled={!selected.includes(participant.id)} onChange={() => setStarter(participant.id)} /><span className="font-condensed text-[0.62rem] uppercase tracking-[0.1em] text-[#748388]">starter</span></label>)}</div></div>;
}

export default function Tournament({ event, sport, sports = [], division, discipline, proposal, entries = [], draw, tournament, bracket, blockers = [], can_generate: canGenerate, can_redraw: canRedraw, can_publish: canPublish, is_archived: isArchived }) {
    const { errors = {}, flash = {} } = usePage().props;
    const [lane, setLane] = useState('winners');
    const generateForm = useForm({ command_uuid: crypto.randomUUID(), redraw: false });
    const publishForm = useForm({});
    const format = division.format;
    const isRoundRobin = format === 'round_robin';
    const generate = () => {
        const url = discipline ? route('admin.discipline-draws.random', discipline.id) : route('admin.draws.random', division.id);
        generateForm.transform((data) => ({ ...data, redraw: false })).post(url, { preserveScroll: true });
    };
    const redraw = () => {
        if (!window.confirm('Replace the unpublished draw? The current preview will be archived and cannot be restored.')) return;
        generateForm.transform((data) => ({ ...data, redraw: true, command_uuid: crypto.randomUUID() })).post(discipline ? route('admin.discipline-draws.random', discipline.id) : route('admin.draws.random', division.id), { preserveScroll: true });
    };
    const publish = () => publishForm.post(route('admin.brackets.publish', bracket.id), { preserveScroll: true });
    const visibleNodes = bracket?.nodes ?? [];
    const heading = discipline ? `${division.name} · ${discipline.name}` : division.name;

    return <AuthenticatedLayout header={<div><p className="font-condensed text-xs font-bold uppercase tracking-[0.16em] text-[#0B536D]">{sport.name}</p><h1 className="font-condensed text-3xl font-bold uppercase tracking-[0.02em]">Draws &amp; Brackets</h1></div>}>
        <Head title={`${heading} · Draws & Brackets`} />
        <main className="bg-[#EEF1EF] p-4 sm:p-6 lg:p-8"><div className="mx-auto max-w-[120rem] space-y-6">
            {flash?.status ? <div role="status" className="border-l-4 border-[#16845B] bg-white p-4 text-sm">{flash.status}</div> : null}
            {Object.keys(errors).length ? <div role="alert" className="border-l-4 border-[#A44242] bg-[#FFF2F1] p-4 text-sm text-[#7E2F2F]">{Object.values(errors).flat().join(' ')}</div> : null}
            <SportContextNav event={event} competition={sport} division={division} currentTask="bracket" />
            <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]"><SportRail event={event} sport={sport} sports={sports} division={division} discipline={discipline} /><section className="min-w-0 space-y-6">
                <header className="flex flex-col gap-4 border-b border-[#D6DDDA] pb-5 md:flex-row md:items-end md:justify-between"><div><p className="font-condensed text-xs font-bold uppercase tracking-[0.18em] text-[#B07E00]">{discipline ? 'Discipline draw' : 'Division draw'}</p><h2 className="mt-1 font-condensed text-4xl font-bold uppercase leading-none text-[#172B34]">{heading}</h2><p className="mt-2 max-w-2xl text-sm text-[#63757B]">{proposal.source} · {format?.replaceAll('_', ' ') ?? 'Rule setup required'}</p></div></header>
                <div className="grid gap-3 sm:grid-cols-3"><div className={`${panel} p-4`}><p className="font-condensed text-xs font-bold uppercase tracking-[0.14em] text-[#75868B]">Scope</p><p className="mt-2 font-condensed text-2xl font-bold">{discipline ? 'Discipline' : 'Division'}</p><p className="mt-1 text-xs text-[#75868B]">{discipline?.family ?? division.participant_mode ?? 'not configured'}</p></div><div className={`${panel} p-4`}><p className="font-condensed text-xs font-bold uppercase tracking-[0.14em] text-[#75868B]">Locked Entries</p><p className="mt-2 font-condensed text-2xl font-bold">{tournament?.eligible_entry_count ?? entries.filter((entry) => entry.status === 'locked' && (!discipline || entry.discipline_entry?.state === 'locked')).length}</p><p className="mt-1 text-xs text-[#75868B]">{discipline ? 'assigned to this discipline' : 'eligible for draw'}</p></div><div className={`${panel} p-4`}><p className="font-condensed text-xs font-bold uppercase tracking-[0.14em] text-[#75868B]">Workspace state</p><p className="mt-2 font-condensed text-2xl font-bold uppercase">{tournament?.state ?? 'setup'}</p><p className="mt-1 text-xs text-[#75868B]">{bracket ? `Bracket v${bracket.version}` : 'No topology generated'}</p></div></div>
                {blockers.length ? <div className="border border-[#E4D39A] bg-[#FFF8E5] p-5"><p className="font-condensed text-xs font-bold uppercase tracking-[0.16em] text-[#8B6900]">Action required</p><ul className="mt-2 space-y-1 text-sm text-[#5F4B00]">{blockers.map((blocker) => <li key={blocker}>· {blocker}</li>)}</ul></div> : null}
                {discipline && entries.length ? <section className={`${panel} p-5`}><div className="flex flex-wrap items-end justify-between gap-3"><div><p className="font-condensed text-xs font-bold uppercase tracking-[0.16em] text-[#0B536D]">Entries</p><h3 className="font-condensed text-2xl font-bold uppercase">Assign participants</h3><p className="mt-1 text-sm text-[#63757B]">Each department gets one starter in a combat weight class. Save drafts first, then lock only after the parent roster is approved.</p></div></div><div className="mt-5 space-y-3">{entries.map((entry) => <DisciplineEntryEditor key={entry.id} event={event} discipline={discipline} entry={entry} />)}</div></section> : null}
                <section className={`${panel} p-5`}><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="font-condensed text-xs font-bold uppercase tracking-[0.16em] text-[#0B536D]">{bracket ? 'Draw workspace' : 'Draw setup'}</p><h3 className="font-condensed text-2xl font-bold uppercase">{bracket ? `${format?.replaceAll('_', ' ')} · ${tournament.state}` : 'Generate the official topology'}</h3></div><div className="flex flex-wrap gap-2">{canGenerate ? <button type="button" onClick={generate} disabled={generateForm.processing || isArchived} className={primary}>Generate random draw</button> : null}{canRedraw ? <button type="button" onClick={redraw} disabled={generateForm.processing || isArchived} className={quiet}>Redraw preview</button> : null}{canPublish ? <button type="button" onClick={publish} disabled={publishForm.processing || isArchived} className={primary}>Publish bracket</button> : null}</div></div>
                    {!bracket ? <div className="mt-6 border border-dashed border-[#B9C6C1] bg-[#F8FAF9] p-8 text-center"><p className="font-condensed text-xl font-bold uppercase text-[#3B525A]">{isArchived ? 'Archived event' : blockers.length ? 'Complete the setup blockers' : !proposal.supported_bracket ? 'This format is performance-ranked' : 'Ready to draw'}</p><p className="mx-auto mt-2 max-w-xl text-sm text-[#708087]">{!proposal.supported_bracket ? 'Athletics disciplines use measured results and standings; no elimination bracket is fabricated for them.' : blockers.length ? 'The workspace will enable drawing as soon as the rule, roster, and discipline assignments are ready.' : 'The generated preview remains editable until it is published.'}</p></div> : isRoundRobin ? <div className="mt-6"><RoundRobinBoard nodes={visibleNodes} /></div> : <div className="mt-6 space-y-4"><div className="flex flex-wrap gap-2" role="tablist" aria-label="Bracket lanes">{[['winners', 'Winners'], ['losers', 'Losers'], ['championship', 'Championship']].filter(([value]) => value === 'winners' || visibleNodes.some((node) => node.side === value)).map(([value, label]) => <button key={value} type="button" role="tab" aria-selected={lane === value} onClick={() => setLane(value)} className={`min-h-9 rounded px-3 font-condensed text-xs font-bold uppercase tracking-[0.12em] ${lane === value ? 'bg-[#17333F] text-white' : 'border border-[#C6D0CC] bg-white text-[#61737A]'}`}>{label}</button>)}</div><BracketCanvas nodes={visibleNodes} side={lane} /></div>}
                </section>
                {bracket ? <p className="text-xs text-[#718086]">Topology is frozen after publication. Contest schedule, venue, and official result metadata remain editable from the scheduling and results workflows.</p> : null}
            </section></div>
        </div></main>
    </AuthenticatedLayout>;
}
