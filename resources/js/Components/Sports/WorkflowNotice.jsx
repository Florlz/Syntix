import React from 'react';
import AppIcon from '@/Components/AppIcon';

export default function WorkflowNotice({ title, children, action = null, tone = 'attention' }) {
    const toneClass = tone === 'success' ? 'border-primary/30 bg-primary/5' : tone === 'danger' ? 'border-danger/30 bg-danger-surface' : 'border-accent/40 bg-accent/10';
    const icon = tone === 'success' ? 'check-circle' : tone === 'danger' ? 'warning' : 'warning';

    return <section role={tone === 'danger' ? 'alert' : 'status'} className={`flex flex-col gap-4 border p-4 sm:flex-row sm:items-start sm:justify-between ${toneClass}`}>
        <div className="flex items-start gap-3"><span className="mt-0.5 grid size-8 shrink-0 place-items-center border border-border bg-surface text-primary"><AppIcon name={icon} className="size-4" /></span><div><h2 className="text-lg font-bold text-foreground">{title}</h2>{children ? <div className="mt-1 text-sm leading-6 text-muted">{children}</div> : null}</div></div>
        {action}
    </section>;
}
