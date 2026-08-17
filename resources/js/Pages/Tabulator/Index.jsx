import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

function WorkList({ title, items, judged = false }) {
    return <section aria-labelledby={`${title}-heading`} className="rounded-xl border border-border bg-surface"><header className="border-b border-border px-5 py-4"><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Work mode</p><h2 id={`${title}-heading`} className="mt-1 font-serif text-2xl font-bold">{title}</h2></header>
        {items.length ? <ul className="divide-y divide-border">{items.map((item) => <li key={item.href} className="grid gap-4 p-5 sm:grid-cols-[1fr_auto] sm:items-center"><div><h3 className="font-serif text-xl font-bold">{item.name}</h3>{judged ? <p className="mt-1 text-sm text-muted">{item.completion.submitted} / {item.completion.expected} Judge scorecards submitted</p> : <p className="mt-1 text-sm text-muted">{item.state_label}</p>}{item.readiness?.next_blocker ? <p aria-live="polite" className="mt-2 text-sm font-semibold text-danger">{item.readiness.next_blocker}</p> : null}</div><Link href={item.href} className="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Open {judged ? 'tabulation' : 'contest'}</Link></li>)}</ul> : <p className="p-6 text-sm text-muted">No {title.toLowerCase()} assignments.</p>}
    </section>;
}

export default function Index({ event, judged = [], objective = [] }) {
    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.15em] text-primary">{event?.name ?? 'Scoring operations'}</p><h1 className="font-serif text-2xl font-bold">My Tabulation</h1></div>}>
        <Head title="My Tabulation" />
        <main className="min-h-[calc(100vh-4rem)] bg-background p-4 text-foreground sm:p-7 lg:p-8"><div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-2"><WorkList title="Judged" items={judged} judged /><WorkList title="Objective" items={objective} /></div></main>
    </AuthenticatedLayout>;
}
