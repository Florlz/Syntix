import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const CSPC_LOGO = 'https://cspc.edu.ph/wp-content/uploads/2025/09/CSPCLogo.jpeg';
const POLL_INTERVAL_MS = 15_000;
const STALE_AFTER_MS = 45_000;
const CAROUSEL_INTERVAL_MS = 8_000;

function hasValue(value) {
    return value !== undefined && value !== null && value !== '';
}

function formatDate(value, fallback = 'No live board') {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
        : fallback;
}

function formatDateTime(value, fallback = 'Awaiting update') {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : fallback;
}

function formatProgrammeDate(value) {
    return value
        ? new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric' }).format(new Date(value))
        : 'Date to be announced';
}

function formatProgrammeTime(start, end) {
    if (!start) return 'Time to be announced';

    const formatter = new Intl.DateTimeFormat(undefined, { hour: 'numeric', minute: '2-digit' });

    return end ? `${formatter.format(new Date(start))}–${formatter.format(new Date(end))}` : formatter.format(new Date(start));
}

function programmeStatus(value) {
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : 'Scheduled';
}

function displayPoints(value) {
    if (typeof value !== 'string') {
        return value ?? '0';
    }

    return value
        .replace(/(\.\d*?[1-9])0+$/, '$1')
        .replace(/\.0+$/, '');
}

function contestPhase(live = {}) {
    return live.period ?? live.set ?? live.round ?? live.phase ?? live.status ?? 'Live activity';
}

function StaffLink({ authenticated }) {
    return (
        <Link
            href={authenticated ? route('dashboard') : route('login')}
            className="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#0B2E4F] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#164565] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F7F5EF]"
        >
            {authenticated ? 'Open Dashboard' : 'Staff Login'}
        </Link>
    );
}

function BrandBar({ authenticated, event }) {
    return (
        <header className="border-t-[3px] border-[#D5A21F]">
            <div className="flex items-center justify-between gap-3 border-b border-[#DDDCD6] py-5 sm:gap-5 sm:py-6">
                <div className="flex min-w-0 items-center gap-8">
                    <Link
                        href={route('landing')}
                        className="flex min-w-0 items-center gap-3 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-4 focus-visible:ring-offset-[#F7F5EF]"
                    >
                        <span className="grid size-11 shrink-0 place-items-center bg-[#0B2E4F] p-1.5 shadow-sm sm:size-12">
                            <img src="/icons/icon.svg" alt="SYNTIX" width="40" height="40" />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-extrabold tracking-[0.04em] text-[#17212B]">CSPC SIKLAB</span>
                            <span className="mt-0.5 block truncate text-xs text-[#68767E]">Public event desk</span>
                        </span>
                    </Link>

                    <nav aria-label="Public landing navigation" className="hidden items-center gap-6 text-sm font-semibold text-[#53636C] md:flex">
                        <a href="#live-score-stage" className="rounded py-2 transition-colors hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Live Scores</a>
                        <a href="#competitions" className="rounded py-2 transition-colors hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Competitions</a>
                        {event ? <Link href={route('public.scoreboard', event.slug)} className="rounded py-2 transition-colors hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Event Board</Link> : null}
                    </nav>
                </div>

                <nav aria-label="Staff access" className="shrink-0">
                    <StaffLink authenticated={authenticated} />
                </nav>
            </div>

            <nav aria-label="Public landing mobile navigation" className="flex flex-wrap gap-x-5 gap-y-1 border-b border-[#E7E5DF] py-2 text-sm font-semibold text-[#53636C] md:hidden">
                <a href="#live-score-stage" className="inline-flex min-h-11 items-center rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Live Scores</a>
                <a href="#competitions" className="inline-flex min-h-11 items-center rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Competitions</a>
                {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 items-center rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Event Board</Link> : null}
            </nav>
        </header>
    );
}

function LiveSignal({ label = 'Live now', tone = 'live', surface = 'dark' }) {
    const toneClass = tone === 'live'
        ? surface === 'dark' ? 'text-[#F5C64B]' : 'text-[#765500]'
        : tone === 'alert'
            ? surface === 'dark' ? 'text-[#FFB4AB]' : 'text-[#B42318]'
            : surface === 'dark' ? 'text-white/60' : 'text-[#53636C]';
    const dotClass = tone === 'live' ? 'bg-[#E9554F]' : tone === 'alert' ? 'bg-[#C9362B]' : 'bg-current opacity-50';

    return (
        <span className={`inline-flex items-center gap-2 text-xs font-semibold ${toneClass}`}>
            <span className={`relative size-2 rounded-full ${dotClass}`}>
                {tone === 'live' ? <span className="absolute inset-0 rounded-full bg-[#E9554F] opacity-70 motion-safe:animate-ping" /> : null}
            </span>
            {label}
        </span>
    );
}

function usePublicFreshness(snapshotAt) {
    const initialTimestamp = Date.parse(snapshotAt ?? '');
    const [lastSuccessfulAt, setLastSuccessfulAt] = useState(
        Number.isNaN(initialTimestamp) ? Date.now() : initialTimestamp,
    );
    const [status, setStatus] = useState('ready');
    const requestInFlight = useRef(false);

    useEffect(() => {
        const timestamp = Date.parse(snapshotAt ?? '');

        if (!Number.isNaN(timestamp)) {
            setLastSuccessfulAt(timestamp);
            setStatus('ready');
        }
    }, [snapshotAt]);

    useEffect(() => {
        let active = true;

        const refresh = () => {
            if (!active || document.visibilityState === 'hidden' || requestInFlight.current) {
                return;
            }

            requestInFlight.current = true;
            setStatus('refreshing');

            router.reload({
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (active) {
                        setLastSuccessfulAt(Date.now());
                        setStatus('ready');
                    }
                },
                onError: () => {
                    if (active) {
                        setStatus('disconnected');
                    }
                },
                onFinish: () => {
                    requestInFlight.current = false;
                },
            });
        };

        const interval = window.setInterval(refresh, POLL_INTERVAL_MS);
        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                refresh();
            }
        };
        const removeExceptionListener = router.on('exception', () => {
            if (active) {
                setStatus('disconnected');
            }
        });

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            active = false;
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', handleVisibilityChange);
            removeExceptionListener();
        };
    }, []);

    useEffect(() => {
        const interval = window.setInterval(() => {
            if (Date.now() - lastSuccessfulAt >= STALE_AFTER_MS) {
                setStatus((current) => current === 'refreshing' ? current : 'stale');
            }
        }, 1000);

        return () => window.clearInterval(interval);
    }, [lastSuccessfulAt]);

    return { lastSuccessfulAt, status };
}

