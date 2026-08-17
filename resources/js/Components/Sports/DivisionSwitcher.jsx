import React from 'react';
import Link from '@/Components/PrefetchLink';
import { sportWorkspaceDivisionUrl, sportWorkspaceUrl } from '@/Support/sportWorkspaceRoutes';

export default function DivisionSwitcher({ event, sport, divisions = [], division = null, activeSection = 'overview' }) {
    return <nav aria-label="Divisions" className="scroll-fade-x -mx-1 overflow-x-auto px-1 py-1">
        <div className="flex min-w-max items-center gap-2">
            <Link
                href={sportWorkspaceUrl(event.id, sport.id)}
                className={`inline-flex min-h-9 items-center rounded-full border px-3.5 text-sm font-bold transition ${!division ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-surface text-muted hover:border-primary hover:text-primary'}`}
                aria-current={!division ? 'page' : undefined}
            >All divisions</Link>
            {divisions.map((item) => {
                const selected = String(item.id) === String(division?.id);
                return <Link
                    key={item.id}
                    href={sportWorkspaceDivisionUrl(event.id, sport.id, item.id, activeSection)}
                    className={`inline-flex min-h-9 items-center rounded-full border px-3.5 text-sm font-bold transition ${selected ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-surface text-muted hover:border-primary hover:text-primary'}`}
                    aria-current={selected ? 'page' : undefined}
                >{item.name}</Link>;
            })}
        </div>
    </nav>;
}
