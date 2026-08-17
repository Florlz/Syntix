import React from 'react';

const statusCopy = {
    not_started: 'Not started',
    review: 'Needs attention',
    ready: 'Ready',
    locked: 'Approved',
    blocked: 'Blocked',
};

function stateFor(division) {
    const requiredTeams = division.participating_entry_count ?? division.entry_count ?? 0;

    if ((division.locked_entry_count ?? 0) > 0 && division.locked_entry_count === requiredTeams) return 'ready';
    if ((division.unlocked_entry_count ?? 0) > 0) return 'review';
    return requiredTeams ? 'ready' : 'not_started';
}

export default function DivisionStatus({ division, compact = false }) {
    const state = division?.state || stateFor(division || {});
    const label = statusCopy[state] || 'Needs attention';
    const requiredTeams = division?.participating_entry_count ?? division?.entry_count ?? 0;
    const summary = requiredTeams ? `${division.locked_entry_count ?? 0} of ${requiredTeams} team sheets ready` : 'No participating teams yet';

    return <span className="inline-flex items-center gap-2 text-sm font-semibold text-muted" data-division-status={state}>
        <span aria-hidden="true" className={`size-2 rounded-full ${state === 'ready' || state === 'locked' ? 'bg-primary' : state === 'blocked' ? 'bg-danger' : 'bg-accent'}`} />
        <span>{compact ? label : `${label} · ${summary}`}</span>
    </span>;
}
