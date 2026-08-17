import React from 'react';
import Link from '@/Components/PrefetchLink';
import AppIcon from '@/Components/AppIcon';
import { sportWorkflow, sportWorkspaceUrl } from '@/Support/sportWorkspaceRoutes';

const icons = { overview: 'overview', teams: 'users', bracket: 'trophy', schedule: 'calendar', results: 'clipboard-check' };

export default function SportWorkflowNav({ event, sport, division = null, activeSection = 'overview' }) {
    return <nav aria-label="Sport workflow" className="scroll-fade-x -mx-1 overflow-x-auto border-b border-border px-1">
        <div className="flex min-w-max items-end gap-1">
            {Object.entries(sportWorkflow).map(([section, label]) => {
                const selected = section === activeSection;
                const requiresDivision = section === 'bracket' && !division;
                const itemClass = `inline-flex min-h-12 items-center gap-2 border-b-2 px-3 text-sm font-bold transition ${selected ? 'border-primary text-primary' : 'border-transparent text-muted hover:border-border hover:text-foreground'}`;
                if (requiresDivision) return <span key={section} className={`${itemClass} cursor-not-allowed opacity-60`} aria-disabled="true" title="Choose a division first"><AppIcon name={icons[section]} className="size-4" />{label}</span>;
                return <Link key={section} href={sportWorkspaceUrl(event.id, sport.id, { section, division: division?.id })} className={itemClass} aria-current={selected ? 'page' : undefined}><AppIcon name={icons[section]} className="size-4" />{label}</Link>;
            })}
        </div>
    </nav>;
}
