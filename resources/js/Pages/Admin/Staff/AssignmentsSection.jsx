import React, { useMemo, useState } from 'react';

const control = 'w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-primary focus:ring-primary';

function targetLabel(item) {
    return item.label || [item.competition, item.division, item.contest].filter(Boolean).join(' / ') || 'Unlabelled target';
}

export default function AssignmentsSection({ staff = [], event, onManage }) {
    const [query, setQuery] = useState('');
    const [role, setRole] = useState('');
    const [scope, setScope] = useState('');
    const rows = useMemo(() => staff.flatMap((person) => [
        ...(person.coverage?.judging_panels ?? []).map((panel) => ({
            id: `judge-${person.id}-${panel.contest_id}`,
            person,
            role: 'Judge',
            scope: 'judging_panel',
            target: panel.label,
            detail: `${panel.entry_count ?? panel.scorecard_count ?? 0} scorecards`,
            panel,
        })),
        ...(person.coverage?.tabulator_targets ?? []).map((target) => ({
            id: `tabulator-${person.id}-${target.assignment_id}`,
            person,
            role: 'Tabulator',
            scope: target.scope,
            target: targetLabel(target),
            detail: target.scope === 'division' ? 'Division assignment' : 'Contest assignment',
            assignment: target,
        })),
    ]).filter((row) => {
        const haystack = `${row.person.name} ${row.person.email} ${row.target}`.toLowerCase();
        return (!query || haystack.includes(query.toLowerCase()))
            && (!role || row.role.toLowerCase() === role)
            && (!scope || row.scope === scope);
    }), [staff, query, role, scope]);
    const missing = useMemo(() => staff.flatMap((person) => (person.coverage?.missing_roles ?? []).map((missingRole) => ({ person, role: missingRole }))).filter(({ person, role: missingRole }) => {
        const haystack = `${person.name} ${person.email} ${missingRole}`.toLowerCase();
        return (!query || haystack.includes(query.toLowerCase())) && (!role || missingRole === role);
    }), [staff, query, role]);

    return <section aria-labelledby="assignments-heading" className="space-y-5">
        <header><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">Assignments</p><h2 id="assignments-heading" className="mt-1 font-serif text-3xl font-bold">Assignment coverage</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-muted">See which Judges are assigned to judging panels and where Tabulators have operational responsibility.</p></header>
        {event.archived ? <div className="rounded-xl border border-accent bg-accent/10 p-4 text-sm text-foreground"><strong>Archived event.</strong> Assignment history remains available, but this event can no longer be modified.</div> : null}
        <dl className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-xl border border-border bg-surface p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Judging panels</dt><dd className="mt-1 font-serif text-2xl font-bold">{rows.filter((row) => row.role === 'Judge').length}</dd></div>
            <div className="rounded-xl border border-border bg-surface p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Tabulator targets</dt><dd className="mt-1 font-serif text-2xl font-bold">{rows.filter((row) => row.role === 'Tabulator').length}</dd></div>
            <div className="rounded-xl border border-border bg-surface p-4"><dt className="text-xs font-bold uppercase tracking-[0.12em] text-muted">Staff needing coverage</dt><dd className="mt-1 font-serif text-2xl font-bold">{new Set(missing.map(({ person }) => person.id)).size}</dd></div>
        </dl>
        <section aria-label="Assignment filters" className="grid gap-3 rounded-xl border border-border bg-surface p-4 md:grid-cols-[minmax(0,1fr)_12rem_12rem]">
            <label><span className="sr-only">Search person or activity</span><input type="search" value={query} onChange={(input) => setQuery(input.target.value)} placeholder="Search person or activity" className={control}/></label>
            <label><span className="sr-only">Filter assignment role</span><select aria-label="Filter assignment role" value={role} onChange={(input) => setRole(input.target.value)} className={control}><option value="">All roles</option><option value="judge">Judges</option><option value="tabulator">Tabulators</option></select></label>
            <label><span className="sr-only">Filter assignment scope</span><select aria-label="Filter assignment scope" value={scope} onChange={(input) => setScope(input.target.value)} className={control}><option value="">All scopes</option><option value="judging_panel">Judging panel</option><option value="division">Division</option><option value="contest">Contest</option></select></label>
        </section>
        {missing.length ? <section aria-labelledby="needs-coverage-heading" className="rounded-xl border border-accent bg-accent/10 p-5"><h3 id="needs-coverage-heading" className="font-serif text-xl font-bold">Needs coverage</h3><div className="mt-3 grid gap-3 md:grid-cols-2">{missing.map(({ person, role: missingRole }) => <button type="button" key={`${person.id}-${missingRole}`} onClick={() => onManage(person)} className="rounded-lg border border-accent/50 bg-surface p-4 text-left focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent"><p className="font-bold">{person.name}</p><p className="mt-1 text-xs font-bold uppercase tracking-[0.1em] text-primary">{missingRole}</p><p className="mt-2 text-sm text-muted">{missingRole === 'judge' ? 'Not assigned to a judging panel' : 'No Division or Contest assignment'}</p></button>)}</div></section> : null}
        {rows.length ? <div className="overflow-hidden rounded-xl border border-border bg-surface"><div className="hidden grid-cols-[minmax(10rem,1fr)_8rem_minmax(14rem,1.5fr)_10rem_auto] gap-4 border-b border-border bg-surface-muted px-5 py-3 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-muted md:grid"><span>Person</span><span>Role</span><span>Target</span><span>Scope</span><span className="sr-only">Action</span></div>{rows.map((row) => <div key={row.id} className="grid gap-3 border-b border-border px-5 py-4 last:border-b-0 md:grid-cols-[minmax(10rem,1fr)_8rem_minmax(14rem,1.5fr)_10rem_auto] md:items-center md:gap-4"><div><p className="font-bold">{row.person.name}</p><p className="text-xs text-muted md:hidden">{row.person.email}</p></div><span className="w-fit rounded-full bg-accent/15 px-2 py-1 text-[0.65rem] font-bold uppercase text-accent-foreground">{row.role}</span><div><p className="text-sm">{row.target}</p><p className="mt-1 text-xs text-muted">{row.detail}</p></div><p className="text-xs font-bold uppercase tracking-[0.1em] text-muted">{row.scope === 'judging_panel' ? 'Judging panel' : row.scope}</p>{row.role === 'Judge' ? <a href={route('admin.staff.index', { event: event.id, section: 'readiness' })} className="text-sm font-bold text-primary">Manage panel</a> : <button type="button" onClick={() => onManage(row.person)} className="text-left text-sm font-bold text-primary">Manage</button>}</div>)}</div> : <div className="rounded-xl border border-dashed border-border bg-surface p-10 text-center text-sm text-muted">No assignment matches these filters.</div>}
    </section>;
}
