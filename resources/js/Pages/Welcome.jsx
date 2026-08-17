import Link from '@/Components/PrefetchLink';
import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const CSPC_LOGO = 'https://cspc.edu.ph/wp-content/uploads/2025/09/CSPCLogo.jpeg';
const POLL_INTERVAL_MS = 15_000;
const PUBLIC_POLL_PROPS = [
    'featured_event',
    'featured_contest',
    'live_contests',
    'competitions',
    'leaderboard',
    'snapshot_at',
    'updated_at',
];
const STALE_AFTER_MS = 45_000;
const CAROUSEL_INTERVAL_MS = 8_000;

const DEPARTMENT_COLORS = {
    'fuchsia pink': { strong: '#C0267D', tint: '#FCE7F3', text: '#FFFFFF' },
    red: { strong: '#C9362B', tint: '#FDE9E7', text: '#FFFFFF' },
    yellow: { strong: '#BD8B00', tint: '#FFF6D5', text: '#17212B' },
    purple: { strong: '#7C3AED', tint: '#F2EAFF', text: '#FFFFFF' },
    gray: { strong: '#687078', tint: '#EEF1F2', text: '#FFFFFF' },
    grey: { strong: '#687078', tint: '#EEF1F2', text: '#FFFFFF' },
    blue: { strong: '#1D4ED8', tint: '#E8F0FF', text: '#FFFFFF' },
    green: { strong: '#16845B', tint: '#E7F5EE', text: '#FFFFFF' },
};

const DEFAULT_DEPARTMENT_COLOR = { strong: '#687078', tint: '#EEF1F2', text: '#FFFFFF' };

function hasValue(value) {
    return value !== undefined && value !== null && value !== '';
}

function formatDate(value, fallback = 'No live board') {
    return value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(value))
        : fallback;
}

