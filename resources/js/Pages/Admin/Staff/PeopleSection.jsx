import AppIcon from '@/Components/AppIcon';
import React, { useMemo, useState } from 'react';

const control = 'w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-primary focus:ring-primary';
const action = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50';

function Summary({ summary = {} }) {
    const items = [
        ['People', summary.people ?? 0],
        ['Active', summary.active ?? 0],
        ['Judges', summary.judges ?? 0],
        ['Tabulators', summary.tabulators ?? 0],
        ['Need assignment', summary.needs_assignment ?? 0],
    ];

    return <dl className="grid gap-px overflow-hidden rounded-xl border border-border bg-border sm:grid-cols-5">
        {items.map(([label, value]) => <div key={label} className="bg-surface px-4 py-4">
            <dt className="text-[0.68rem] font-bold uppercase tracking-[0.12em] text-muted">{label}</dt>
            <dd className="mt-1 font-serif text-2xl font-bold text-foreground">{value}</dd>
        </div>)}
    </dl>;
}

function Coverage({ person, compact = false }) {
    const coverage = person.coverage ?? { judging_panels: [], tabulator_targets: [], missing_roles: [], total: 0 };
    const details = [
        coverage.judging_panels?.length ? `${coverage.judging_panels.length} judging panel${coverage.judging_panels.length === 1 ? '' : 's'}` : null,
        coverage.tabulator_targets?.length ? `${coverage.tabulator_targets.length} tabulator target${coverage.tabulator_targets.length === 1 ? '' : 's'}` : null,
    ].filter(Boolean);

    return <div className={compact ? '' : 'mt-3'}>
        <p className={`text-sm ${details.length ? 'text-muted' : 'font-semibold text-accent-foreground'}`}>
            {details.length ? details.join(' · ') : 'No scoring coverage'}
        </p>
        {coverage.missing_roles?.map((role) => <p key={role} className="mt-1 text-xs text-muted">
            {role === 'judge' ? 'Judge: Not assigned to a judging panel' : 'Tabulator: No Division or Contest assignment'}
        </p>)}
    </div>;
}

function PersonCard({ person, onManage }) {
    return <button type="button" onClick={() => onManage(person)} className="w-full rounded-xl border border-border bg-surface p-5 text-left shadow-sm transition hover:border-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">
        <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
                <h3 className="truncate font-serif text-lg font-bold">{person.name}</h3>
                <p className="mt-1 truncate text-xs text-muted">{person.email}</p>
            </div>
            <span className={`shrink-0 rounded-full px-2 py-1 text-[0.65rem] font-bold uppercase ${person.account_state === 'active' ? 'bg-primary/10 text-primary' : 'bg-danger-surface text-danger'}`}>{person.account_state}</span>
        </div>
        <div className="mt-4 flex flex-wrap gap-2">
            {person.roles.map((role) => <span key={role.id} className="rounded-full bg-accent/15 px-2 py-1 text-[0.65rem] font-bold uppercase text-accent-foreground">{role.role}</span>)}
        </div>
        <Coverage person={person}/>
        <span className="mt-4 inline-flex text-sm font-bold text-primary">Manage <span className="sr-only">{person.name}</span></span>
    </button>;
}

function PersonTable({ people, onManage }) {
    return <div className="hidden overflow-hidden rounded-xl border border-border bg-surface md:block">
        <div className="grid grid-cols-[minmax(15rem,1.4fr)_minmax(8rem,0.7fr)_minmax(9rem,0.9fr)_minmax(12rem,1fr)_auto] gap-4 border-b border-border bg-surface-muted px-5 py-3 text-[0.68rem] font-bold uppercase tracking-[0.12em] text-muted">
            <span>Person</span><span>Roles</span><span>Setup / access</span><span>Scoring coverage</span><span className="sr-only">Action</span>
        </div>
        {people.map((person) => <div key={person.id} className="grid grid-cols-[minmax(15rem,1.4fr)_minmax(8rem,0.7fr)_minmax(9rem,0.9fr)_minmax(12rem,1fr)_auto] items-center gap-4 border-b border-border px-5 py-4 last:border-b-0">
            <div className="min-w-0"><p className="truncate font-bold">{person.name}</p><p className="truncate text-xs text-muted">{person.email}</p></div>
            <div className="flex flex-wrap gap-1">{person.roles.map((role) => <span key={role.id} className="rounded-full bg-accent/15 px-2 py-1 text-[0.65rem] font-bold uppercase text-accent-foreground">{role.role}</span>)}</div>
            <div><p className="text-sm font-semibold capitalize">{person.account_state}</p><p className="mt-1 text-xs text-muted">{person.invitation?.state ?? 'Account established'}</p></div>
            <Coverage person={person} compact/>
            <button type="button" onClick={() => onManage(person)} className="text-sm font-bold text-primary focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent">Manage</button>
        </div>)}
    </div>;
}

