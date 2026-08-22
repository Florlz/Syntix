import React, { useId } from 'react';
import { adminStyles } from '@/Support/adminStyles';

function classes(...values) {
    return values.filter(Boolean).join(' ');
}

export function AdminMasthead({ eyebrow, title, description, actions, children, className = '' }) {
    const headingId = useId();

    return <section aria-labelledby={headingId} className={classes('border-y border-border bg-surface', className)}>
        <div className="flex flex-col gap-5 px-5 py-6 sm:px-7 lg:flex-row lg:items-end lg:justify-between">
            <div className="min-w-0">
                {eyebrow ? <p className="text-xs font-bold uppercase tracking-[0.12em] text-primary">{eyebrow}</p> : null}
                <h1 id={headingId} className={classes('text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl', eyebrow && 'mt-1')}>{title}</h1>
                {description ? <p className="mt-2 max-w-3xl text-sm leading-6 text-muted sm:text-base">{description}</p> : null}
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
        </div>
        {children ? <div className="border-t border-border px-5 py-4 sm:px-7">{children}</div> : null}
    </section>;
}

export function AdminSection({ title, description, actions, as: Component = 'section', children, className = '' }) {
    const headingId = useId();

    return <Component aria-labelledby={headingId} className={classes(adminStyles.section, className)}>
        <header className="flex flex-col gap-3 border-b border-border px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
                <h2 id={headingId} className="text-lg font-bold text-foreground">{title}</h2>
                {description ? <p className="mt-1 max-w-3xl text-sm leading-6 text-muted">{description}</p> : null}
            </div>
            {actions ? <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div> : null}
        </header>
        <div>{children}</div>
    </Component>;
}

export function AdminEmptyState({ title, description, action, icon, className = '' }) {
    const headingId = useId();

    return <section aria-labelledby={headingId} className={classes('border border-dashed border-border bg-surface-muted px-5 py-10 text-center', className)}>
        {icon ? <div className="mx-auto mb-3 grid size-10 place-items-center text-primary" aria-hidden="true">{icon}</div> : null}
        <h2 id={headingId} className="text-lg font-bold text-foreground">{title}</h2>
        {description ? <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-muted">{description}</p> : null}
        {action ? <div className="mt-5 flex justify-center">{action}</div> : null}
    </section>;
}