function useReducedMotion() {
    const [reducedMotion, setReducedMotion] = useState(false);

    useEffect(() => {
        const media = window.matchMedia('(prefers-reduced-motion: reduce)');
        const handleChange = () => setReducedMotion(media.matches);

        handleChange();
        media.addEventListener('change', handleChange);

        return () => media.removeEventListener('change', handleChange);
    }, []);

    return reducedMotion;
}

function ScoreStatus({ status, lastSuccessfulAt }) {
    if (status === 'ready') {
        return null;
    }

    const alert = status === 'disconnected' || status === 'stale';
    const label = status === 'refreshing'
        ? 'Updating…'
        : status === 'disconnected'
            ? 'Connection lost'
            : 'Snapshot may be stale';

    return (
        <p aria-live="polite" className={`flex flex-wrap items-center gap-x-2 gap-y-1 text-xs ${alert ? 'text-[#FFC1BA]' : 'text-white/55'}`}>
            <span className={`size-1.5 rounded-full ${alert ? 'bg-[#E9554F]' : 'bg-white/45'}`} aria-hidden="true" />
            <span className="font-semibold">{label}</span>
            {alert ? <span className="text-white/45">Last update <time dateTime={new Date(lastSuccessfulAt).toISOString()}>{formatDateTime(lastSuccessfulAt)}</time></span> : null}
        </p>
    );
}

function ScoreSide({ label, score, align = 'left' }) {
    return (
        <div className={`min-w-0 ${align === 'right' ? 'text-right' : 'text-left'}`}>
            <p className="truncate text-sm font-semibold text-white/65 sm:text-base" title={label}>{label}</p>
            <p className="mt-3 font-mono text-[clamp(4rem,9vw,8rem)] font-bold leading-none tracking-[-0.08em] tabular-nums text-white">
                {hasValue(score) ? score : '—'}
            </p>
        </div>
    );
}