export default function PeopleSection({ staff = [], staffSummary = {}, event, onManage }) {
    const [query, setQuery] = useState('');
    const [role, setRole] = useState('');
    const [coverage, setCoverage] = useState('');
    const [account, setAccount] = useState('');
    const visible = useMemo(() => staff.filter((person) => {
        const haystack = `${person.name} ${person.email}`.toLowerCase();
        return (!query || haystack.includes(query.toLowerCase()))
            && (!role || person.roles.some((item) => item.role === role))
            && (!coverage || (coverage === 'assigned' ? person.coverage?.total > 0 : person.coverage?.missing_roles?.length > 0))
            && (!account || person.account_state === account || (account === 'pending_setup' && person.invitation?.state === 'pending'));
    }), [staff, query, role, coverage, account]);

    return <section aria-labelledby="people-heading" className="space-y-5">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">People</p><h2 id="people-heading" className="mt-1 font-serif text-3xl font-bold">Staff access</h2><p className="mt-2 max-w-2xl text-sm leading-6 text-muted">Manage identity, event roles, account state, and a quick view of scoring coverage.</p></div>
            {!event.archived ? <a href={route('admin.accounts.create', event.id)} className={action}><AppIcon name="user-plus"/>Invite staff</a> : null}
        </div>
        {event.archived ? <div className="rounded-xl border border-accent bg-accent/10 p-4 text-sm text-foreground"><strong>Archived event.</strong> Staff records and scoring history remain available, but this event can no longer be modified.</div> : null}
        <Summary summary={staffSummary}/>
        <section aria-label="People filters" className="grid gap-3 rounded-xl border border-border bg-surface p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_10rem_12rem_12rem]">
            <label className="relative"><span className="sr-only">Search staff</span><AppIcon name="search" className="absolute left-3 top-3 size-5 text-muted"/><input type="search" value={query} onChange={(input) => setQuery(input.target.value)} placeholder="Search name or email" className={`${control} pl-10`}/></label>
            <label><span className="sr-only">Filter by role</span><select aria-label="Filter by role" value={role} onChange={(input) => setRole(input.target.value)} className={control}><option value="">All roles</option><option value="judge">Judges</option><option value="tabulator">Tabulators</option></select></label>
            <label><span className="sr-only">Filter by coverage</span><select aria-label="Filter by coverage" value={coverage} onChange={(input) => setCoverage(input.target.value)} className={control}><option value="">All coverage</option><option value="assigned">Assigned</option><option value="needs_assignment">Needs assignment</option></select></label>
            <label><span className="sr-only">Filter by account state</span><select aria-label="Filter by account state" value={account} onChange={(input) => setAccount(input.target.value)} className={control}><option value="">All accounts</option><option value="active">Active</option><option value="disabled">Disabled</option><option value="pending_setup">Pending setup</option></select></label>
        </section>
        <p className="text-sm text-muted">Showing {visible.length} of {staff.length} staff records</p>
        {visible.length ? <><PersonTable people={visible} onManage={onManage}/><div className="grid gap-4 md:hidden">{visible.map((person) => <PersonCard key={person.id} person={person} onManage={onManage}/>)}</div></> : <div className="rounded-xl border border-dashed border-border bg-surface p-10 text-center text-sm text-muted">No staff match these filters.</div>}
    </section>;
}
