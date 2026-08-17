import React, { useLayoutEffect, useMemo, useRef, useState } from 'react';
import AppIcon from '@/Components/AppIcon';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SportWorkspaceShell from '@/Components/Sports/SportWorkspaceShell';
import WorkflowNotice from '@/Components/Sports/WorkflowNotice';
import { sportWorkspaceUrl } from '@/Support/sportWorkspaceRoutes';
import { Head, useForm, usePage } from '@inertiajs/react';

const panel = 'rounded-xl border border-border bg-surface shadow-xs';
const primary = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground transition hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const quiet = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 text-sm font-bold text-primary transition hover:border-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

function formatLabel(value, fallback = 'Format not set') {
    return value ? String(value).replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase()) : fallback;
}
function friendlyBlocker(value) {
    const text = String(value);
    if (/No Entries have been registered/i.test(text)) return 'No teams are currently participating in this division.';
    if (/No department Entries have been assigned/i.test(text)) return 'No teams have been added to this event yet.';
    if (/Lock at least one eligible Entry/i.test(text)) return 'Approve at least one team sheet before making the draw.';
    if (/Lock every assigned discipline Entry/i.test(text)) return "Approve every team's team sheet and lineup for this event before making the draw.";
    if (/rule version is configured/i.test(text)) return 'Competition format and roster rules still need to be configured.';
    if (/archived/i.test(text)) return 'This event is archived and the bracket is read-only.';
    return text.replaceAll('Entries', 'teams').replaceAll('Entry', 'team');
}
function blockerKind(value) {
    const text = String(value);
    if (/archived/i.test(text)) return 'archived';
    if (/rule version|competition format|rules/i.test(text)) return 'configuration';
    if (/discipline|lineup/i.test(text)) return 'discipline';
    if (/currently participating in this division/i.test(text)) return 'roster';
    if (/entry|entries|team sheet|roster/i.test(text)) return 'roster';
    return 'general';
}

function MatchCard({ node, nodeRef }) {
    const contestState = node.contest?.state ?? node.state;
    const stateClass = contestState === 'completed' ? 'text-primary' : contestState === 'cancelled' ? 'text-danger' : 'text-muted';

    return <article ref={nodeRef} data-node-id={node.id} className="relative z-10 w-[13.5rem] border border-border bg-surface text-foreground shadow-xs">
        <div className="flex items-center justify-between border-b border-border bg-surface-muted px-3 py-2"><span className="font-condensed text-[0.68rem] font-bold uppercase tracking-[0.12em] text-muted">{node.key}</span><span className={`font-condensed text-[0.65rem] font-bold uppercase ${stateClass}`}>{contestState}</span></div>
        <div className="divide-y divide-border">{node.slots.map((slot) => <div key={slot.number} className={`flex min-h-10 items-center justify-between gap-2 px-3 text-sm ${slot.source_result === 'winner' ? 'font-bold' : ''}`}><span className="min-w-0 truncate">{slot.label}</span><span className="font-condensed text-xs text-muted">{slot.source_result === 'winner' ? 'W' : slot.source_result === 'loser' ? 'L' : ''}</span></div>)}</div>
        {node.contest ? <div className="border-t border-border px-3 py-1.5 font-condensed text-[0.65rem] uppercase tracking-[0.1em] text-muted">{node.contest.name}</div> : null}
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
                next.push({ key: `${slot.source_node_id}-${target.id}-${slot.number}`, d: `M ${x1} ${y1} C ${x1 + bend} ${y1}, ${x2 - bend} ${y2}, ${x2} ${y2}`, tone: target.side === 'losers' ? 'primary' : 'accent' });
            }));
            setPaths(next);
        };
        measure();
        const observer = typeof ResizeObserver === 'undefined' ? null : new ResizeObserver(measure);
        if (observer) { observer.observe(boardRef.current); Object.values(refs.current).forEach((element) => element && observer.observe(element)); }
        window.addEventListener('resize', measure);
        return () => { observer?.disconnect(); window.removeEventListener('resize', measure); };
    }, [visible]);

    if (!visible.length) return <div className={`${panel} p-8 text-center text-sm text-muted`}>No matches are in this bracket lane yet.</div>;
    return <div ref={boardRef} className={`${panel} relative overflow-x-auto`}><svg aria-hidden="true" className="pointer-events-none absolute inset-0 z-0 hidden h-full w-full overflow-visible sm:block">{paths.map((path) => <path key={path.key} d={path.d} fill="none" stroke={`var(--${path.tone})`} strokeWidth="2.5" />)}</svg><div className="relative z-10 grid min-w-max grid-flow-col auto-cols-[13.5rem] gap-10 p-8">{rounds.map((round) => <section key={round} className="flex flex-col gap-5"><p className="font-condensed text-xs font-bold uppercase tracking-[0.16em] text-muted">Round {round}</p><div className="flex flex-col gap-7">{visible.filter((node) => node.round === round).map((node) => <MatchCard key={node.id} node={node} nodeRef={(element) => { refs.current[node.id] = element; }} />)}</div></section>)}</div></div>;
}

