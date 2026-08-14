import React from 'react';
import Link from '@/Components/PrefetchLink';

const linkBase = 'inline-flex min-h-10 items-center justify-center rounded-lg border px-3 py-2 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transition-none';
const linkQuiet = `${linkBase} border-[#C6D0CC] bg-white text-[#17333F] hover:border-[#0B536D] hover:bg-[#F2F7F5]`;
const linkCurrent = `${linkBase} border-[#0B2E4F] bg-[#0B2E4F] text-white shadow-sm`;

function valueFrom(value, id, name) {
    if (value && typeof value === 'object') {
        return { id: value.id ?? id, name: value.name ?? name };
    }

    return { id: id ?? value, name: name ?? (value ? String(value) : '') };
}

function withDivision(href, divisionId) {
    if (!divisionId) return href;

    return `${href}${href.includes('?') ? '&' : '?'}division=${encodeURIComponent(divisionId)}`;
}

/**
 * Cross-workflow navigation for a sport-scoped task page.
 *
 * Schedule and Results can be opened for a whole sport, while Bracket is a
 * division-only workflow. Teams remain department-owned, so that link always
 * returns to the event's department directory.
 */
export default function SportContextNav({
    event,
    competition = null,
    competitionId = null,
    competitionName = null,
    sport = null,
    sportId = null,
    sportName = null,
    division = null,
    divisionId = null,
    divisionName = null,
    currentTask,
}) {
    const eventId = event?.id ?? event;
    const competitionValue = valueFrom(competition ?? sport, competitionId ?? sportId, competitionName ?? sportName);
    const divisionValue = valueFrom(division, divisionId, divisionName);

    if (!eventId || !competitionValue.id) return null;

    const hubHref = withDivision(route('admin.sports.show', [eventId, competitionValue.id]), divisionValue.id);
    const teamsHref = route('admin.departments.index', eventId);
    const scheduleHref = route('admin.sports.schedules', {
        event: eventId,
        competition: competitionValue.id,
        ...(divisionValue.id ? { division: divisionValue.id } : {}),
    });
    const resultsHref = route('admin.approvals.index', {
        event: eventId,
        competition: competitionValue.id,
        ...(divisionValue.id ? { division: divisionValue.id } : {}),
    });
    const bracketHref = divisionValue.id
        ? route('admin.sports.tournament', [eventId, divisionValue.id])
        : null;
    const contextName = [competitionValue.name, divisionValue.name].filter(Boolean).join(' / ');
    const current = (task) => currentTask === task ? { 'aria-current': 'page', className: linkCurrent } : { className: linkQuiet };

    return (
        <section className="border border-[#D6DDDA] bg-[#F8FAF9] p-4 shadow-[0_4px_16px_rgba(17,38,51,0.04)] sm:p-5" aria-label="Sport context">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0">
                    <p className="font-condensed text-[0.68rem] font-bold uppercase tracking-[0.16em] text-[#B07E00]">Current sport</p>
                    <p className="mt-1 truncate text-base font-bold text-[#17333F]" title={contextName}>{contextName}</p>
                </div>
                <Link href={hubHref} className="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-[#0B2E4F] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#08223B] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transition-none sm:w-auto">Back to {competitionValue.name}</Link>
            </div>
            <nav className="mt-4 flex flex-wrap items-center gap-2 border-t border-[#DDE5E1] pt-4" aria-label="Sport workflow">
                <span className="mr-1 font-condensed text-[0.68rem] font-bold uppercase tracking-[0.14em] text-[#748388]">Open a task</span>
                <Link href={teamsHref} {...current('teams')}>Teams</Link>
                {bracketHref
                    ? <Link href={bracketHref} {...current('bracket')}>Bracket</Link>
                    : <span className={`${linkBase} cursor-not-allowed border-[#E3E1D4] bg-[#FBFAF5] text-[#8B8060]`} aria-disabled="true" title="Choose a division first">Choose division for bracket</span>}
                <Link href={scheduleHref} {...current('schedule')}>Schedule</Link>
                <Link href={resultsHref} {...current('results')}>Results</Link>
            </nav>
        </section>
    );
}
