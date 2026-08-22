export const adminStyles = Object.freeze({
    page: 'min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8',
    section: 'border border-border bg-surface',
    quietSection: 'border border-border bg-surface-muted',
    primaryAction: 'inline-flex min-h-11 items-center justify-center gap-2 rounded-sm border border-primary bg-primary px-4 text-sm font-bold text-primary-foreground transition-colors hover:border-primary-hover hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50',
    secondaryAction: 'inline-flex min-h-11 items-center justify-center gap-2 rounded-sm border border-border bg-surface px-4 text-sm font-bold text-primary transition-colors hover:border-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50',
    dangerAction: 'inline-flex min-h-11 items-center justify-center gap-2 rounded-sm border border-danger bg-danger-surface px-4 text-sm font-bold text-danger transition-colors hover:bg-danger/10 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-danger focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50',
    field: 'min-h-11 w-full rounded-sm border-border bg-surface text-foreground placeholder:text-muted focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-muted',
    toolbar: 'flex flex-col gap-3 border-y border-border bg-surface-muted px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
    tableHead: 'border-y border-border bg-surface-muted text-xs font-bold uppercase tracking-[0.08em] text-muted',
});
