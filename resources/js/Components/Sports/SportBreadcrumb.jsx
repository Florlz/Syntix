import React from 'react';
import Link from '@/Components/PrefetchLink';
import { sportWorkflow, sportWorkspaceUrl } from '@/Support/sportWorkspaceRoutes';

export default function SportBreadcrumb({ event, sport, division = null, activeSection = 'overview' }) {
    const section = activeSection === 'overview' ? null : sportWorkflow[activeSection];

    return <nav aria-label="Breadcrumb" className="overflow-x-auto text-sm text-muted">
        <ol className="flex min-w-max items-center gap-2">
            <li><Link href={route('admin.sports.index', event.id)} className="font-semibold text-primary underline decoration-accent underline-offset-4">Sports Directory</Link></li>
            <li aria-hidden="true">/</li>
            <li><Link href={sportWorkspaceUrl(event.id, sport.id)} className="font-semibold text-primary">{sport.name}</Link></li>
            {division ? <><li aria-hidden="true">/</li><li className="font-semibold text-foreground" aria-current={section ? undefined : 'page'}>{division.name}</li></> : null}
            {section ? <><li aria-hidden="true">/</li><li className="font-semibold text-foreground" aria-current="page">{section}</li></> : null}
        </ol>
    </nav>;
}