function formatEventDate(value, fallback = 'Date to be announced') {
    return value
        ? new Intl.DateTimeFormat(undefined, { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)).toUpperCase()
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

function departmentColor(value) {
    return DEPARTMENT_COLORS[String(value ?? '').trim().toLocaleLowerCase()] ?? DEFAULT_DEPARTMENT_COLOR;
}

function sharedStandingRank(leaderboard, index) {
    const total = Number(leaderboard[index]?.total ?? 0);
    const firstMatchingIndex = leaderboard.findIndex((delegation) => Number(delegation.total ?? 0) === total);

    return firstMatchingIndex === -1 ? index + 1 : firstMatchingIndex + 1;
}

function contestMiniScore(contest) {
    const live = contest.live ?? {};

    if (hasValue(live.home) || hasValue(live.away)) {
        return `${hasValue(live.home) ? live.home : '—'} : ${hasValue(live.away) ? live.away : '—'}`;
    }

    return contestPhase(live);
}

function StaffLink({ authenticated, onClick, tabIndex, className = '' }) {
    return (
        <Link
            href={authenticated ? route('dashboard') : route('login')}
            prefetch="mount"
            onClick={onClick}
            tabIndex={tabIndex}
            className={`inline-flex min-h-10 items-center justify-center rounded-none bg-[#D5A21F] px-4 text-xs font-bold uppercase tracking-[0.08em] text-[#17212B] transition-colors hover:bg-[#F5C64B] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F] ${className}`}
        >
            {authenticated ? 'Open Dashboard' : 'Staff Login'}
        </Link>
    );
}

function BrandBar({ authenticated, event }) {
    const [menuOpen, setMenuOpen] = useState(false);
    const menuButtonRef = useRef(null);
    const closeButtonRef = useRef(null);
    const drawerRef = useRef(null);
    const wasOpenRef = useRef(false);

    const closeMenu = () => setMenuOpen(false);

    useEffect(() => {
        if (menuOpen) {
            document.body.style.overflow = 'hidden';
            closeButtonRef.current?.focus();
        } else {
            document.body.style.removeProperty('overflow');

            if (wasOpenRef.current) {
                menuButtonRef.current?.focus();
            }
        }

        wasOpenRef.current = menuOpen;

        return () => {
            document.body.style.removeProperty('overflow');
        };
    }, [menuOpen]);

    useEffect(() => {
        if (!menuOpen) return undefined;

        const handleKeyDown = (keyEvent) => {
            if (keyEvent.key === 'Escape') {
                keyEvent.preventDefault();
                closeMenu();
                return;
            }

            if (keyEvent.key !== 'Tab') return;

            const focusable = Array.from(drawerRef.current?.querySelectorAll('a[href], button:not([disabled])') ?? []);
            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (keyEvent.shiftKey && document.activeElement === first) {
                keyEvent.preventDefault();
                last.focus();
            } else if (!keyEvent.shiftKey && document.activeElement === last) {
                keyEvent.preventDefault();
                first.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [menuOpen]);

    const publicLinkClass = 'rounded-xs px-2 py-2 transition-colors hover:text-[#F5C64B] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]';
    const mobileLinkClass = 'flex min-h-12 items-center justify-between border-b border-white/10 px-2 text-sm font-bold uppercase tracking-[0.13em] text-white/80 transition-colors hover:bg-white/[0.06] hover:text-[#F5C64B] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#F5C64B]';

    return (
        <header className="border-t-[3px] border-[#D5A21F]">
            <div className="flex min-h-[4.25rem] items-stretch overflow-hidden bg-[#0B2E4F] md:min-h-[4.5rem]">
                <div className="flex min-w-0 flex-1 items-center bg-[#08253F] px-3 py-2 sm:px-4 md:flex-none md:px-5">
                    <Link
                        href={route('landing')}
                        className="flex min-w-0 items-center gap-3 rounded-none focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B] focus-visible:ring-offset-2 focus-visible:ring-offset-[#08253F]"
                    >
                        <span className="grid size-10 shrink-0 place-items-center bg-[#F7F5EF] p-1.5 sm:size-11">
                            <img src="/icons/icon.svg" alt="SYNTIX" width="40" height="40" />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-xs font-extrabold uppercase tracking-[0.1em] text-white">CSPC SIKLAB</span>
                            <span className="mt-0.5 block truncate text-[10px] uppercase tracking-[0.1em] text-[#B8C6D0]">Public event boards</span>
                        </span>
                    </Link>
                </div>

                <nav aria-label="Public landing navigation" className="hidden min-w-0 flex-1 items-center gap-6 px-6 text-xs font-bold uppercase tracking-[0.13em] text-white/65 lg:gap-8 md:flex">
                    <a href="#live-score-stage" className={`${publicLinkClass} text-[#F5C64B]`}>Live Scores</a>
                    <a href="#competitions" className={publicLinkClass}>Competitions</a>
                    {event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" className={publicLinkClass}>Event Board</Link> : null}
                </nav>

                <nav aria-label="Staff access" className="hidden shrink-0 items-center px-3 md:flex">
                    <StaffLink authenticated={authenticated} />
                </nav>

                <button
                    ref={menuButtonRef}
                    type="button"
                    aria-label="Open navigation menu"
                    aria-controls="mobile-navigation-drawer"
                    aria-expanded={menuOpen}
                    onClick={() => setMenuOpen(true)}
                    className="m-2 inline-flex min-w-12 items-center justify-center border border-white/20 bg-[#0B2E4F] text-white transition-colors hover:bg-[#164565] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B] focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F] md:hidden"
                >
                    <span className="sr-only">Open navigation</span>
                    <span aria-hidden="true" className="flex flex-col gap-1.5">
                        <span className="block h-0.5 w-5 bg-[#F5C64B]" />
                        <span className="block h-0.5 w-5 bg-[#F5C64B]" />
                        <span className="block h-0.5 w-5 bg-[#F5C64B]" />
                    </span>
                </button>
            </div>

            <div className={`fixed inset-0 z-50 md:hidden ${menuOpen ? 'pointer-events-auto' : 'pointer-events-none'}`} aria-hidden={!menuOpen}>
                <button
                    type="button"
                    aria-label="Close navigation menu"
                    tabIndex={menuOpen ? 0 : -1}
                    onClick={closeMenu}
                    className={`absolute inset-0 bg-[#071F33]/70 transition-opacity duration-200 motion-reduce:transition-none ${menuOpen ? 'opacity-100' : 'opacity-0'}`}
                />

                <aside
                    ref={drawerRef}
                    id="mobile-navigation-drawer"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Public navigation"
                    className={`absolute right-0 top-0 flex h-full w-[min(19rem,86vw)] flex-col bg-[#0B2E4F] text-white shadow-[-24px_0_60px_rgba(7,31,51,0.35)] transition-transform duration-200 motion-reduce:transition-none ${menuOpen ? 'translate-x-0' : 'translate-x-full'}`}
                >
                    <div className="flex items-center justify-between border-b border-white/15 bg-[#08253F] px-4 py-4">
                        <div>
                            <p className="font-condensed text-lg font-extrabold uppercase tracking-[0.08em]">CSPC SIKLAB</p>
                            <p className="mt-0.5 text-[10px] uppercase tracking-[0.12em] text-[#B8C6D0]">Public event boards</p>
                        </div>
                        <button
                            ref={closeButtonRef}
                            type="button"
                            aria-label="Close navigation menu"
                            tabIndex={menuOpen ? 0 : -1}
                            onClick={closeMenu}
                            className="inline-flex size-10 items-center justify-center border border-white/20 text-2xl leading-none text-[#F5C64B] transition-colors hover:bg-white/[0.06] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B]"
                        >
                            <span aria-hidden="true">×</span>
                        </button>
                    </div>

                    <nav aria-label="Mobile public navigation" className="flex flex-col px-4 py-5">
                        <a href="#live-score-stage" tabIndex={menuOpen ? 0 : -1} onClick={closeMenu} className={`${mobileLinkClass} text-[#F5C64B]`}>
                            Live Scores <span aria-hidden="true">→</span>
                        </a>
                        <a href="#competitions" tabIndex={menuOpen ? 0 : -1} onClick={closeMenu} className={mobileLinkClass}>
                            Competitions <span aria-hidden="true">→</span>
                        </a>
                        {event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" tabIndex={menuOpen ? 0 : -1} onClick={closeMenu} className={mobileLinkClass}>
                            Event Board <span aria-hidden="true">→</span>
                        </Link> : null}
                    </nav>

                    <div className="mt-auto border-t border-white/15 p-4">
                        <StaffLink authenticated={authenticated} onClick={closeMenu} tabIndex={menuOpen ? 0 : -1} className="w-full" />
                    </div>
                </aside>
            </div>
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

    useEffect(() => {
        const timestamp = Date.parse(snapshotAt ?? '');

        if (!Number.isNaN(timestamp)) {
            setLastSuccessfulAt(timestamp);
            setStatus('ready');
        }
    }, [snapshotAt]);

    useEffect(() => {
        let active = true;

        const poll = router.poll(
            POLL_INTERVAL_MS,
            {
                only: PUBLIC_POLL_PROPS,
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    if (active) setStatus('refreshing');
                },
                onSuccess: () => {
                    if (active) {
                        setLastSuccessfulAt(Date.now());
                        setStatus('ready');
                    }
                },
                onError: () => {
                    if (active) setStatus('disconnected');
                },
            },
            { autoStart: false, mode: 'rest' },
        );

        const handleVisibilityChange = () => {
            if (document.visibilityState === 'hidden') {
                poll.stop();
            } else {
                poll.start();
            }
        };
        const removeExceptionListener = router.on('exception', () => {
            if (active) {
                setStatus('disconnected');
            }
        });

        document.addEventListener('visibilitychange', handleVisibilityChange);
        if (document.visibilityState === 'visible') poll.start();

        return () => {
            active = false;
            poll.destroy();
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
                    {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-[#D5A21F] px-5 text-sm font-bold text-[#17212B] transition-colors hover:bg-[#F1C85F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]">Open Event Board</Link> : null}
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
                {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-full bg-[#D5A21F] px-5 text-sm font-bold text-[#17212B] transition-colors hover:bg-[#F1C85F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]">Open Event Board</Link> : null}
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
                <div className="mt-3 rounded-2xl border border-[#DDDCD6] bg-white p-3 shadow-xs sm:p-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex flex-wrap items-center gap-2">
                            <button type="button" onClick={() => move(-1)} className="inline-flex min-h-11 items-center justify-center rounded-full border border-[#D5DBDE] px-4 text-xs font-semibold text-[#30414B] transition-colors hover:border-[#0B2E4F] hover:text-[#0B2E4F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Previous</button>
                            <button
                                type="button"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => setRotationPaused((paused) => !paused)}
                                disabled={reducedMotion}
                                className="inline-flex min-h-11 items-center justify-center rounded-full border border-[#D5DBDE] px-4 text-xs font-semibold text-[#30414B] transition-colors hover:border-[#0B2E4F] hover:text-[#0B2E4F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F] disabled:cursor-not-allowed disabled:text-[#9AA5AA]"
                            >
                                {reducedMotion ? 'Rotation off' : rotationPaused ? 'Start rotation' : 'Pause rotation'}
                            </button>
                            <button type="button" onClick={() => move(1)} className="inline-flex min-h-11 items-center justify-center rounded-full border border-[#D5DBDE] px-4 text-xs font-semibold text-[#30414B] transition-colors hover:border-[#0B2E4F] hover:text-[#0B2E4F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">Next</button>
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
                                    className={`min-h-11 min-w-36 shrink-0 rounded-xl border px-3 py-2 text-left transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F] ${active ? 'border-[#0B2E4F] bg-[#0B2E4F] text-white' : 'border-[#E0E4E5] bg-[#F8F8F6] text-[#30414B] hover:border-[#AAB5BA]'}`}
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

            {event ? <Link href={route('public.scoreboard', event.slug)} className="mt-5 inline-flex min-h-11 items-center justify-center rounded-full border border-[#CDD5D8] px-4 text-sm font-semibold text-[#0B2E4F] transition-colors hover:border-[#0B2E4F] hover:bg-[#F4F6F6] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F]">View All Standings</Link> : null}
        </section>
    );
}

function BroadcastHero({ authenticated, event, contests, competitionCount, leaderboard, status, lastSuccessfulAt }) {
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
                        {event ? <Link href={route('public.scoreboard', event.slug)} className="inline-flex min-h-11 items-center justify-center rounded-full bg-[#0B2E4F] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#164565] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F7F5EF]">View Event Board</Link> : null}
                    </div>
                </div>

                <section aria-label="Event at a glance" className="grid gap-3 pb-7 sm:grid-cols-3 sm:pb-9">
                    <article className="border-l-[3px] border-[#D5A21F] bg-white px-5 py-4 shadow-[0_10px_28px_rgba(23,33,43,0.05)]">
                        <p className="text-xs font-semibold uppercase tracking-[0.13em] text-[#7A878D]">Live now</p>
                        <div className="mt-2 flex items-end justify-between gap-3"><p className="font-mono text-3xl font-bold tabular-nums text-[#0B2E4F]">{contests.length}</p><span className="pb-1 text-xs text-[#68767E]">{contests.length === 1 ? 'contest' : 'contests'}</span></div>
                    </article>
                    <article className="border-l-[3px] border-[#0B536D] bg-white px-5 py-4 shadow-[0_10px_28px_rgba(23,33,43,0.05)]">
                        <p className="text-xs font-semibold uppercase tracking-[0.13em] text-[#7A878D]">Sports programme</p>
                        <div className="mt-2 flex items-end justify-between gap-3"><p className="font-mono text-3xl font-bold tabular-nums text-[#0B2E4F]">{competitionCount}</p><span className="pb-1 text-xs text-[#68767E]">published</span></div>
                    </article>
                    <article className="border-l-[3px] border-[#16845B] bg-white px-5 py-4 shadow-[0_10px_28px_rgba(23,33,43,0.05)]">
                        <p className="text-xs font-semibold uppercase tracking-[0.13em] text-[#7A878D]">Department table</p>
                        <div className="mt-2 flex items-end justify-between gap-3"><p className="font-mono text-3xl font-bold tabular-nums text-[#0B2E4F]">{leaderboard.length}</p><span className="pb-1 text-xs text-[#68767E]">with points</span></div>
                    </article>
                </section>

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

function nextPublishedSchedule(competitions) {
    const now = Date.now();

    return competitions
        .flatMap((competition) => competition.schedules.map((schedule) => ({ ...schedule, competition: competition.name, cover: competition.cover })))
        .filter((schedule) => schedule.starts_at && Date.parse(schedule.starts_at) >= now)
        .sort((left, right) => Date.parse(left.starts_at) - Date.parse(right.starts_at))[0] ?? null;
}

function ScoreCover({ competition }) {
    const [failed, setFailed] = useState(false);
    const cover = competition?.cover;
    const showCover = cover?.url && !failed;

    return (
        <>
            {showCover ? (
                <img
                    src={cover.url}
                    alt=""
                    width={cover.width}
                    height={cover.height}
                    className="absolute inset-y-0 right-0 h-full w-1/2 object-cover opacity-45 grayscale-[0.35]"
                    onError={() => setFailed(true)}
                />
            ) : (
                <div className="absolute inset-y-0 right-0 w-1/2 overflow-hidden opacity-60" aria-hidden="true">
                    <span className="absolute -right-20 -top-20 size-72 rounded-full border-[3rem] border-white/[0.07]" />
                    <span className="absolute -bottom-28 right-8 size-72 rounded-full border-2 border-[#D5A21F]/40" />
                    <span className="absolute inset-y-0 left-1/2 border-l border-white/10" />
                    <span className="absolute inset-x-0 top-1/2 border-t border-white/10" />
                </div>
            )}
            <div className="absolute inset-0 bg-gradient-to-r from-[#0B2E4F] via-[#0B2E4F]/90 to-[#0B2E4F]/25" aria-hidden="true" />
            <div className="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-[#071F33]/70 to-transparent" aria-hidden="true" />
        </>
    );
}

function ScoreValue({ value, changed }) {
    return (
        <p className={`mt-2 font-condensed text-[clamp(5rem,10vw,7.5rem)] font-extrabold leading-[0.78] tracking-[-0.05em] tabular-nums text-white ${changed ? 'text-[#F5C64B] motion-safe:animate-pulse' : ''}`}>
            {hasValue(value) ? value : '—'}
        </p>
    );
}

function BroadcastScoreBoard({ event, contest, competition, nextSchedule, lastSuccessfulAt }) {
    const live = contest?.live ?? {};
    const phase = contestPhase(live);
    const [changedSides, setChangedSides] = useState({ home: false, away: false });
    const previousScores = useRef(null);

    useEffect(() => {
        if (!contest) {
            previousScores.current = null;
            setChangedSides({ home: false, away: false });
            return undefined;
        }

        const current = { id: contest.id, home: live.home, away: live.away };
        const previous = previousScores.current;
        const nextChanged = previous?.id === current.id
            ? { home: String(previous.home) !== String(current.home), away: String(previous.away) !== String(current.away) }
            : { home: false, away: false };

        previousScores.current = current;
        setChangedSides(nextChanged);

        const timeout = window.setTimeout(() => setChangedSides({ home: false, away: false }), 1200);

        return () => window.clearTimeout(timeout);
    }, [contest?.id, contest?.revision, live.home, live.away]);

    if (!contest) {
        return (
            <article id="live-score-stage" aria-labelledby="next-programme-title" className="relative flex min-h-[22rem] scroll-mt-6 flex-col justify-between overflow-hidden border border-[#0B2E4F] bg-[#0B2E4F] p-6 text-white sm:min-h-[25rem] sm:p-8">
                <ScoreCover competition={nextSchedule} />
                <div className="relative max-w-xl">
                    <p className="font-condensed text-sm font-extrabold uppercase tracking-[0.16em] text-[#F1C85F]">{nextSchedule ? 'Next on the programme' : 'No contests live'}</p>
                    <h2 id="next-programme-title" className="mt-3 max-w-2xl font-condensed text-4xl font-extrabold uppercase leading-[0.9] tracking-tight sm:text-5xl">
                        {nextSchedule?.title ?? 'The next score will appear here.'}
                    </h2>
                    <p className="mt-4 max-w-lg text-sm leading-6 text-white/65">
                        {nextSchedule ? `${nextSchedule.competition} · ${nextSchedule.division}` : 'This board updates automatically when a public SIKLAB contest goes live.'}
                    </p>
                </div>
                <div className="relative mt-10 flex flex-col gap-4 border-t border-white/15 pt-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="text-xs text-white/60">
                        {nextSchedule ? <><p className="font-condensed text-xl font-extrabold uppercase text-[#F1C85F]">{formatProgrammeDate(nextSchedule.starts_at)}</p><p className="mt-1">{formatProgrammeTime(nextSchedule.starts_at, nextSchedule.ends_at)}{nextSchedule.venue ? ` · ${nextSchedule.venue.name}` : ''}</p></> : <p>Waiting for the event desk to publish the next live board.</p>}
                        <p className="mt-2">Updated <time dateTime={new Date(lastSuccessfulAt).toISOString()}>{formatDateTime(lastSuccessfulAt)}</time></p>
                    </div>
                    {event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" className="inline-flex min-h-10 shrink-0 items-center justify-center bg-[#D5A21F] px-4 text-xs font-bold uppercase tracking-[0.08em] text-[#17212B] transition-colors hover:bg-[#F1C85F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]">Full event board →</Link> : null}
                </div>
            </article>
        );
    }

    const home = contest.sides?.find((side) => side.position === 'home')?.label ?? 'Home';
    const away = contest.sides?.find((side) => side.position === 'away')?.label ?? 'Away';
    const hasScore = hasValue(live.home) || hasValue(live.away);

    return (
        <article id="live-score-stage" aria-labelledby="active-live-contest-title" className="relative flex min-h-[24rem] scroll-mt-6 flex-col overflow-hidden border border-[#0B2E4F] bg-[#0B2E4F] text-white sm:min-h-[28rem]">
            <ScoreCover competition={competition} />
            <div className="relative flex flex-wrap items-start justify-between gap-4 px-6 pb-3 pt-6 sm:px-8 sm:pt-8">
                <div className="min-w-0">
                    <p className="font-condensed text-base font-extrabold uppercase tracking-[0.12em] text-[#F1C85F]">{contest.competition} · {contest.division}</p>
                    <h2 id="active-live-contest-title" className="mt-1.5 truncate font-condensed text-2xl font-extrabold uppercase tracking-tight sm:text-3xl" title={contest.name}>{contest.name}</h2>
                </div>
                <span className="shrink-0 font-condensed text-sm font-extrabold uppercase tracking-[0.16em] text-white">Live</span>
            </div>

            <div className="relative flex flex-1 flex-col justify-center px-6 py-8 sm:px-9 lg:px-11">
                {hasScore ? (
                    <div className="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-end gap-3 sm:gap-7">
                        <div className="min-w-0">
                            <p className="truncate font-condensed text-lg font-extrabold uppercase text-white/70 sm:text-xl" title={home}>{home}</p>
                            <ScoreValue value={live.home} changed={changedSides.home} />
                        </div>
                        <span className="mb-3 font-condensed text-4xl font-extrabold text-[#D5A21F] sm:text-5xl">:</span>
                        <div className="min-w-0 text-right">
                            <p className="truncate font-condensed text-lg font-extrabold uppercase text-white/70 sm:text-xl" title={away}>{away}</p>
                            <ScoreValue value={live.away} changed={changedSides.away} />
                        </div>
                    </div>
                ) : (
                    <div className="border border-white/15 bg-white/[0.07] p-6 sm:p-8">
                        <p className="font-condensed text-base font-extrabold uppercase tracking-[0.12em] text-[#F1C85F]">Live activity</p>
                        <p className="mt-3 max-w-xl font-condensed text-4xl font-extrabold uppercase leading-[0.9] tracking-tight text-white sm:text-5xl">Performance in progress</p>
                        <p className="mt-5 font-condensed text-lg font-bold uppercase text-white/60">{phase}</p>
                    </div>
                )}
            </div>

            <div className="relative flex flex-col gap-4 border-t border-white/15 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-8">
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 font-condensed text-sm font-bold uppercase tracking-[0.06em] text-white/60">
                    <span className="text-[#F1C85F]">{phase}</span>
                    <span>Updated <time dateTime={contest.updated_at}>{formatDateTime(contest.updated_at)}</time></span>
                </div>
                {event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" className="inline-flex min-h-10 shrink-0 items-center justify-center bg-[#D5A21F] px-4 text-xs font-bold uppercase tracking-[0.08em] text-[#17212B] transition-colors hover:bg-[#F1C85F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[#0B2E4F]">Full event board →</Link> : null}
            </div>
        </article>
    );
}

function LiveChannelStrip({ contests, activeId, onSelect }) {
    if (!contests.length) {
        return null;
    }

    return (
        <section aria-label="Live contest channels" className="border-x border-b border-[#CFD6D3] bg-[#F7F8F6] p-3 sm:p-4">
            <div className="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]">
                {contests.map((contest) => {
                    const active = contest.id === activeId;

                    return (
                        <button
                            key={contest.id}
                            type="button"
                            aria-pressed={active}
                            aria-controls="live-score-stage"
                            onClick={() => onSelect(contest.id)}
                            className={`min-h-14 min-w-48 shrink-0 border px-3 py-2 text-left transition-colors focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#0B536D] ${active ? 'border-[#0B2E4F] border-b-[3px] bg-[#0B2E4F] text-white' : 'border-[#D4DCDA] bg-white text-[#17212B] hover:border-[#0B536D]'}`}
                        >
                            <span className="flex items-center justify-between gap-3 font-condensed text-base font-extrabold uppercase leading-none">
                                <span className="truncate">{contest.competition} · {contest.division}</span>
                                <span className={active ? 'text-[#F1C85F]' : 'text-[#0B536D]'}>{contestMiniScore(contest)}</span>
                            </span>
                            <span className={`mt-2 block truncate text-[10px] uppercase tracking-[0.06em] ${active ? 'text-white/60' : 'text-[#77858A]'}`}>{contest.name}</span>
                        </button>
                    );
                })}
            </div>
        </section>
    );
}

function ChampionshipTeamBands({ event, leaderboard }) {
    const visible = leaderboard.slice(0, 5);

    return (
        <aside aria-labelledby="championship-standings-title" className="border border-[#CFD6D3] bg-white p-5 text-[#17212B] sm:p-6 lg:border-l-0 lg:border-t-0 lg:p-5">
            <div className="flex items-end justify-between gap-4 border-b-[3px] border-[#D5A21F] pb-4">
                <h2 id="championship-standings-title" className="font-condensed text-4xl font-extrabold uppercase leading-[0.82] tracking-tight">Championship<br />standings</h2>
                <span className="pb-1 text-right text-[9px] font-bold uppercase tracking-[0.1em] text-[#6D7B81]">Approved<br />points</span>
            </div>

            {visible.length ? (
                <ol className="mt-3 grid gap-2">
                    {visible.map((delegation, index) => {
                        const palette = departmentColor(delegation.color);
                        const rank = sharedStandingRank(leaderboard, index);

                        return (
                            <li key={delegation.id} className="grid min-h-[3.65rem] grid-cols-[3.5rem_minmax(0,1fr)_auto] items-center overflow-hidden border-l-[6px]" style={{ backgroundColor: palette.tint, borderLeftColor: palette.strong }}>
                                <span className="grid h-full place-items-center font-condensed text-3xl font-extrabold" style={{ backgroundColor: palette.strong, color: palette.text }}>{rank}</span>
                                <span className="min-w-0 px-3">
                                    <span className="block font-condensed text-xl font-extrabold uppercase leading-none">{delegation.abbreviation || delegation.name.slice(0, 4)}</span>
                                    <span className="mt-1 block truncate text-[10px] text-[#65747B]" title={delegation.name}>{delegation.name}</span>
                                </span>
                                <span className="pr-3 font-condensed text-3xl font-extrabold leading-none text-[#0B2E4F]">{displayPoints(delegation.total)}<span className="ml-1 text-[10px] font-bold uppercase tracking-[0.04em] text-[#65747B]">pts</span></span>
                            </li>
                        );
                    })}
                </ol>
            ) : (
                <div className="mt-4 border-l-4 border-[#CDD5D8] bg-[#F4F5F2] p-5">
                    <p className="font-condensed text-2xl font-extrabold uppercase">No approved totals yet</p>
                    <p className="mt-2 text-sm leading-6 text-[#758188]">Championship points will appear after the event desk approves them.</p>
                </div>
            )}

            {event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" className="mt-4 block border-t border-[#D9DEDC] pt-3 font-condensed text-sm font-extrabold uppercase tracking-[0.08em] text-[#0B536D] hover:text-[#0B2E4F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B536D]">View complete standings →</Link> : null}
        </aside>
    );
}

function BroadcastLedgerHero({ authenticated, event, contests, competitions, leaderboard, lastSuccessfulAt }) {
    const [activeId, setActiveId] = useState(contests[0]?.id ?? null);
    const contestSignature = contests.map((contest) => contest.id).join('|');

    useEffect(() => {
        if (!contests.length) {
            setActiveId(null);
            return;
        }

        if (!contests.some((contest) => contest.id === activeId)) {
            setActiveId(contests[0].id);
        }
    }, [contestSignature, activeId]);

    if (!event) {
        return (
            <section className="bg-[#F7F5EF]">
                <div className="mx-auto max-w-7xl px-5 sm:px-8">
                    <BrandBar authenticated={authenticated} event={event} />
                    <div className="border-b-2 border-[#0B2E4F] py-16 sm:py-24">
                        <p className="font-condensed text-sm font-extrabold uppercase tracking-[0.16em] text-[#946B00]">CSPC SIKLAB</p>
                        <h1 className="mt-3 max-w-3xl font-condensed text-5xl font-extrabold uppercase leading-[0.86] tracking-tight text-[#17212B] sm:text-7xl">No live event is published.</h1>
                        <p className="mt-5 max-w-xl text-base leading-7 text-[#68767E]">The public boards will appear here when the next SIKLAB event is released by the event desk.</p>
                    </div>
                </div>
            </section>
        );
    }

    const activeContest = contests.find((contest) => contest.id === activeId) ?? contests[0] ?? null;
    const activeCompetition = competitions.find((competition) => String(competition.id) === String(activeContest?.competition_id));
    const upcoming = activeContest ? null : nextPublishedSchedule(competitions);

    return (
        <section className="bg-[#F7F5EF]">
            <div className="mx-auto max-w-7xl px-5 sm:px-8">
                <BrandBar authenticated={authenticated} event={event} />

                <div className="flex flex-col gap-3 border-b-0 pb-4 pt-7 sm:flex-row sm:items-end sm:justify-between sm:gap-6 sm:pt-9">
                    <div>
                        <p className="font-condensed text-sm font-extrabold uppercase tracking-[0.16em] text-[#946B00]">Now at</p>
                        <h1 className="mt-2 font-condensed text-5xl font-extrabold uppercase leading-[0.84] tracking-tight text-[#17212B] sm:text-7xl">{event.name}</h1>
                    </div>
                    <p className="font-condensed text-sm font-bold uppercase tracking-[0.06em] text-[#68767E]">{formatEventDate(event.starts_at)} · Nabua, Camarines Sur</p>
                </div>

                <div className="flex min-h-10 flex-wrap items-center border-y-2 border-[#0B2E4F] text-[10px] font-bold uppercase tracking-[0.08em] text-[#68767E]">
                    <span className="border-r border-[#CFD6D3] py-2 pr-4"><strong className="mr-1 font-condensed text-xl text-[#0B2E4F]">{contests.length}</strong> live</span>
                    <span className="border-r border-[#CFD6D3] px-4 py-2"><strong className="mr-1 font-condensed text-xl text-[#0B2E4F]">{competitions.length}</strong> sports</span>
                    <span className="border-r border-[#CFD6D3] px-4 py-2"><strong className="mr-1 font-condensed text-xl text-[#0B2E4F]">{leaderboard.length}</strong> departments</span>
                    <span className="py-2 pl-4"><strong className="mr-1 font-condensed text-xl text-[#0B2E4F]">{formatDateTime(lastSuccessfulAt).split(', ').pop()}</strong> updated</span>
                </div>

                <div className="grid gap-0 pb-10 pt-4 lg:grid-cols-[minmax(0,1fr)_22rem] lg:pb-14">
                    <div className="min-w-0 lg:col-start-1 lg:row-start-1">
                        <BroadcastScoreBoard event={event} contest={activeContest} competition={activeCompetition} nextSchedule={upcoming} lastSuccessfulAt={lastSuccessfulAt} />
                    </div>
                    <div className="min-w-0 lg:col-start-1 lg:row-start-2">
                        <LiveChannelStrip contests={contests} activeId={activeContest?.id} onSelect={setActiveId} />
                    </div>
                    <div className="min-w-0 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                        <ChampionshipTeamBands event={event} leaderboard={leaderboard} />
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

function ProgrammeArtwork({ competition, bracketCount }) {
    const [failed, setFailed] = useState(false);
    const showPhoto = competition.cover?.url && !failed;
    const initials = competition.name.slice(0, 2).toUpperCase();

    return (
        <div className="relative h-36 overflow-hidden bg-[#0B2E4F] text-white" style={{ clipPath: 'polygon(0 0, calc(100% - 1.5rem) 0, 100% 1.5rem, 100% 100%, 0 100%)' }}>
            {showPhoto ? <img src={competition.cover.url} alt={competition.cover.alt} width={competition.cover.width} height={competition.cover.height} loading="lazy" className="absolute inset-0 h-full w-full object-cover opacity-75" onError={() => setFailed(true)} /> : null}
            <div className="absolute inset-0 overflow-hidden bg-gradient-to-br from-[#0B2E4F] via-[#0B536D]/85 to-[#08243C]">
                <span className="absolute -right-20 -top-24 size-56 rounded-full border border-white/15" />
                <span className="absolute -bottom-24 -left-16 size-64 rounded-full border-2 border-[#D5A21F]/50" />
                <span className="absolute inset-y-0 left-1/2 border-l border-white/10" />
            </div>
            <div className="absolute inset-x-4 top-3 flex items-start justify-between gap-3 text-[9px] font-extrabold uppercase tracking-[0.14em] text-[#F1CC6B] sm:inset-x-5">
                <span>Published programme</span>
                <span className="border border-white/25 px-2 py-1 text-white">{bracketCount ? `${bracketCount} bracket${bracketCount === 1 ? '' : 's'}` : 'Programme only'}</span>
            </div>
            <h3 className="absolute inset-x-4 bottom-3 m-0 font-condensed text-3xl font-extrabold uppercase tracking-tight sm:inset-x-5">{competition.name}</h3>
            <span className="absolute bottom-2 right-4 font-condensed text-5xl font-extrabold leading-none text-white/20" aria-hidden="true">{initials}</span>
        </div>
    );
}

function BracketPreview({ event, division, preview }) {
    const matchups = preview.matchups.slice(0, 3);

    return (
        <div className="border-t-2 border-[#0B2E4F] bg-[#F7F8F6] px-3 pb-3">
            <div className="flex items-center justify-between gap-3 py-2">
                <span className="font-condensed text-xs font-extrabold uppercase tracking-[0.08em] text-[#0B2E4F]">{preview.round_label}</span>
                {event ? <Link href={route('public.bracket', [event.slug, division.id])} className="text-[9px] font-extrabold uppercase tracking-[0.08em] text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B536D]">View full bracket</Link> : null}
            </div>
            {matchups.length ? <div className="grid gap-2">
                {matchups.map((matchup) => (
                    <div key={matchup.id} className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 border border-[#D4DAD7] border-l-[3px] border-l-[#D5A21F] bg-white px-2 py-2 text-[10px] font-bold text-[#17212B]">
                        <span className="truncate">{matchup.slots[0]?.label ?? 'To be determined'}</span>
                        <span className="font-condensed text-[9px] uppercase text-[#8A9598]">vs</span>
                        <span className="truncate text-right">{matchup.slots[1]?.label ?? 'To be determined'}</span>
                    </div>
                ))}
            </div> : <p className="border border-dashed border-[#CDD4D1] bg-white px-2 py-3 text-[10px] leading-4 text-[#69777D]">The bracket is published; matchups will appear when the draw is populated.</p>}
            <p className="mt-2 text-[9px] leading-4 text-[#7A878D]">Bracket v{preview.version} · {matchups.length} matchup{matchups.length === 1 ? '' : 's'} shown</p>
        </div>
    );
}

function bracketFormatLabel(value) {
    return {
        single_elimination: 'Single elimination',
        double_elimination: 'Double elimination',
        round_robin: 'Round robin',
    }[value] ?? 'Published draw';
}

function DivisionBracket({ event, division, open, onToggle }) {
    const panelId = `public-bracket-preview-${division.id}`;

    if (!division.bracket_preview) {
        return <div className="mt-3 border-l-[3px] border-[#CDD4D1] bg-[#FAFAF7] px-3 py-3 text-[10px] leading-4 text-[#69777D]"><strong className="block font-condensed text-sm font-extrabold uppercase text-[#17212B]">{division.name}</strong>Bracket not published yet. Programme details remain available.</div>;
    }

    return (
        <div className="mt-3 overflow-hidden border border-[#D8DEDB] bg-[#F7F8F6]">
            <button type="button" aria-expanded={open} aria-controls={panelId} onClick={onToggle} className="flex min-h-11 w-full items-center justify-between gap-3 px-3 py-2 text-left text-[#0B2E4F] hover:bg-[#EDF3F5] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#0B536D]">
                <span><strong className="font-condensed text-sm font-extrabold uppercase">{division.name}</strong><span className="ml-2 text-[9px] font-bold uppercase tracking-[0.06em] text-[#78858A]">· {bracketFormatLabel(division.bracket_preview.format)}</span></span>
                <span className="font-condensed text-xl font-extrabold text-[#D5A21F]" aria-hidden="true">{open ? '−' : '+'}</span>
            </button>
            {open ? <div id={panelId}><BracketPreview event={event} division={division} preview={division.bracket_preview} /></div> : null}
        </div>
    );
}

function CompetitionDirectory({ event, competitions }) {
    const [query, setQuery] = useState('');
    const [bracketsOnly, setBracketsOnly] = useState(false);
    const [openDivisionIds, setOpenDivisionIds] = useState(() => new Set(
        competitions.flatMap((competition) => competition.divisions.filter((division) => division.bracket_preview).slice(0, 1).map((division) => division.id)),
    ));
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const visibleCompetitions = competitions.filter((competition) => {
        const searchable = [competition.name, ...competition.divisions.map((division) => division.name)].join(' ').toLocaleLowerCase();

        return (!normalizedQuery || searchable.includes(normalizedQuery))
            && (!bracketsOnly || competition.divisions.some((division) => division.bracket_preview));
    });

    const toggleDivision = (divisionId) => {
        setOpenDivisionIds((current) => {
            const next = new Set(current);
            if (next.has(divisionId)) next.delete(divisionId);
            else next.add(divisionId);
            return next;
        });
    };

    return (
        <section id="competitions" aria-labelledby="competition-directory-title" className="scroll-mt-6 bg-[#F5F4EF]">
            <div className="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
                <div className="grid gap-8 border-b-2 border-[#0B2E4F] pb-8 lg:grid-cols-[minmax(0,1.25fr)_minmax(18rem,0.75fr)] lg:items-end lg:gap-12">
                    <div><p className="font-condensed text-sm font-extrabold uppercase tracking-[0.15em] text-[#946B00]">Sports programme</p><h2 id="competition-directory-title" className="mt-2 max-w-4xl font-condensed text-5xl font-extrabold uppercase leading-[0.93] tracking-tight text-[#17212B] sm:text-7xl">Find the next contest. Follow the bracket.</h2></div>
                    <div><p className="text-sm leading-7 text-[#69777D]">Published schedules and tournament progress for every SIKLAB competition. Matchups update only from official event-desk releases.</p><div className="mt-5 flex items-center justify-between gap-3"><span className="font-condensed text-sm font-extrabold uppercase tracking-[0.08em] text-[#0B536D]">{competitions.length} sports &amp; activities</span>{event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" className="inline-flex min-h-11 items-center justify-center border-b-[3px] border-[#D5A21F] bg-[#0B2E4F] px-4 text-sm font-semibold text-white transition-colors hover:bg-[#164565] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F5F4EF]">Open event board</Link> : null}</div></div>
                </div>
                <div className="grid gap-4 py-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"><label className="flex max-w-xl items-center border-b-2 border-[#0B2E4F] bg-white px-3"><span className="font-condensed text-xl font-extrabold text-[#D5A21F]" aria-hidden="true">⌕</span><input type="search" value={query} onChange={(inputEvent) => setQuery(inputEvent.target.value)} placeholder="Find basketball, arnis, chess…" aria-label="Find a sport" className="min-h-11 w-full border-0 bg-transparent px-2 text-sm text-[#17212B] outline-hidden ring-0 placeholder:text-[#8A9598] focus:ring-0" /></label><label className="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm font-semibold text-[#0B2E4F]"><input type="checkbox" checked={bracketsOnly} onChange={(inputEvent) => setBracketsOnly(inputEvent.target.checked)} className="size-4 rounded-sm border-[#0B536D] text-[#0B536D] focus:ring-[#0B536D]" /> Brackets available</label></div>
                {visibleCompetitions.length ? <div className="grid items-start gap-6 md:grid-cols-2 xl:grid-cols-3">{visibleCompetitions.map((competition) => {
                    const bracketCount = competition.divisions.filter((division) => division.bracket_preview).length;
                    const nextSchedule = competition.schedules[0];

                    return <article key={competition.id} className="overflow-hidden border border-[#CFD6D3] bg-white shadow-[8px_8px_0_rgba(8,46,79,0.08)] transition-transform hover:-translate-y-0.5 motion-reduce:transition-none"><ProgrammeArtwork competition={competition} bracketCount={bracketCount} /><div className="p-4 sm:p-5"><p className="font-condensed text-[10px] font-extrabold uppercase tracking-[0.15em] text-[#78858A]">Next on the programme</p>{nextSchedule ? <div className="mt-2 grid grid-cols-[4.25rem_minmax(0,1fr)] gap-3 border-b border-[#DFE4E2] pb-4"><div><p className="font-condensed text-lg font-extrabold leading-none text-[#0B2E4F]">{formatProgrammeDate(nextSchedule.starts_at)}</p><p className="mt-1 font-condensed text-xs font-bold text-[#69777D]">{formatProgrammeTime(nextSchedule.starts_at, nextSchedule.ends_at)}</p></div><div><p className="text-xs font-extrabold text-[#17212B]">{nextSchedule.title}</p><p className="mt-1 text-[10px] leading-4 text-[#69777D]">{nextSchedule.division}{nextSchedule.venue ? <> · {nextSchedule.venue.name}{nextSchedule.venue.location ? `, ${nextSchedule.venue.location}` : ''}</> : ' · Venue to be announced'}</p>{competition.schedules.length > 1 ? <p className="mt-2 text-[9px] font-bold uppercase tracking-[0.06em] text-[#0B536D]">+ {competition.schedules.length - 1} more published schedule{competition.schedules.length === 2 ? '' : 's'}</p> : null}</div></div> : <div className="mt-2 border-b border-[#DFE4E2] pb-4"><p className="text-xs font-extrabold text-[#17212B]">Schedule not published</p><p className="mt-1 text-[10px] leading-4 text-[#69777D]">The event desk has not released a public time and venue.</p></div>}<div className="mt-4"><p className="font-condensed text-[10px] font-extrabold uppercase tracking-[0.15em] text-[#78858A]">Divisions &amp; brackets</p>{competition.divisions.map((division) => <DivisionBracket key={division.id} event={event} division={division} open={openDivisionIds.has(division.id)} onToggle={() => toggleDivision(division.id)} />)}</div></div></article>;
                })}</div> : <div className="border-l-4 border-[#D5A21F] bg-white px-6 py-8 shadow-[0_14px_35px_rgba(23,33,43,0.05)]"><h3 className="font-condensed text-2xl font-extrabold uppercase text-[#17212B]">No sports match those filters.</h3><p className="mt-2 max-w-xl text-sm leading-6 text-[#69777D]">Try another sport name or clear the bracket filter to see the full published programme.</p></div>}
            </div>
        </section>
    );
}

function LegacyCompetitionDirectory({ event, competitions }) {
    return (
        <section id="competitions" aria-labelledby="competition-directory-title" className="scroll-mt-6 bg-[#F5F4EF]">
            <div className="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-20">
                <div className="flex flex-col gap-6 border-b border-[#C9CECD] pb-8 md:flex-row md:items-end md:justify-between">
                    <div className="max-w-3xl">
                        <p className="text-sm font-semibold text-[#9B741A]">Sports programme</p>
                        <h2 id="competition-directory-title" className="mt-2 text-balance text-3xl font-bold tracking-[-0.04em] text-[#17212B] sm:text-5xl">See what’s playing, when, and where.</h2>
                        <p className="mt-4 max-w-2xl text-base leading-7 text-[#68767E]">Published schedules and CSPC event photos, organized by competition. Changes appear only after the event desk republishes them.</p>
                    </div>
                    {event ? <Link href={route('public.scoreboard', event.slug)} prefetch="mount" className="inline-flex min-h-11 shrink-0 items-center justify-center rounded-lg bg-[#0B2E4F] px-5 text-sm font-semibold text-white transition-colors hover:bg-[#164565] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B2E4F] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F5F4EF]">Open Complete Event Board</Link> : null}
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
                                                    {division.has_published_bracket && event ? <Link href={route('public.bracket', [event.slug, division.id])} className="font-semibold text-[#0B536D] underline decoration-[#D5A21F] decoration-2 underline-offset-4 hover:text-[#0B2E4F] focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#0B536D]">Bracket</Link> : null}
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
                            <li><a href="#live-score-stage" className="inline-flex min-h-11 items-center rounded-sm transition-colors hover:text-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B]">Live Scores</a></li>
                            <li><a href="#competitions" className="inline-flex min-h-11 items-center rounded-sm transition-colors hover:text-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B]">Competitions</a></li>
                            {event ? <li><Link href={route('public.scoreboard', event.slug)} prefetch="mount" className="inline-flex min-h-11 items-center rounded-sm transition-colors hover:text-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B]">Event Board</Link></li> : null}
                        </ul>
                    </nav>

                    <nav aria-label="Institutional resources">
                        <h2 className="text-xs font-semibold uppercase tracking-[0.14em] text-[#E4BD54]">Institution</h2>
                        <ul className="mt-4 space-y-3 text-sm text-white/65">
                            <li><a href="https://cspc.edu.ph" target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center rounded-sm transition-colors hover:text-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B]">CSPC Website</a></li>
                            <li><Link href={authenticated ? route('dashboard') : route('login')} className="inline-flex min-h-11 items-center rounded-sm transition-colors hover:text-white focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[#F5C64B]">{authenticated ? 'Staff Dashboard' : 'Staff Login'}</Link></li>
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
    const { lastSuccessfulAt } = usePublicFreshness(snapshotAt);
    const contests = featuredContest ? [featuredContest, ...liveContests] : liveContests;

    return (
        <>
            <Head title={event ? `${event.name} Live` : 'CSPC SIKLAB'}>
                <meta name="theme-color" content="#F7F5EF" />
            </Head>
            <a href="#main-content" className="sr-only z-50 bg-[#F5C64B] px-4 py-3 font-bold text-[#17212B] focus:not-sr-only focus:fixed focus:left-4 focus:top-0 focus:outline-hidden focus:ring-2 focus:ring-[#17212B]">Skip to event content</a>
            <main id="main-content" className="min-h-screen overflow-x-hidden bg-[#F7F5EF] font-sans text-[#17212B]">
                <BroadcastLedgerHero authenticated={authenticated} event={event} contests={contests} competitions={competitions} leaderboard={leaderboard} lastSuccessfulAt={lastSuccessfulAt} />
                <CompetitionDirectory event={event} competitions={competitions} />
                <Footer authenticated={authenticated} event={event} />
            </main>
        </>
    );
}
