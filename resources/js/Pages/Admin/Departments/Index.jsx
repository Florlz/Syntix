import React from 'react';
import { AdminEmptyState, AdminMasthead } from '@/Components/Admin/AdminSurface';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { adminStyles } from '@/Support/adminStyles';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const primary = adminStyles.primaryAction;

function DepartmentCard({ event, department }) {
    const divisionCount = department.sports.reduce((total, sport) => total + sport.divisions.length, 0);
    const startedCount = department.counts.rosters || 0;
    const accent = department.color || '#0B536D';

    return <article className="group relative overflow-hidden border border-border bg-surface transition-colors hover:border-primary">
        <div className="h-1.5" style={{ backgroundColor: accent }} aria-hidden="true" />
        <div className="relative overflow-hidden p-5 sm:p-6">
            <span className="pointer-events-none absolute -right-3 -top-7 font-serif text-[7rem] font-black leading-none text-sidebar/[0.045]" aria-hidden="true">{department.abbreviation || 'D'}</span>
            <div className="relative flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-primary">Event department</p>
                    <h2 className="mt-2 font-serif text-2xl font-bold leading-tight text-foreground">{department.name}</h2>
                </div>
                <span className="grid size-12 shrink-0 place-items-center rounded-sm text-sm font-black text-white" style={{ backgroundColor: accent }}>{department.abbreviation || '—'}</span>
            </div>

            <dl className="relative mt-6 grid grid-cols-2 gap-px overflow-hidden border border-border bg-border sm:grid-cols-4">
                {[
                    ['Players', department.counts.players],
                    ['Coaches', department.counts.coaches],
                    ['Team sheets', `${startedCount}/${divisionCount}`],
                    ['Sports', department.sports.length],
                ].map(([label, value]) => <div key={label} className="bg-surface-muted px-3 py-3"><dt className="text-[0.62rem] font-bold uppercase tracking-[0.1em] text-muted">{label}</dt><dd className="mt-1 font-condensed text-2xl font-bold tabular-nums text-primary">{value}</dd></div>)}
            </dl>

            <div className="relative mt-5 flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className={`text-xs ${department.counts.unassigned > 0 ? 'font-semibold text-danger' : 'text-muted'}`}>
                    {department.counts.unassigned > 0 ? `${department.counts.unassigned} player${department.counts.unassigned === 1 ? '' : 's'} not yet rostered` : 'Every registered player is on a roster'}
                </p>
                <Link href={route('admin.departments.show', [event.id, department.id])} className={`${primary} shrink-0`}>Manage rosters <AppIcon name="arrow-right" className="size-4" /></Link>
            </div>
        </div>
    </article>;
}

export default function DepartmentDirectory({ event, directory_summary: summary = { departments: [], totals: {} } }) {
    const [query, setQuery] = useState('');
    const teamSheetSlots = summary.departments.reduce(
        (total, department) => total + department.sports.reduce((sportTotal, sport) => sportTotal + sport.divisions.length, 0),
        0,
    );
    const departments = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return summary.departments;
        return summary.departments.filter((department) => `${department.name} ${department.abbreviation || ''}`.toLowerCase().includes(needle));
    }, [query, summary.departments]);

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-primary">{event.name}</p><h1 className="font-serif text-2xl font-bold">Departments</h1></div>}>
        <Head title="Departments" />
        <main className={adminStyles.page}>
            <div className="mx-auto max-w-[96rem] space-y-6">
                <AdminMasthead eyebrow="Roster operations" title="Choose a department first" description="Each department has one clear home for its players, coaches, and team sheets across every event sport.">
                    <dl className="grid grid-cols-3 divide-x divide-border text-sm"><div className="px-3 first:pl-0"><dt className="text-muted">Departments</dt><dd className="mt-1 font-condensed text-3xl font-bold text-primary">{summary.departments.length}</dd></div><div className="px-3"><dt className="text-muted">Players</dt><dd className="mt-1 font-condensed text-3xl font-bold text-primary">{summary.totals.players || 0}</dd></div><div className="px-3"><dt className="text-muted">Team sheets</dt><dd className="mt-1 font-condensed text-3xl font-bold text-primary">{summary.totals.rosters || 0}/{teamSheetSlots}</dd></div></dl>
                </AdminMasthead>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 className="font-serif text-2xl font-bold text-foreground">Department directory</h2><p className="mt-1 text-sm text-muted">Open a department to work through its sports and rosters.</p></div>
                    <label className="relative w-full sm:max-w-sm"><span className="sr-only">Search departments</span><AppIcon name="search" className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" /><input type="search" value={query} onChange={(eventObject) => setQuery(eventObject.target.value)} placeholder="Find a department" className={`${adminStyles.field} pl-10`} /></label>
                </div>

                {departments.length ? <section className="grid gap-5 xl:grid-cols-2" aria-label="Event departments">{departments.map((department) => <DepartmentCard key={department.id} event={event} department={department} />)}</section> : <AdminEmptyState title="No departments match that search" description="Try the department name or abbreviation." />}
            </div>
        </main>
    </AuthenticatedLayout>;
}
