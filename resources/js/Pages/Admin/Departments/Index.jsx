import React from 'react';
import AppIcon from '@/Components/AppIcon';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const primary = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#0B536D] px-4 text-sm font-bold text-white transition hover:bg-[#083e52] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2';

function DepartmentCard({ event, department }) {
    const divisionCount = department.sports.reduce((total, sport) => total + sport.divisions.length, 0);
    const startedCount = department.counts.rosters || 0;
    const accent = department.color || '#0B536D';

    return <article className="group relative overflow-hidden rounded-2xl border border-[#D8DEDC] bg-white shadow-[0_10px_28px_rgba(23,33,43,0.05)] transition duration-200 hover:-translate-y-0.5 hover:border-[#AAB8B5] hover:shadow-[0_18px_38px_rgba(23,33,43,0.09)] motion-reduce:transform-none">
        <div className="h-1.5" style={{ backgroundColor: accent }} aria-hidden="true" />
        <div className="relative overflow-hidden p-5 sm:p-6">
            <span className="pointer-events-none absolute -right-3 -top-7 font-serif text-[7rem] font-black leading-none text-[#0B2E4F]/[0.045]" aria-hidden="true">{department.abbreviation || 'D'}</span>
            <div className="relative flex items-start justify-between gap-4">
                <div className="min-w-0">
                    <p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-[#0B536D]">Event department</p>
                    <h2 className="mt-2 font-serif text-2xl font-bold leading-tight text-[#17212B]">{department.name}</h2>
                </div>
                <span className="grid size-12 shrink-0 place-items-center rounded-xl text-sm font-black text-white shadow-sm" style={{ backgroundColor: accent }}>{department.abbreviation || '—'}</span>
            </div>

            <dl className="relative mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-[#E0E5E3] bg-[#E0E5E3] sm:grid-cols-4">
                {[
                    ['Players', department.counts.players],
                    ['Coaches', department.counts.coaches],
                    ['Team sheets', `${startedCount}/${divisionCount}`],
                    ['Sports', department.sports.length],
                ].map(([label, value]) => <div key={label} className="bg-[#FAFBF9] px-3 py-3"><dt className="text-[0.62rem] font-bold uppercase tracking-[0.1em] text-[#78858A]">{label}</dt><dd className="mt-1 font-mono text-lg font-bold tabular-nums text-[#0B2E4F]">{value}</dd></div>)}
            </dl>

            <div className="relative mt-5 flex flex-col gap-3 border-t border-[#E6EAE8] pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p className={`text-xs ${department.counts.unassigned > 0 ? 'font-semibold text-amber-800' : 'text-[#68767E]'}`}>
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

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.14em] text-[#0B536D]">{event.name}</p><h1 className="font-serif text-2xl font-bold">Departments</h1></div>}>
        <Head title="Departments" />
        <main className="p-4 sm:p-7 lg:p-8">
            <div className="mx-auto max-w-[96rem] space-y-6">
                <section className="relative overflow-hidden rounded-2xl bg-[#0B2E4F] px-5 py-7 text-white sm:px-8 sm:py-9">
                    <div className="absolute inset-y-0 right-0 w-1/3 bg-[radial-gradient(circle_at_center,rgba(213,162,31,0.18),transparent_68%)]" aria-hidden="true" />
                    <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                        <div className="max-w-3xl"><p className="text-xs font-bold uppercase tracking-[0.16em] text-[#E7C865]">Roster operations</p><h2 className="mt-2 font-serif text-3xl font-bold sm:text-4xl">Choose a department first</h2><p className="mt-3 max-w-2xl text-sm leading-6 text-white/70">Each department has one clear home for its players, coaches, and team sheets across every event sport.</p></div>
                        <dl className="flex flex-wrap gap-x-8 gap-y-3 text-sm"><div><dt className="text-white/55">Departments</dt><dd className="mt-1 font-mono text-2xl font-bold">{summary.departments.length}</dd></div><div><dt className="text-white/55">Players</dt><dd className="mt-1 font-mono text-2xl font-bold">{summary.totals.players || 0}</dd></div><div><dt className="text-white/55">Team sheets</dt><dd className="mt-1 font-mono text-2xl font-bold">{summary.totals.rosters || 0}/{teamSheetSlots}</dd></div></dl>
                    </div>
                </section>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 className="font-serif text-2xl font-bold text-[#17212B]">Department directory</h2><p className="mt-1 text-sm text-[#68767E]">Open a department to work through its sports and rosters.</p></div>
                    <label className="relative w-full sm:max-w-sm"><span className="sr-only">Search departments</span><AppIcon name="search" className="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#68767E]" /><input type="search" value={query} onChange={(eventObject) => setQuery(eventObject.target.value)} placeholder="Find a department" className="min-h-11 w-full rounded-lg border-[#C4CECB] bg-white pl-10 text-sm focus:border-[#0B536D] focus:ring-[#0B536D]" /></label>
                </div>

                {departments.length ? <section className="grid gap-5 xl:grid-cols-2" aria-label="Event departments">{departments.map((department) => <DepartmentCard key={department.id} event={event} department={department} />)}</section> : <section className="rounded-2xl border border-dashed border-[#C4CECB] bg-white p-10 text-center"><h2 className="font-serif text-xl font-bold">No departments match that search</h2><p className="mt-2 text-sm text-[#68767E]">Try the department name or abbreviation.</p></section>}
            </div>
        </main>
    </AuthenticatedLayout>;
}
