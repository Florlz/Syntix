import React from 'react';

const profiles = {
    team_total: { eyebrow: 'Team total', score: 'Final points', help: 'Record the official final points for both sides.' },
    best_of_sets: { eyebrow: 'Best of sets', score: 'Sets won', help: 'Preserve every set score before completing.' },
    team_tie: { eyebrow: 'Team tie', score: 'Ties won', help: 'Preserve Singles–Doubles–Singles rubber winners.' },
    chess: { eyebrow: 'Chess', score: 'Board points', help: 'Preserve each board result and the official match result.' },
    combat_rounds: { eyebrow: 'Combat', score: 'Rounds won', help: 'Preserve round-by-round points.' },
    quiz_bowl: { eyebrow: 'Quiz bowl', score: 'Final points', help: 'Preserve official round scores.' },
    measurement: { eyebrow: 'Measured result', score: 'Official measurement', help: 'Record verified measurements and source notes.' },
};
const parseNumbers = (value) => value.split(',').map((item) => item.trim()).filter(Boolean).map(Number).filter(Number.isFinite);

function EvidenceFields({ profile, evidence, onChange, homeName, awayName }) {
    const set = (key, value) => onChange('evidence', { ...evidence, [key]: value });
    if (profile === 'best_of_sets' || profile === 'combat_rounds' || profile === 'quiz_bowl') {
        const noun = profile === 'best_of_sets' ? 'set' : profile === 'combat_rounds' ? 'round' : 'quiz round';
        return <div className="mt-4 grid gap-4 sm:grid-cols-2"><label className="text-xs font-bold uppercase tracking-[0.12em] text-white/60">{homeName} {noun} scores<input aria-label={`${homeName} ${noun} scores`} value={(evidence.home_scores || []).join(', ')} onChange={(event) => set('home_scores', parseNumbers(event.target.value))} placeholder="e.g. 25, 21, 25" className="mt-2 w-full rounded-xl border-white/20 bg-white/8 text-white placeholder:text-white/40 focus:border-accent focus:ring-accent"/></label><label className="text-xs font-bold uppercase tracking-[0.12em] text-white/60">{awayName} {noun} scores<input aria-label={`${awayName} ${noun} scores`} value={(evidence.away_scores || []).join(', ')} onChange={(event) => set('away_scores', parseNumbers(event.target.value))} placeholder="e.g. 20, 25, 18" className="mt-2 w-full rounded-xl border-white/20 bg-white/8 text-white placeholder:text-white/40 focus:border-accent focus:ring-accent"/></label></div>;
    }
    if (profile === 'team_tie') {
        const rubbers = evidence.rubbers || ['', '', ''];
        return <fieldset className="mt-4"><legend className="text-xs font-bold uppercase tracking-[0.12em] text-white/60">Rubber winners</legend><div className="mt-2 grid gap-3 sm:grid-cols-3">{['Singles 1', 'Doubles', 'Singles 2'].map((label, index) => <label key={label} className="text-xs font-bold">{label}<select aria-label={`${label} winner`} value={rubbers[index] || ''} onChange={(event) => { const next = [...rubbers]; next[index] = event.target.value; set('rubbers', next); }} className="mt-1 w-full rounded-lg border-white/20 bg-sidebar text-white"><option value="">Choose winner</option><option value="home">{homeName}</option><option value="away">{awayName}</option></select></label>)}</div></fieldset>;
    }
    if (profile === 'chess') {
        return <label className="mt-4 block text-xs font-bold uppercase tracking-[0.12em] text-white/60">Board results<input aria-label="Board results" value={(evidence.board_results || []).join(', ')} onChange={(event) => set('board_results', event.target.value.split(',').map((item) => item.trim()).filter(Boolean))} placeholder="home_win, draw, away_win" className="mt-2 w-full rounded-xl border-white/20 bg-white/8 text-white placeholder:text-white/40"/></label>;
    }
    return <label className="mt-4 block text-xs font-bold uppercase tracking-[0.12em] text-white/60">Verification notes<textarea value={evidence.notes || ''} onChange={(event) => set('notes', event.target.value)} rows="3" className="mt-2 w-full rounded-xl border-white/20 bg-white/8 text-white focus:border-accent focus:ring-accent"/></label>;
}

export default function ObjectiveScoreProfile({ profile, homeName, awayName, home, away, result, evidence = {}, onChange }) {
    const config = profiles[profile] || profiles.team_total;
    const scoreInput = (side, name, value) => <label className="rounded-xl border border-white/15 bg-white/8 p-4"><span className="text-xs font-bold uppercase tracking-[0.12em] text-white/60">{name} · {config.score}</span><input name={`${side}_score`} aria-label={`${name} ${config.score}`} inputMode="decimal" value={value} onChange={(event) => onChange(side, event.target.value)} type="number" min="0" step="any" className="mt-3 w-full border-0 border-b border-white/20 bg-transparent px-0 font-mono text-5xl font-bold text-white outline-hidden ring-0 focus-visible:border-accent focus-visible:ring-2 focus-visible:ring-accent"/></label>;
    return <section aria-labelledby="objective-profile-title" className="rounded-2xl bg-sidebar p-6 text-white shadow-lg sm:p-8"><p className="text-xs font-bold uppercase tracking-[0.16em] text-accent">{config.eyebrow}</p><h2 id="objective-profile-title" className="mt-2 font-serif text-3xl font-bold">Official contest evidence</h2><p className="mt-2 text-sm leading-6 text-white/65">{config.help}</p><div className="mt-6 grid gap-4 sm:grid-cols-2">{scoreInput('home', homeName, home)}{scoreInput('away', awayName, away)}</div>{profile === 'chess' ? <label className="mt-4 block rounded-xl border border-white/15 bg-white/8 p-4"><span className="text-xs font-bold uppercase tracking-[0.12em] text-white/60">Official result</span><select name="chess_result" value={result} onChange={(event) => onChange('result', event.target.value)} className="mt-3 w-full rounded-lg border-white/20 bg-sidebar text-white"><option value="home_win">{homeName} wins</option><option value="draw">Draw</option><option value="away_win">{awayName} wins</option></select></label> : null}<EvidenceFields profile={profile} evidence={evidence} onChange={onChange} homeName={homeName} awayName={awayName}/></section>;
}