function ScoreSlide({ event, contest, status, lastSuccessfulAt }) {
    if (!contest) {
        return (
            <article id="live-score-stage" className="relative flex min-h-[20rem] scroll-mt-6 flex-col justify-between overflow-hidden rounded-[1.75rem] bg-[#0B2E4F] p-6 text-white shadow-[0_24px_60px_rgba(11,46,79,0.16)] sm:min-h-[23rem] sm:p-9">
                <div className="absolute -right-16 -top-20 size-64 rounded-full border-[3rem] border-white/[0.035]" aria-hidden="true" />
                <div className="relative max-w-xl">
                    <span className="inline-flex rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold text-white/70">Waiting for live activity</span>
                    <h2 className="mt-7 text-balance text-3xl font-bold leading-tight tracking-[-0.035em] sm:text-4xl">The next score will appear here.</h2>
                    <p className="mt-4 max-w-lg text-sm leading-6 text-white/60">This board updates automatically when a public SIKLAB contest goes live.</p>
                </div>
                <div className="relative mt-10 flex flex-col gap-4 border-t border-white/10 pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <span className="text-sm text-white/45">No score has been published yet</span>
                        <ScoreStatus status={status} lastSuccessfulAt={lastSuccessfulAt} />
                    </div>
                    {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-[#D5A21F] px-5 text-sm font-bold text-[#17212B] transition-colors hover:bg-[#F1C85F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]">Open Event Board</Link> : null}
                </div>
            </article>
        );
    }

    const home = contest.sides?.find((side) => side.position === 'home')?.label ?? 'Home';
    const away = contest.sides?.find((side) => side.position === 'away')?.label ?? 'Away';
    const live = contest.live ?? {};
    const hasScore = hasValue(live.home) || hasValue(live.away);
    const phase = contestPhase(live);

    return (
        <article id="live-score-stage" aria-labelledby="active-live-contest-title" className="relative flex min-h-[27rem] scroll-mt-6 flex-col overflow-hidden rounded-[1.75rem] bg-[#0B2E4F] text-white shadow-[0_24px_60px_rgba(11,46,79,0.18)] lg:min-h-[31rem]">
            <div className="absolute -right-24 -top-24 size-80 rounded-full border-[4rem] border-white/[0.035]" aria-hidden="true" />
            <div className="relative flex flex-wrap items-start justify-between gap-4 px-6 pb-4 pt-6 sm:px-8 sm:pt-8">
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-[#F1C85F]">{contest.competition} · {contest.division}</p>
                    <h2 id="active-live-contest-title" className="mt-1.5 truncate text-xl font-bold tracking-[-0.02em] sm:text-2xl" title={contest.name}>{contest.name}</h2>
                </div>
                <div className="flex shrink-0 items-center gap-2">
                    <span className="rounded-full bg-[#E9554F]/15 px-3 py-1.5 text-xs font-semibold text-[#FFC1BA]">Unofficial</span>
                    <LiveSignal label="Live" />
                </div>
            </div>

            <div className="relative flex flex-1 flex-col justify-center px-6 py-8 sm:px-9 lg:px-11">
                {hasScore ? (
                    <div className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3 sm:gap-7">
                        <ScoreSide label={home} score={live.home} />
                        <span className="font-mono text-3xl font-bold text-[#D5A21F] sm:text-5xl">:</span>
                        <ScoreSide label={away} score={live.away} align="right" />
                    </div>
                ) : (
                    <div className="rounded-2xl bg-white/[0.07] p-6 sm:p-8">
                        <p className="text-sm font-semibold text-[#F1C85F]">Live activity</p>
                        <p className="mt-3 text-balance text-3xl font-bold leading-tight tracking-[-0.035em] text-white sm:text-4xl">Performance in progress</p>
                        <p className="mt-5 font-mono text-sm font-semibold text-white/55">{phase}</p>
                    </div>
                )}
            </div>

            <div className="relative flex flex-col gap-4 border-t border-white/10 px-6 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div className="flex flex-col gap-2 text-xs">
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                        <span className="font-mono font-semibold text-[#F1C85F]">{phase}</span>
                        <span className="text-white/50">Updated <time dateTime={contest.updated_at}>{formatDateTime(contest.updated_at)}</time></span>
                    </div>
                    <ScoreStatus status={status} lastSuccessfulAt={lastSuccessfulAt} />
                </div>
                {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-[#D5A21F] px-5 text-sm font-bold text-[#17212B] transition-colors hover:bg-[#F1C85F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]">Open Event Board</Link> : null}
            </div>
        </article>
    );
}

function LiveScoreCarousel({ event, contests, status, lastSuccessfulAt }) {
    const reducedMotion = useReducedMotion();
    const [activeId, setActiveId] = useState(contests[0]?.id ?? null);
    const [rotationPaused, setRotationPaused] = useState(false);
    const [hovered, setHovered] = useState(false);
    const [documentHidden, setDocumentHidden] = useState(false);
    const [manualAnnouncement, setManualAnnouncement] = useState('');
    const contestSignature = contests.map((contest) => contest.id).join('|');
    const activeIndex = Math.max(0, contests.findIndex((contest) => contest.id === activeId));
    const activeContest = contests[activeIndex] ?? null;
    const canRotate = contests.length > 1;

    useEffect(() => {
        if (contests.length === 0) {
            setActiveId(null);
            return;
        }

        if (!contests.some((contest) => contest.id === activeId)) {
            setActiveId(contests[0].id);
        }
    }, [contestSignature, activeId]);

    useEffect(() => {
        if (reducedMotion) {
            setRotationPaused(true);
        }
    }, [reducedMotion]);

    useEffect(() => {
        const handleVisibilityChange = () => setDocumentHidden(document.visibilityState === 'hidden');

        handleVisibilityChange();
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
    }, []);

    useEffect(() => {
        if (!canRotate || rotationPaused || hovered || documentHidden || reducedMotion) {
            return undefined;
        }

        const timeout = window.setTimeout(() => {
            setActiveId((currentId) => {
                const currentIndex = Math.max(0, contests.findIndex((contest) => contest.id === currentId));
                return contests[(currentIndex + 1) % contests.length].id;
            });
        }, CAROUSEL_INTERVAL_MS);

        return () => window.clearTimeout(timeout);
    }, [contestSignature, activeId, rotationPaused, hovered, documentHidden, reducedMotion]);

    const announceContest = (contest) => {
        if (contest) {
            setManualAnnouncement(`Showing ${contest.competition} ${contest.division}: ${contest.name}.`);
        }
    };

    const selectContest = (index) => {
        const selected = contests[index];

        if (!selected) {
            return;
        }

        setActiveId(selected.id);
        setRotationPaused(true);
        announceContest(selected);
    };

    const move = (offset) => {
        if (!canRotate) {
            return;
        }

        const nextIndex = (activeIndex + offset + contests.length) % contests.length;
        selectContest(nextIndex);
    };

    return (
        <section
            role="region"
            aria-roledescription="carousel"
            aria-label="Live Contest scores"
            className="flex h-full min-w-0 flex-col"
            onMouseEnter={() => setHovered(true)}
            onMouseLeave={() => setHovered(false)}
            onFocusCapture={() => setRotationPaused(true)}
        >
            <div aria-live="off" className="min-w-0 flex-1">
                <ScoreSlide event={event} contest={activeContest} status={status} lastSuccessfulAt={lastSuccessfulAt} />
            </div>

            {canRotate ? (
                <div className="mt-3 rounded-2xl border border-[#DDDCD6] bg-white p-3 shadow-sm sm:p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={() => move(-1)} className="inline-flex min-h-11 items-center justify-center rounded-full border border-[#D5DBDE] px-4 text-xs font-semibold text-[#30414B] transition-colors hover:border-[#0B2E4F] hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Previous</button>
                            <button
                                type="button"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => setRotationPaused((paused) => !paused)}
                                disabled={reducedMotion}
                                className="inline-flex min-h-11 items-center justify-center rounded-full border border-[#D5DBDE] px-4 text-xs font-semibold text-[#30414B] transition-colors hover:border-[#0B2E4F] hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] disabled:cursor-not-allowed disabled:text-[#9AA5AA]"
                            >
                                {reducedMotion ? 'Rotation off' : rotationPaused ? 'Start rotation' : 'Pause rotation'}
                            </button>
                            <button type="button" onClick={() => move(1)} className="inline-flex min-h-11 items-center justify-center rounded-full border border-[#D5DBDE] px-4 text-xs font-semibold text-[#30414B] transition-colors hover:border-[#0B2E4F] hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Next</button>
                        </div>
                        <p className="font-mono text-xs font-semibold tabular-nums text-[#7A878D]">Contest {activeIndex + 1} of {contests.length}</p>
                    </div>

                    <div aria-label="Choose a live Contest" className="mt-3 flex gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
                        {contests.map((contest, index) => {
                            const active = contest.id === activeContest?.id;

                            return (
                                <button
                                    key={contest.id}
                                    type="button"
                                    aria-pressed={active}
                                    aria-controls="live-score-stage"
                                    onClick={() => selectContest(index)}
                                    className={`min-h-11 min-w-36 shrink-0 rounded-xl border px-3 py-2 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] ${active ? 'border-[#0B2E4F] bg-[#0B2E4F] text-white' : 'border-[#E0E4E5] bg-[#F8F8F6] text-[#30414B] hover:border-[#AAB5BA]'}`}
                                >
                                    <span className="block truncate text-xs font-bold">{contest.competition}</span>
                                    <span className={`mt-1 block truncate text-xs ${active ? 'text-white/60' : 'text-[#7A878D]'}`}>{contest.division}{index === 0 ? ' · Latest' : ''}</span>
                                </button>
                            );
                        })}
                    </div>
                </div>
            ) : null}

            <p className="sr-only" aria-live="polite">{manualAnnouncement}</p>
        </section>
    );
}

function ChampionshipStandingsStack({ event, leaderboard }) {
    const leadingTotal = leaderboard.length ? Number(leaderboard[0].total) : null;
    const hasLeadingTotal = leadingTotal !== null && leadingTotal !== 0;

    return (
        <section aria-labelledby="championship-standings-title" className="flex h-full min-h-0 flex-col rounded-[1.75rem] border border-[#DDDCD6] bg-white p-5 text-[#17212B] shadow-[0_18px_45px_rgba(23,33,43,0.06)] sm:p-6">
            <div className="border-b border-[#E8E7E1] pb-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-semibold text-[#9B741A]">Approved points</p>
                        <h2 id="championship-standings-title" className="mt-1 text-2xl font-bold tracking-[-0.03em]">Department standings</h2>
                    </div>
                    <span className="shrink-0 rounded-full bg-[#F1F0EB] px-3 py-1 font-mono text-xs font-semibold tabular-nums text-[#68767E]">{leaderboard.length}</span>
                </div>
                <p className="mt-3 text-sm leading-6 text-[#68767E]">All departments, ordered by approved championship totals. Equal totals receive equal emphasis.</p>
            </div>

            {leaderboard.length ? (
                <ul className="mt-2 flex flex-col lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pr-1 [scrollbar-color:#C9A548_transparent] [scrollbar-width:thin]">
                    {leaderboard.map((delegation) => {
                        const leading = hasLeadingTotal && Number(delegation.total) === leadingTotal;

                        return (
                            <li key={delegation.id} className={`flex items-center gap-3 rounded-xl px-2 py-3 ${leading ? 'bg-[#FBF4DF]' : 'border-b border-[#EEEDE8] last:border-b-0'}`}>
                                <span className={`grid size-10 shrink-0 place-items-center rounded-full font-mono text-xs font-bold uppercase ${leading ? 'bg-[#D5A21F] text-[#17212B]' : 'bg-[#EEF1F2] text-[#53636C]'}`} aria-hidden="true">
                                    {delegation.abbreviation?.slice(0, 4) || delegation.name.slice(0, 2)}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold" title={delegation.name}>{delegation.name}</p>
                                    <p className={`mt-0.5 text-xs ${leading ? 'text-[#8A681B]' : 'text-[#8A959A]'}`}>{leading ? 'Leading total' : delegation.abbreviation || 'Department'}</p>
                                </div>
                                <p className="shrink-0 font-mono text-xl font-bold tabular-nums text-[#0B2E4F]">
                                    {displayPoints(delegation.total)}<span className="ml-1 text-xs font-semibold text-[#7A878D]">pts</span>
                                </p>
                            </li>
                        );
                    })}
                </ul>
            ) : (
                <div className="mt-5 rounded-2xl bg-[#F4F5F2] p-5">
                    <p className="font-semibold">No department totals yet</p>
                    <p className="mt-2 text-sm leading-6 text-[#758188]">Approved championship points will appear here once they are published.</p>
                </div>
            )}

            {event ? <Link href={route('public.scoreboard', event.slug)} className="mt-5 inline-flex min-h-11 items-center justify-center rounded-full border border-[#CDD5D8] px-4 text-sm font-semibold text-[#0B2E4F] transition-colors hover:border-[#0B2E4F] hover:bg-[#F4F6F6] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">View All Standings</Link> : null}
        </section>
    );
}

function BroadcastHero({ authenticated, event, contests, leaderboard, status, lastSuccessfulAt }) {
    return (
        <section className="bg-[#F7F5EF]">
            <div className="mx-auto max-w-7xl px-5 sm:px-8">
                <BrandBar authenticated={authenticated} event={event} />

                <div className="flex flex-col gap-5 pb-8 pt-7 sm:pb-10 sm:pt-10 lg:flex-row lg:items-end lg:justify-between">
                    <div className="max-w-3xl">
                        <p className="text-sm font-semibold text-[#9B741A]">CSPC SIKLAB</p>
                        <h1 className="mt-3 text-balance text-4xl font-bold leading-[1.02] tracking-[-0.045em] text-[#17212B] sm:text-5xl lg:text-[3.5rem]">{event?.name ?? 'SIKLAB scores and standings.'}</h1>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-[#68767E]">{event ? `${formatDate(event.starts_at)} · Follow live contests and approved championship points in one place.` : 'When the next event begins, live scores and department totals will update here automatically.'}</p>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-3 lg:justify-end">
                        <span className="rounded-full border border-[#DDDCD6] bg-white px-4 py-2 text-sm text-[#68767E]">{contests.length ? `${contests.length} live contest${contests.length === 1 ? '' : 's'}` : 'No live contests'}</span>
                        {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 items-center justify-center rounded-full bg-[#0B2E4F] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#164565] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F7F5EF]">View Event Board</Link> : null}
                    </div>
                </div>

                <div className="grid gap-5 pb-10 lg:grid-cols-[minmax(0,1.65fr)_minmax(20rem,0.85fr)] lg:items-stretch lg:pb-14">
                    <div className="min-w-0">
                        <LiveScoreCarousel event={event} contests={contests} status={status} lastSuccessfulAt={lastSuccessfulAt} />
                    </div>
                    <div className="min-w-0">
                        <ChampionshipStandingsStack event={event} leaderboard={leaderboard} />
                    </div>
                </div>
            </div>
        </section>
    );
}

function CompetitionArtwork({ competition, prominent = false }) {
    const [failed, setFailed] = useState(false);
    const showPhoto = competition.cover?.url && !failed;

    return (
        <div className={`relative overflow-hidden bg-[#0B2E4F] ${prominent ? 'aspect-[16/9] sm:aspect-[16/8]' : 'aspect-[16/10]'}`}>
            {showPhoto ? (
                <img
                    src={competition.cover.url}
                    alt={competition.cover.alt}
                    width={competition.cover.width}
                    height={competition.cover.height}
                    loading="lazy"
                    className="h-full w-full object-cover transition duration-500 motion-safe:group-hover:scale-[1.02]"
                    onError={() => setFailed(true)}
                />
            ) : (
                <div className="absolute inset-0 overflow-hidden" aria-hidden="true">
                    <span className="absolute -right-[15%] -top-[30%] size-[80%] rounded-full border-[2px] border-white/10" />
                    <span className="absolute -bottom-[45%] -left-[12%] size-[90%] rounded-full border-[2px] border-[#D5A21F]/35" />
                    <span className="absolute inset-x-0 top-1/2 border-t border-white/10" />
                    <span className="absolute left-1/2 inset-y-0 border-l border-white/10" />
                </div>
            )}

            <div className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-[#071F33]/95 via-[#071F33]/55 to-transparent px-5 pb-5 pt-20 text-white sm:px-6 sm:pb-6">
                {!showPhoto ? <p className="mb-2 text-xs font-semibold text-[#E4BD54]">CSPC SIKLAB</p> : null}
                <h3 className={`font-bold tracking-[-0.035em] ${prominent ? 'text-3xl sm:text-4xl' : 'text-2xl sm:text-3xl'}`}>{competition.name}</h3>
            </div>
        </div>
    );
}

function PublishedSchedule({ schedule }) {
    return (
        <li className="grid gap-3 border-t border-[#D9DDDC] py-4 sm:grid-cols-[8.5rem_minmax(0,1fr)] sm:gap-5">
            <div>
                <time dateTime={schedule.starts_at} className="block text-sm font-bold text-[#0B2E4F]">{formatProgrammeDate(schedule.starts_at)}</time>
                <span className="mt-1 block font-mono text-xs tabular-nums text-[#68767E]">{formatProgrammeTime(schedule.starts_at, schedule.ends_at)}</span>
            </div>
            <div className="min-w-0">
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <p className="font-semibold text-[#17212B]">{schedule.title}</p>
                    {schedule.status !== 'scheduled' ? <span className="text-xs font-semibold text-[#9A6112]">{programmeStatus(schedule.status)}</span> : null}
                </div>
                <p className="mt-1 text-sm leading-6 text-[#637178]">
                    {schedule.division}
                    {schedule.venue ? <> · <span className="font-medium text-[#3F5059]">{schedule.venue.name}</span>{schedule.venue.location ? `, ${schedule.venue.location}` : ''}</> : ' · Venue to be announced'}
                </p>
            </div>
        </li>
    );
}

function CompetitionDirectory({ event, competitions }) {
    return (
        <section id="competitions" aria-labelledby="competition-directory-title" className="scroll-mt-6 bg-[#F5F4EF]">
            <div className="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
                <div className="flex flex-col gap-6 border-b border-[#C9CECD] pb-8 md:flex-row md:items-end md:justify-between">
                    <div className="max-w-3xl">
                        <p className="text-sm font-semibold text-[#9B741A]">Sports programme</p>
                        <h2 id="competition-directory-title" className="mt-2 text-balance text-3xl font-bold tracking-[-0.04em] text-[#17212B] sm:text-5xl">See what’s playing, when, and where.</h2>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-[#68767E]">Published schedules and CSPC event photos, organized by competition. Changes appear only after the event desk republishes them.</p>
                    </div>
                    {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-[#0B2E4F] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#164565] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F5F4EF]">Open Complete Event Board</Link> : null}
                </div>

                {competitions.length ? (
                    <div className="mt-10 grid gap-x-7 gap-y-12 lg:grid-cols-2 lg:gap-y-16">
                        {competitions.map((competition, index) => (
                            <article key={competition.id} className={`group min-w-0 ${index === 0 && competitions.length > 2 ? 'lg:col-span-2' : ''}`}>
                                <CompetitionArtwork competition={competition} prominent={index === 0 && competitions.length > 2} />
                                <div className={`bg-white px-5 pb-5 pt-1 shadow-[0_16px_40px_rgba(23,33,43,0.07)] sm:px-6 sm:pb-6 ${index === 0 && competitions.length > 2 ? 'lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(17rem,0.6fr)] lg:gap-10' : ''}`}>
                                    <div className="min-w-0">
                                        {competition.schedules.length ? (
                                            <ul>{competition.schedules.map((schedule) => <PublishedSchedule key={schedule.id} schedule={schedule} />)}</ul>
                                        ) : (
                                            <div className="border-t border-[#D9DDDC] py-5">
                                                <p className="font-semibold text-[#17212B]">Schedule not published</p>
                                                <p className="mt-1 text-sm leading-6 text-[#68767E]">The event desk has not released a public time and venue for this competition.</p>
                                            </div>
                                        )}
                                    </div>

                                    <div className="border-t border-[#D9DDDC] py-4 lg:self-start">
                                        <p className="text-xs font-semibold uppercase tracking-[0.13em] text-[#7A878D]">Divisions and brackets</p>
                                        <ul className="mt-2 flex flex-wrap gap-x-5 gap-y-1">
                                            {competition.divisions.map((division) => (
                                                <li key={division.id} className="inline-flex min-h-10 items-center gap-2 text-sm">
                                                    <span className="text-[#53636C]">{division.name}</span>
                                                    {division.has_published_bracket && event ? <Link href={route('public.bracket', [event.slug, division.id])} className="font-semibold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4 hover:text-[#0B2E4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0B536D]">Bracket</Link> : null}
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                </div>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="mt-10 border-l-4 border-[#D5A21F] bg-white px-6 py-8 shadow-[0_14px_35px_rgba(23,33,43,0.05)]">
                        <h3 className="text-xl font-bold text-[#17212B]">The sports programme is not published yet.</h3>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-[#68767E]">Competition photos, schedules, and venue details will appear here when the SIKLAB event desk releases them.</p>
                    </div>
                )}
            </div>
        </section>
    );
}

function Footer({ authenticated, event }) {
    return (
        <footer className="border-t-[3px] border-[#D5A21F] bg-[#071F33] text-white">
            <div className="mx-auto max-w-7xl px-5 py-10 sm:px-8 sm:py-12">
                <div className="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.6fr_0.8fr_0.8fr] lg:gap-14">
                    <div className="max-w-md">
                        <div className="flex items-center gap-4">
                            <img src={CSPC_LOGO} alt="Camarines Sur Polytechnic Colleges seal" width="52" height="52" loading="lazy" className="h-[3.25rem] w-[3.25rem] rounded-full bg-white object-cover" onError={(error) => { error.currentTarget.onerror = null; error.currentTarget.src = '/icons/icon.svg'; }} />
                            <div>
                                <p className="text-lg font-bold tracking-[-0.02em]">CSPC SIKLAB</p>
                                <p className="mt-0.5 text-sm text-white/50">Public event desk by SYNTIX</p>
                            </div>
                        </div>
                        <p className="mt-5 text-sm leading-6 text-white/55">Live scores, published brackets, and approved championship totals for the CSPC intramurals community.</p>
                    </div>

                    <nav aria-label="Public resources">
                        <h2 className="text-xs font-semibold uppercase tracking-[0.14em] text-[#E4BD54]">Public Boards</h2>
                        <ul className="mt-4 space-y-3 text-sm text-white/65">
                            <li><a href="#live-score-stage" className="inline-flex min-h-11 items-center rounded transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5C64B]">Live Scores</a></li>
                            <li><a href="#competitions" className="inline-flex min-h-11 items-center rounded transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5C64B]">Competitions</a></li>
                            {event ? <li><Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 items-center rounded transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5C64B]">Event Board</Link></li> : null}
                        </ul>
                    </nav>

                    <nav aria-label="Institutional resources">
                        <h2 className="text-xs font-semibold uppercase tracking-[0.14em] text-[#E4BD54]">Institution</h2>
                        <ul className="mt-4 space-y-3 text-sm text-white/65">
                            <li><a href="https://cspc.edu.ph" target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center rounded transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5C64B]">CSPC Website</a></li>
                            <li><Link href={authenticated ? route('dashboard') : route('login')} className="inline-flex min-h-11 items-center rounded transition-colors hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F5C64B]">{authenticated ? 'Staff Dashboard' : 'Staff Login'}</Link></li>
                        </ul>
                    </nav>
                </div>

                <div className="mt-10 flex flex-col gap-2 border-t border-white/10 pt-5 text-xs text-white/60 sm:flex-row sm:items-center sm:justify-between">
                    <span>Nabua, Camarines Sur</span>
                    <span>Public access is read-only · Staff access is invite-only</span>
                </div>
            </div>
        </footer>
    );
}

export default function Welcome({ auth, featured_event: event, featured_contest: featuredContest, live_contests: liveContests = [], competitions = [], leaderboard = [], snapshot_at: snapshotAt }) {
    const authenticated = Boolean(auth?.user);
    const { lastSuccessfulAt, status } = usePublicFreshness(snapshotAt);
    const contests = featuredContest ? [featuredContest, ...liveContests] : liveContests;

    return (
        <>
            <Head title={event ? `${event.name} Live` : 'CSPC SIKLAB'}>
                <meta name="theme-color" content="#F7F5EF" />
            </Head>
            <a href="#main-content" className="sr-only z-50 bg-[#F5C64B] px-4 py-3 font-bold text-[#17212B] focus:not-sr-only focus:fixed focus:left-4 focus:top-0 focus:outline-none focus:ring-2 focus:ring-[#17212B]">Skip to event content</a>
            <main id="main-content" className="min-h-screen overflow-x-hidden bg-[#F7F5EF] font-sans text-[#17212B]">
                <BroadcastHero authenticated={authenticated} event={event} contests={contests} leaderboard={leaderboard} status={status} lastSuccessfulAt={lastSuccessfulAt} />
                <CompetitionDirectory event={event} competitions={competitions} />
                <Footer authenticated={authenticated} event={event} />
            </main>
        </>
    );
}
