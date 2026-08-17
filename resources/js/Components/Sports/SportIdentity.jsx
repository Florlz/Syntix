import React from 'react';
import AppIcon from '@/Components/AppIcon';

export default function SportIdentity({ sport, division = null }) {
    const entryCount = division?.entry_count ?? sport.entry_count;
    const participatingEntryCount = division?.participating_entry_count ?? entryCount;
    const playerCount = division?.player_count ?? sport.player_count;
    const lockedCount = division?.locked_entry_count;
    const readiness = division
        ? lockedCount === undefined || participatingEntryCount === undefined ? '—' : `${lockedCount}/${participatingEntryCount}`
        : sport.division_count ?? '—';

    return <section className="border-b border-border pb-5 pt-4 sm:pt-5" aria-labelledby="sport-workspace-title">
        <div className="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-xs font-bold uppercase tracking-[0.16em] text-primary">
                    <span>{sport.active === false ? 'Inactive sport' : 'Competition operations'}</span>
                    <span aria-hidden="true" className="text-border">/</span>
                    <span className="text-muted">{division ? 'Selected division' : 'All divisions'}</span>
                </div>
                <h1 id="sport-workspace-title" className="mt-2 font-serif text-4xl font-bold tracking-tight text-foreground sm:text-5xl">{sport.name}</h1>
                <p data-workspace-division className="mt-2 text-base font-semibold text-muted">{division?.name || 'All divisions'}</p>
            </div>
            <div className="grid grid-cols-3 divide-x divide-border rounded-xl border border-border bg-surface-muted px-1 py-3 sm:min-w-[24rem]">
                <div className="px-3 sm:px-4"><p className="text-[0.65rem] font-bold uppercase tracking-[0.12em] text-muted">Teams</p><p className="mt-1 font-condensed text-xl font-bold text-foreground">{entryCount ?? '—'}</p></div>
                <div className="px-3 sm:px-4"><p className="text-[0.65rem] font-bold uppercase tracking-[0.12em] text-muted">Players</p><p className="mt-1 font-condensed text-xl font-bold text-foreground">{playerCount ?? '—'}</p></div>
                <div className="px-3 sm:px-4"><p className="flex items-center gap-1 text-[0.65rem] font-bold uppercase tracking-[0.12em] text-muted"><AppIcon name="lock" className="size-3" /> Ready</p><p className="mt-1 font-condensed text-xl font-bold text-foreground">{readiness}</p></div>
            </div>
        </div>
    </section>;
}
