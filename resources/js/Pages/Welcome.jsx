import { Head, Link } from '@inertiajs/react';

const CSPC_LOGO = 'https://cspc.edu.ph/wp-content/uploads/2025/09/CSPCLogo.jpeg';
const CSPC_FACILITIES = 'https://cspc.edu.ph/wp-content/uploads/2024/12/Facilities-1280x580.jpg';

function formatDate(value) {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
        : 'No live board';
}

function formatTime(value) {
    return value
        ? new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' }).format(new Date(value))
        : 'Awaiting update';
}

function StaffLink({ authenticated }) {
    return (
        <Link
            href={authenticated ? route('dashboard') : route('login')}
            className="inline-flex min-h-11 items-center justify-center rounded-full border border-white/30 px-4 text-sm font-bold text-white transition hover:border-white hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#f5c64b] focus-visible:ring-offset-2 focus-visible:ring-offset-[#061e35]"
        >
            {authenticated ? 'Open Dashboard' : 'Staff Login'}
        </Link>
    );
}

function BrandBar({ authenticated }) {
    return (
        <header className="border-b border-white/15 py-5">
            <div className="flex items-center justify-between gap-5">
                <Link href={route('landing')} className="flex min-w-0 items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#f5c64b]">
                    <img src={CSPC_LOGO} alt="Camarines Sur Polytechnic Colleges seal" width="48" height="48" className="size-12 rounded-full border-2 border-white/80 bg-white object-cover p-0.5" />
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-black uppercase tracking-[0.16em] text-white">CSPC SIKLAB</span>
                        <span className="mt-1 block truncate text-[10px] font-bold uppercase tracking-[0.2em] text-white/50">Public broadcast by SYNTIX</span>
                    </span>
                </Link>
                <nav aria-label="Staff access" className="flex shrink-0 items-center gap-4">
                    <span className="hidden text-[10px] font-bold uppercase tracking-[0.16em] text-white/45 sm:inline">Official public board</span>
                    <StaffLink authenticated={authenticated} />
                </nav>
            </div>
        </header>
    );
}

function LiveBadge({ standby = false }) {
    return (
        <span className={`inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.18em] ${standby ? 'text-white/50' : 'text-[#f5c64b]'}`}>
            <span className={`relative size-2 rounded-full ${standby ? 'bg-white/30' : 'bg-[#e9554f]'}`}>
                {standby ? null : <span className="motion-safe:animate-ping absolute inset-0 rounded-full bg-[#e9554f] opacity-75" />}
            </span>
            {standby ? 'Standby' : 'Live now'}
        </span>
    );
}

