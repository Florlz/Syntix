import React from 'react';

const statusCopy = {
    not_started: 'Not started',
    review: 'Needs attention',
    ready: 'Ready',
    locked: 'Locked',
    blocked: 'Blocked',
};

function stateFor(division) {
    if ((division.locked_entry_count ?? 0) > 0 && division.locked_entry_count === division.entry_count) return 'ready';
    if ((division.unlocked_entry_count ?? 0) > 0) return 'review';
    return division.entry_count ? 'ready' : 'not_started';
}

export default function DivisionStatus({ division, compact = false }) {
    const state = division?.state || stateFor(division || {});
    const label = statusCopy[state] || 'Needs attention';
    const summary = division?.entry_count ? `${division.locked_entry_count ?? 0} of ${division.entry_count} team sheets ready` : 'No team sheets created';

    return <span className="inline-flex items-center gap-2 text-sm font-semibold text-muted" data-division-status={state}>
        <span aria-hidden="true" className={`size-2 rounded-full ${state === 'ready' || state === 'locked' ? 'bg-primary' : state === 'blocked' ? 'bg-danger' : 'bg-accent'}`} />
        <span>{compact ? label : `${label} · ${summary}`}</span>
    </span>;
}