function RoundRobinBoard({ nodes = [] }) {
    const matches = nodes.filter((node) => node.contest).map((node) => ({ node, slot: node.slots[0], opponent: node.slots[1] }));
    const teams = [...new Set(matches.flatMap((match) => [match.slot?.label, match.opponent?.label]).filter(Boolean))];

    return <div className="flex flex-col gap-5"><div className={`${panel} overflow-x-auto`}><table className="min-w-[42rem] w-full text-left text-sm"><thead className="bg-surface-muted font-condensed text-xs uppercase tracking-[0.14em] text-muted"><tr><th className="px-4 py-3">Team</th>{teams.map((team) => <th key={team} className="px-4 py-3">{team}</th>)}<th className="px-4 py-3">Pts</th></tr></thead><tbody className="divide-y divide-border">{teams.map((team) => <tr key={team}><th className="px-4 py-3 font-bold">{team}</th>{teams.map((opponent) => <td key={opponent} className="px-4 py-3 text-muted">{team === opponent ? '—' : '·'}</td>)}<td className="px-4 py-3 font-condensed font-bold">0</td></tr>)}</tbody></table></div><div className={`${panel} divide-y divide-border`}>{matches.map(({ node, slot, opponent }) => <div key={node.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm"><span className="font-condensed text-xs font-bold uppercase tracking-[0.12em] text-muted">{node.key}</span><span className="font-bold">{slot?.label ?? 'TBD'} <span className="px-2 text-muted">vs</span> {opponent?.label ?? 'TBD'}</span><span className="text-xs text-muted">{node.contest.state}</span></div>)}</div></div>;
}

function DisciplineEntryEditor({ event, discipline, entry }) {
    const initial = entry.discipline_entry?.members ?? [];
    const [selected, setSelected] = useState(initial.map((member) => member.participant_id));
    const [starter, setStarter] = useState(initial.find((member) => member.is_starter)?.participant_id ?? '');
    const form = useForm({ members: [] });
    const save = (state = 'draft') => form.transform(() => ({ members: selected.map((id) => ({ participant_id: id, is_starter: String(id) === String(starter) })), state })).patch(route('admin.discipline-entries.update', [event.id, discipline.id, entry.id]), { preserveScroll: true });

    const teamSheetState = entry.status === 'locked' ? 'approved' : 'not approved';
    const lineupState = entry.discipline_entry?.state === 'locked' ? 'approved' : entry.discipline_entry?.state === 'draft' ? 'draft' : 'not started';

    return <div className="rounded-lg border border-border bg-surface-muted p-4"><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="font-bold text-foreground">{entry.delegation?.abbreviation ?? entry.delegation?.name ?? entry.name}</p><p className="text-xs text-muted">Team sheet: {teamSheetState} · Event lineup: {lineupState}</p></div><div className="flex gap-2"><button type="button" onClick={() => save('draft')} disabled={form.processing} className={quiet}>Save as draft</button><button type="button" onClick={() => save('locked')} disabled={form.processing || !selected.length} className={primary}>Approve lineup</button></div></div><div className="mt-3 grid gap-2 sm:grid-cols-2">{entry.participants.map((participant) => <label key={participant.id} className={`flex items-center gap-2 rounded-md border px-3 py-2 text-sm ${selected.includes(participant.id) ? 'border-primary bg-primary/5' : 'border-border bg-surface'}`}><input type="checkbox" checked={selected.includes(participant.id)} disabled={!participant.active || form.processing} onChange={() => setSelected((current) => current.includes(participant.id) ? current.filter((id) => id !== participant.id) : [...current, participant.id])} /><span className="min-w-0 flex-1 truncate">{participant.name}</span><input aria-label={`Starter for ${participant.name}`} type="radio" name={`starter-${entry.id}`} checked={String(starter) === String(participant.id)} disabled={!selected.includes(participant.id)} onChange={() => setStarter(participant.id)} /><span className="font-condensed text-[0.62rem] uppercase tracking-[0.1em] text-muted">starter</span></label>)}</div></div>;
}

function UncontestedSurface({ event, sport, division, entry }) {
    const delegation = entry?.delegation;
    const teamName = delegation?.name || entry?.name || 'Eligible team';
    const teamCode = delegation?.abbreviation || entry?.code;
    const activePlayers = entry?.participants?.filter((participant) => participant.active).length ?? 0;

    return <section className={`${panel} overflow-hidden`} aria-labelledby="uncontested-title">
        <div className="border-b border-primary/20 bg-primary/10 p-5 sm:p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-xs font-bold uppercase tracking-[0.18em] text-primary">UNCONTESTED</p>
                    <h3 id="uncontested-title" className="mt-1 font-serif text-2xl font-bold text-foreground">One eligible team advances</h3>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted">Only one eligible team is in this division, so it advances without a bracket. Record the official final placement in Results.</p>
                </div>
                <a href={sportWorkspaceUrl(event.id, sport.id, { section: 'results', division: division.id })} className={primary}>Open Results<AppIcon name="arrow-right" className="size-4" /></a>
            </div>
        </div>
        <div className="p-5 sm:p-6">
            <p className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Eligible team</p>
            <div className="mt-3 flex flex-col gap-2 rounded-lg border border-border bg-surface-muted p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p className="font-serif text-xl font-bold text-foreground">{teamName}</p>{teamCode ? <p className="mt-1 text-sm font-semibold text-muted">{teamCode}</p> : null}</div>
                <p className="text-sm text-muted">{activePlayers} active {activePlayers === 1 ? 'player' : 'players'}</p>
            </div>
        </div>
    </section>;
}

export default function Tournament({ event, sport, sports = [], division, discipline, proposal, entries = [], tournament, bracket, blockers = [], can_generate: canGenerate, can_redraw: canRedraw, can_publish: canPublish, is_archived: isArchived }) {
    const { errors = {}, flash = {} } = usePage().props;
    const [lane, setLane] = useState('winners');
    const generateForm = useForm({ command_uuid: crypto.randomUUID(), redraw: false });
    const publishForm = useForm({});
    const format = division.format;
    const isRoundRobin = format === 'round_robin';
    const selectedSport = sports.find((item) => String(item.id) === String(sport.id));
    const divisions = selectedSport?.divisions?.length ? selectedSport.divisions : [division];
    const shellSport = { ...sport, division_count: divisions.length, entry_count: division.entry_count ?? sport.entry_count, player_count: division.player_count ?? sport.player_count };
    const participatingScopeEntries = discipline
        ? entries.filter((entry) => !['withdrawn', 'disqualified'].includes(entry.status))
        : null;
    const requiredTeams = discipline
        ? participatingScopeEntries.length
        : division.participating_entry_count ?? division.entry_count ?? 0;
    const lockedTeams = discipline
        ? participatingScopeEntries.filter((entry) => entry.status === 'locked' && entry.discipline_entry?.state === 'locked').length
        : division.locked_entry_count ?? 0;
    const unreadyTeamCount = Math.max(requiredTeams - lockedTeams, 0);
    const readinessDetail = unreadyTeamCount
        ? discipline
            ? `${unreadyTeamCount} participating ${unreadyTeamCount === 1 ? 'team still needs' : 'teams still need'} an approved team sheet and event lineup.`
            : `${unreadyTeamCount} participating ${unreadyTeamCount === 1 ? 'team still needs' : 'teams still need'} an approved team sheet.`
        : 'ready for the draw';
    const eligibleCount = tournament?.eligible_entry_count ?? entries.filter((entry) => entry.status === 'locked' && (!discipline || entry.discipline_entry?.state === 'locked')).length;
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
    const isUncontested = tournament?.state === 'uncontested';
    const status = isUncontested ? 'Uncontested' : bracket?.state === 'published' || tournament?.state === 'published' ? 'Published' : bracket ? 'Draft' : 'Setup';
    const heading = discipline ? `${division.name} · ${discipline.name}` : division.name;
    const blockerDetails = blockers.map((blocker) => ({ kind: blockerKind(blocker), label: friendlyBlocker(blocker) }));
    const readinessBlockers = blockerDetails.map((blocker) => blocker.label);
    const manageRostersHref = sportWorkspaceUrl(event.id, sport.id, { section: 'teams', division: division.id });
    const uncontestedEntry = entries.find((entry) => entry.status === 'locked' && (!discipline || entry.discipline_entry?.state === 'locked')) ?? entries[0];
    const suppressBlockerAction = blockerDetails.some((blocker) => ['archived', 'configuration', 'general'].includes(blocker.kind));
    const blockerAction = !suppressBlockerAction && blockerDetails.some((blocker) => blocker.kind === 'roster')
        ? <a href={manageRostersHref} className={quiet}>Manage rosters<AppIcon name="arrow-right" className="size-4" /></a>
        : !suppressBlockerAction && discipline && entries.length > 0 && blockerDetails.some((blocker) => blocker.kind === 'discipline')
            ? <a href="#event-lineups" className={quiet}>Review lineups<AppIcon name="arrow-right" className="size-4" /></a>
            : null;

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">{event.name}</p><h1 className="font-serif text-2xl font-bold">Bracket</h1></div>}>
        <Head title={`${sport.name} ${heading} · Bracket`} />
        <main className="bg-background p-4 sm:p-7 lg:p-8"><SportWorkspaceShell event={event} sport={shellSport} division={division} divisions={divisions} activeSection="bracket">
            <div className="flex flex-col gap-5">
                {flash?.status ? <div role="status" className="rounded-xl border border-primary bg-primary/10 p-4 text-sm font-semibold text-primary">{flash.status}</div> : null}
                {Object.keys(errors).length ? <div role="alert" className="rounded-xl border border-danger bg-danger-surface p-4 text-sm font-semibold text-danger">{Object.values(errors).flat().join(' ')}</div> : null}

                <header className="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">{discipline ? 'Event bracket' : 'Division bracket'}</p><h2 className="mt-1 font-serif text-3xl font-bold text-foreground sm:text-4xl">Bracket</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-muted">Create and publish the official draw for {heading}.</p></div><span className="inline-flex min-h-8 items-center rounded-full border border-border bg-surface-muted px-3 text-xs font-bold uppercase tracking-[0.12em] text-muted">{status}</span></header>

                <div className="grid gap-3 sm:grid-cols-3"><div className={`${panel} p-4`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Competition format</p><p className="mt-2 font-serif text-xl font-bold text-foreground">{formatLabel(format)}</p><p className="mt-1 text-xs text-muted">{discipline?.family ?? division.participant_mode ?? 'Rules not configured'}</p></div><div className={`${panel} p-4`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-muted">{discipline ? 'Team sheets and lineups' : 'Team sheets'}</p><p className="mt-2 font-serif text-xl font-bold text-foreground">{lockedTeams} / {requiredTeams}</p><p className="mt-1 text-xs text-muted">{readinessDetail}</p></div><div className={`${panel} p-4`}><p className="text-xs font-bold uppercase tracking-[0.14em] text-muted">Teams in the draw</p><p className="mt-2 font-serif text-xl font-bold text-foreground">{eligibleCount}</p><p className="mt-1 text-xs text-muted">included in this draw</p></div></div>

                {!isUncontested && !bracket && readinessBlockers.length ? <WorkflowNotice title="Before generating the bracket" action={blockerAction}><span className="flex flex-col gap-1">{readinessBlockers.map((blocker) => <span key={blocker}>• {blocker}</span>)}</span></WorkflowNotice> : null}
                {discipline && entries.length ? <section id="event-lineups" className={`${panel} p-5`}><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">Event lineups</p><h3 className="mt-1 font-serif text-2xl font-bold text-foreground">Choose players for this event</h3><p className="mt-1 text-sm leading-6 text-muted">Choose the players from each team who will compete in this event. You can save a draft first, then approve the lineup after the team sheet is approved.</p></div><div className="mt-5 flex flex-col gap-3">{entries.map((entry) => <DisciplineEntryEditor key={entry.id} event={event} discipline={discipline} entry={entry} />)}</div></section> : null}

                {isUncontested ? <UncontestedSurface event={event} sport={sport} division={division} entry={uncontestedEntry} /> : <section className={`${panel} p-5 sm:p-6`}><div className="flex flex-wrap items-center justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-[0.16em] text-primary">{bracket ? 'Official bracket' : 'Draw setup'}</p><h3 className="mt-1 font-serif text-2xl font-bold text-foreground">{bracket ? `${formatLabel(format)} · ${status}` : 'Generate bracket'}</h3></div><div className="flex flex-wrap gap-2">{bracket && canRedraw ? <button type="button" onClick={redraw} disabled={generateForm.processing || isArchived} className={quiet}>Redraw</button> : null}{bracket && canPublish ? <button type="button" onClick={publish} disabled={publishForm.processing || isArchived} className={primary}>Publish bracket</button> : null}</div></div>
                    {!bracket ? <div className="mt-6 flex flex-col items-center gap-4 rounded-xl border border-dashed border-border bg-surface-muted p-8 text-center"><div><p className="font-serif text-xl font-bold text-foreground">{isArchived ? 'Archived event' : !proposal.supported_bracket ? 'Results-based competition' : readinessBlockers.length ? 'Complete the setup requirements' : 'Ready to generate'}</p><p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted">{!proposal.supported_bracket ? 'This competition uses measured results and standings; no elimination bracket is fabricated.' : readinessBlockers.length ? 'Complete the requirements above before generating the official draw.' : 'The generated preview remains editable until it is published.'}</p></div>{proposal.supported_bracket ? <button type="button" onClick={generate} disabled={!canGenerate || generateForm.processing || isArchived} className={primary}>{generateForm.processing ? 'Generating…' : 'Generate bracket'}<AppIcon name="arrow-right" className="size-4" /></button> : null}</div> : isRoundRobin ? <div className="mt-6"><RoundRobinBoard nodes={visibleNodes} /></div> : <div className="mt-6 flex flex-col gap-4"><div className="flex flex-wrap gap-2" role="tablist" aria-label="Bracket lanes">{[['winners', 'Winners'], ['losers', 'Losers'], ['championship', 'Championship']].filter(([value]) => value === 'winners' || visibleNodes.some((node) => node.side === value)).map(([value, label]) => <button key={value} type="button" role="tab" aria-selected={lane === value} onClick={() => setLane(value)} className={`min-h-9 rounded-lg px-3 text-xs font-bold uppercase tracking-[0.12em] ${lane === value ? 'bg-primary text-primary-foreground' : 'border border-border bg-surface text-muted'}`}>{label}</button>)}</div><BracketCanvas nodes={visibleNodes} side={lane} /></div>}
                </section>}
                {bracket ? <p className="text-xs leading-5 text-muted">The published bracket is frozen. Schedule, venue, and official result details remain managed from their workflow pages.</p> : null}
            </div>
        </SportWorkspaceShell></main>
    </AuthenticatedLayout>;
}