function ScoreboardWall({ event, contest }) {
    const home = contest?.sides?.find((side) => side.position === 'home')?.label ?? 'Home';
    const away = contest?.sides?.find((side) => side.position === 'away')?.label ?? 'Away';
    const homeScore = contest?.live?.home ?? '-';
    const awayScore = contest?.live?.away ?? '-';
    const phase = contest?.live?.period ?? contest?.live?.set ?? contest?.live?.round ?? contest?.live?.phase ?? 'Live play';

    return (
        <section className="overflow-hidden border border-white/25 bg-[#061e35]/90 shadow-2xl shadow-black/30 backdrop-blur-md">
            <div className="flex items-center justify-between gap-4 border-b border-white/15 px-5 py-4 sm:px-7">
                <div>
                    <p className="text-[10px] font-black uppercase tracking-[0.2em] text-white/45">CSPC SIKLAB</p>
                    <p className="mt-1 text-sm font-bold uppercase tracking-[0.1em] text-white">Scoreboard wall</p>
                </div>
                <LiveBadge standby={!contest} />
            </div>
            {contest ? (
                <>
                    <div className="px-5 pt-6 text-center sm:px-7">
                        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#f5c64b]">{contest.competition} · {contest.division}</p>
                        <h2 className="mt-2 text-lg font-bold text-white sm:text-xl">{contest.name}</h2>
                    </div>
                    <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 px-5 py-8 text-center sm:gap-7 sm:px-7 sm:py-10">
                        <div className="min-w-0"><p className="truncate text-xs font-black uppercase tracking-[0.12em] text-white/60 sm:text-sm">{home}</p><p className="mt-3 text-6xl font-black leading-none tabular-nums text-white sm:text-8xl">{homeScore}</p></div>
                        <div className="text-2xl font-black text-[#f5c64b]">:</div>
                        <div className="min-w-0"><p className="truncate text-xs font-black uppercase tracking-[0.12em] text-white/60 sm:text-sm">{away}</p><p className="mt-3 text-6xl font-black leading-none tabular-nums text-white sm:text-8xl">{awayScore}</p></div>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-white/15 px-5 py-4 text-xs sm:px-7"><span className="font-bold uppercase tracking-[0.12em] text-[#f5c64b]">{phase}</span><span className="tabular-nums text-white/45">Revision {contest.revision} · {formatTime(contest.updated_at)}</span></div>
                </>
            ) : (
                <div className="px-5 py-10 text-center sm:px-7 sm:py-14">
                    <p className="text-xs font-black uppercase tracking-[0.18em] text-white/45">No match on air</p>
                    <p className="mt-4 text-5xl font-black tracking-tight tabular-nums text-white/90 sm:text-7xl">-- : --</p>
                    <p className="mx-auto mt-5 max-w-xs text-sm leading-6 text-white/55">The next live contest will appear here. The board remains ready for official event coverage.</p>
                </div>
            )}
            <div className="flex flex-col gap-3 border-t border-white/15 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-7"><span className="text-xs text-white/50">Live scores are unofficial until Admin approval.</span>{event ? <Link href={route('public.scoreboard', event.slug)} className="text-xs font-black uppercase tracking-[0.13em] text-[#f5c64b] underline decoration-[#f5c64b] underline-offset-4 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#f5c64b]">Open full board</Link> : null}</div>
        </section>
    );
}

function ContestCard({ contest }) {
    const home = contest.sides?.find((side) => side.position === 'home')?.label ?? 'Home';
    const away = contest.sides?.find((side) => side.position === 'away')?.label ?? 'Away';
    const hasScore = contest.live?.home !== undefined || contest.live?.away !== undefined;

    return <article className="min-w-[18rem] border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-[#e1aa28] hover:shadow-md"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-[10px] font-black uppercase tracking-[0.15em] text-[#0b536d]">{contest.competition} · {contest.division}</p><h3 className="mt-2 truncate text-base font-black text-[#061e35]">{contest.name}</h3></div><LiveBadge /></div>{hasScore ? <div className="mt-7 grid grid-cols-[1fr_auto_1fr] items-end gap-2"><div className="min-w-0"><p className="truncate text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{home}</p><p className="mt-2 text-4xl font-black leading-none tabular-nums text-[#061e35]">{contest.live.home ?? '-'}</p></div><span className="pb-1 text-lg font-black text-slate-300">:</span><div className="min-w-0 text-right"><p className="truncate text-xs font-bold uppercase tracking-[0.08em] text-slate-500">{away}</p><p className="mt-2 text-4xl font-black leading-none tabular-nums text-[#061e35]">{contest.live.away ?? '-'}</p></div></div> : <p className="mt-7 text-xl font-black text-[#061e35]">Live activity</p>}<div className="mt-6 border-t border-slate-100 pt-4 text-xs text-slate-500">Revision {contest.revision} · Updated {formatTime(contest.updated_at)}</div></article>;
}

function CompetitionDirectory({ event, competitions }) {
    return <div className="grid gap-px overflow-hidden border border-slate-200 bg-slate-200 md:grid-cols-2">{competitions.length ? competitions.map((competition) => <section key={competition.id} className="bg-white p-5 sm:p-6"><p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#0b536d]">Competition board</p><h3 className="mt-2 text-xl font-black text-[#061e35]">{competition.name}</h3><div className="mt-5 space-y-3">{competition.divisions.map((division) => <div key={division.id} className="flex items-center justify-between gap-3 border-t border-slate-100 pt-3"><span className="truncate text-sm font-semibold text-slate-700">{division.name}</span>{division.has_published_bracket ? <Link href={route('public.bracket', [event.slug, division.id])} className="shrink-0 text-xs font-black uppercase tracking-[0.12em] text-[#0b536d] underline decoration-[#e1aa28] decoration-2 underline-offset-4 hover:text-[#061e35] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0b536d]">Bracket</Link> : <span className="shrink-0 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Pending</span>}</div>)}</div></section>) : <div className="bg-white p-7 text-sm leading-6 text-slate-600">Competition boards will appear here when the next SIKLAB event is published.</div>}</div>;
}

function Footer({ event }) {
    return <footer className="bg-[#061e35] text-white"><div className="mx-auto max-w-7xl px-5 py-9 sm:px-8"><div className="flex flex-col gap-8 sm:flex-row sm:items-start sm:justify-between"><div className="flex items-center gap-3"><img src={CSPC_LOGO} alt="Camarines Sur Polytechnic Colleges seal" width="36" height="36" loading="lazy" className="size-9 rounded-full bg-white object-cover" /><div><p className="font-bold">CSPC SIKLAB · SYNTIX</p><p className="mt-1 text-sm text-white/55">Official public event broadcast.</p></div></div><nav aria-label="Public resources" className="flex flex-wrap gap-x-6 gap-y-3 text-sm font-semibold text-white/65"><a href="https://cspc.edu.ph" target="_blank" rel="noreferrer" className="hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#f5c64b]">CSPC Website</a>{event ? <Link href={route('public.scoreboard', event.slug)} className="hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#f5c64b]">Event Board</Link> : null}<span className="text-white/40">Staff access is invite-only</span></nav></div><div className="mt-8 flex flex-col gap-2 border-t border-white/10 pt-4 text-[10px] font-bold uppercase tracking-[0.15em] text-white/35 sm:flex-row sm:items-center sm:justify-between"><span>Public scores · published brackets · official standings</span><span>Nabua, Camarines Sur</span></div></div></footer>;
}

export default function Welcome({ auth, featured_event: event, featured_contest: featuredContest, live_contests: liveContests = [], competitions = [], updated_at: updatedAt }) {
    const authenticated = auth.user !== null;
    const totalLive = featuredContest ? liveContests.length + 1 : 0;

    return <><Head title={event ? `${event.name} Live` : 'CSPC SIKLAB'} /><a href="#main-content" className="sr-only z-50 rounded-b-lg bg-[#f5c64b] px-4 py-3 font-bold text-[#061e35] focus:not-sr-only focus:fixed focus:left-4 focus:top-0 focus:outline-none focus:ring-2 focus:ring-[#061e35]">Skip to event content</a><main id="main-content" className="min-h-screen overflow-x-hidden bg-[#edf1f3] text-[#061e35]"><section className="relative isolate overflow-hidden bg-[#061e35] text-white"><img src={CSPC_FACILITIES} alt="" aria-hidden="true" width="1280" height="580" fetchPriority="high" className="absolute inset-0 z-0 h-full w-full object-cover opacity-55" onError={(error) => { error.currentTarget.style.display = 'none'; }} /><div className="absolute inset-0 z-0 bg-[linear-gradient(105deg,rgba(6,30,53,0.98)_0%,rgba(6,30,53,0.9)_40%,rgba(6,30,53,0.62)_100%)]" /><div className="relative z-10 mx-auto max-w-7xl px-5 sm:px-8"><BrandBar authenticated={authenticated} /><div className="grid gap-8 py-10 sm:py-14 lg:grid-cols-[0.72fr_1.28fr] lg:items-center lg:py-20"><div><p className="text-xs font-black uppercase tracking-[0.2em] text-[#f5c64b]">{event ? 'CSPC SIKLAB live broadcast' : 'CSPC SIKLAB public board'}</p><h1 className="mt-5 max-w-xl text-balance text-4xl font-black leading-[0.94] tracking-[-0.045em] sm:text-6xl">{event ? event.name : 'The event board is ready.'}</h1><p className="mt-6 max-w-md text-base leading-7 text-white/70">{event ? `${formatDate(event.starts_at)} · Follow live competition across the CSPC campus.` : 'Official live scores, published brackets, and championship standings will appear here when the next event opens.'}</p><div className="mt-8 flex flex-wrap items-center gap-4"><span className="text-xs font-bold uppercase tracking-[0.14em] text-white/50">{event ? `${totalLive} live match${totalLive === 1 ? '' : 'es'}` : 'Standby coverage'}</span>{event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 items-center rounded-full bg-[#f5c64b] px-5 text-sm font-black text-[#061e35] transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#061e35]">Open event board</Link> : null}</div></div><ScoreboardWall event={event} contest={featuredContest} /></div></div></section><section className="border-b border-slate-200 bg-white"><div className="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-4 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-8"><p><strong>Broadcast status:</strong> public scores remain unofficial until Admin approval.</p><p className="tabular-nums text-xs text-slate-500">Board update {formatTime(updatedAt)}</p></div></section><section className="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16">{liveContests.length ? <><div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p className="text-xs font-black uppercase tracking-[0.18em] text-[#0b536d]">More happening now</p><h2 className="mt-2 text-3xl font-black tracking-tight">Concurrent live matches.</h2></div><span className="text-sm font-semibold text-slate-500">{liveContests.length} additional board{liveContests.length === 1 ? '' : 's'}</span></div><div className="mt-7 flex gap-4 overflow-x-auto pb-4 [scrollbar-width:thin]">{liveContests.map((contest) => <ContestCard key={contest.id} contest={contest} />)}</div></> : <div className="flex flex-col gap-4 border-l-4 border-[#e1aa28] bg-white p-6 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-xs font-black uppercase tracking-[0.16em] text-[#0b536d]">Broadcast desk</p><p className="mt-2 font-bold">{event ? 'No other matches are on air.' : 'No live event is published.'}</p><p className="mt-1 text-sm text-slate-500">The scoreboard wall will update when official live activity begins.</p></div>{event ? <Link href={route('public.scoreboard', event.slug)} className="text-sm font-black text-[#0b536d] underline decoration-[#e1aa28] underline-offset-4">View event board</Link> : null}</div>}</section><section className="border-t border-slate-200 bg-[#dce5ea]"><div className="mx-auto max-w-7xl px-5 py-12 sm:px-8 sm:py-16"><p className="text-xs font-black uppercase tracking-[0.18em] text-[#0b536d]">Today at SIKLAB</p><h2 className="mt-2 text-3xl font-black tracking-tight">Competition boards.</h2><p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600">Published brackets are available after staff release official tournament topology. Public boards use delegation labels, never participant names.</p><div className="mt-8"><CompetitionDirectory event={event} competitions={competitions} /></div></div></section><Footer event={event} /></main></>;
}
