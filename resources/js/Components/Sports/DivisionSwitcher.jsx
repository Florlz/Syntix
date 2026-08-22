import React from 'react';
import Link from '@/Components/PrefetchLink';
import { sportWorkspaceDivisionUrl, sportWorkspaceUrl } from '@/Support/sportWorkspaceRoutes';

export default function DivisionSwitcher({ event, sport, divisions = [], division = null, activeSection = 'overview' }) {
    const allDivisionsSection = activeSection === 'bracket' ? 'overview' : activeSection;

    return <nav aria-label="Divisions" className="scroll-fade-x -mx-1 overflow-x-auto px-1 py-1">
        <div className="flex min-w-max items-center gap-2">
            <Link
                href={sportWorkspaceUrl(event.id, sport.id, { section: allDivisionsSection })}
                className={`inline-flex min-h-11 items-center rounded-sm border px-3.5 text-sm font-bold transition-colors ${!division ? 'border-primary bg-primary/10 text-primary' : 'border-control-border bg-surface text-muted hover:border-primary hover:text-primary'}`}
                aria-current={!division ? 'page' : undefined}
            >All divisions</Link>
            {divisions.map((item) => {
                const selected = String(item.id) === String(division?.id);
                return <Link
                    key={item.id}
                    href={sportWorkspaceDivisionUrl(event.id, sport.id, item.id, activeSection)}
                    className={`inline-flex min-h-11 items-center rounded-sm border px-3.5 text-sm font-bold transition-colors ${selected ? 'border-primary bg-primary/10 text-primary' : 'border-control-border bg-surface text-muted hover:border-primary hover:text-primary'}`}
                    aria-current={selected ? 'page' : undefined}
                >{item.name}</Link>;
            })}
        </div>
    </nav>;
}
