import React from 'react';
import AppIcon from '@/Components/AppIcon';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Link from '@/Components/PrefetchLink';
import { applyTheme, clearTheme } from '@/lib/theme';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function NavItem({ href, icon, label, active, badge, external = false, onNavigate, className = '' }) {
    const classes = `group flex min-h-11 items-center gap-3 border-l-[3px] px-4 py-2.5 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent ${active ? 'border-accent bg-white/10 text-white' : 'border-transparent text-white/65 hover:bg-white/5 hover:text-white'}`;
    return <Link href={href} onClick={onNavigate} aria-current={active ? 'page' : undefined} className={`${classes} ${className}`}><AppIcon name={icon} className="size-5 shrink-0 text-white/50 group-hover:text-accent"/><span className="min-w-0 flex-1">{label}</span>{badge > 0 ? <span className="rounded-full bg-accent px-2 py-0.5 font-mono text-[0.65rem] text-accent-foreground">{badge}</span> : null}{external ? <AppIcon name="external" className="size-4 text-white/35"/> : null}</Link>;
}
function NavGroup({ href, icon, label, active, badge, children, onNavigate, id }) {
    const [expanded, setExpanded] = useState(active);
    useEffect(() => { if (active) setExpanded(true); }, [active]);
    return <div>
        <div className="flex items-stretch">
            <NavItem href={href} icon={icon} label={label} active={active} badge={badge} onNavigate={onNavigate} className="min-w-0 flex-1" />
            <button type="button" aria-label={`${expanded ? 'Collapse' : 'Expand'} ${label}`} aria-expanded={expanded} aria-controls={id} onClick={() => setExpanded((value) => !value)} className={`mr-2 grid min-h-11 w-10 place-items-center border-l border-white/10 text-white/55 hover:bg-white/5 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent ${active ? 'bg-white/10' : ''}`}><AppIcon name="chevron" className={`size-4 transition-transform ${expanded ? 'rotate-180' : ''}`} /></button>
        </div>
        {expanded ? <div id={id} className="ml-5 border-l border-white/15 py-1">{children}</div> : null}
    </div>;
}

function formatNotificationTime(value) {
    if (!value) return '';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(date);
}

function notificationActionHref(action) {
    if (!action?.route) return null;

    try {
        return route(action.route, action.params ?? {});
    } catch (_) {
        return null;
    }
}

function NotificationBell({ notifications }) {
    const recent = Array.isArray(notifications?.recent) ? notifications.recent : [];
    const unreadCount = Number(notifications?.unread_count ?? 0);

    const markAllRead = () => {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true });
    };

    const markRead = (notification) => {
        if (notification.read_at) return;

        router.post(route('notifications.read', { notification: notification.id }), {}, { preserveScroll: true });
    };

    const openNotification = (notification) => {
        const href = notificationActionHref(notification.action);

        if (!href) {
            markRead(notification);
            return;
        }

        if (notification.read_at) {
            router.visit(href);
            return;
        }

        router.post(route('notifications.read', { notification: notification.id }), {}, {
            preserveScroll: true,
            onSuccess: () => router.visit(href),
        });
    };

    return <Popover className="relative">
        <PopoverButton
            aria-label="Open notifications"
            className="relative grid size-10 place-items-center rounded-lg text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
        >
            <AppIcon name="bell" className="size-5" />
            {unreadCount > 0 ? <span className="absolute right-0.5 top-0.5 min-w-4 rounded-full bg-accent px-1 text-center font-mono text-[0.62rem] font-bold leading-4 text-accent-foreground">{unreadCount > 99 ? '99+' : unreadCount}</span> : null}
        </PopoverButton>

        <PopoverPanel anchor="bottom end" className="z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-border bg-surface shadow-xl">
            <div className="flex items-start justify-between gap-4 border-b border-border px-4 py-3">
                <div>
                    <h2 className="font-serif text-lg text-foreground">Notifications</h2>
                    <p className="mt-0.5 text-xs text-muted">Recent admin activity</p>
                </div>
                {unreadCount > 0 ? <button type="button" onClick={markAllRead} className="rounded-md px-2 py-1 text-xs font-semibold text-primary hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">Mark all read</button> : null}
            </div>
            <div className="max-h-96 overflow-y-auto p-2">
                {recent.length > 0 ? recent.map((notification) => {
                    const actionHref = notificationActionHref(notification.action);

                    return <article key={notification.id} className={`rounded-lg px-3 py-3 ${notification.read_at ? '' : 'bg-primary/5'}`}>
                        <button type="button" onClick={() => openNotification(notification)} className="block w-full text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
                            <span className="flex items-start gap-3">
                                <span className={`mt-1.5 size-2 shrink-0 rounded-full ${notification.read_at ? 'bg-border' : 'bg-accent'}`} aria-hidden="true" />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold text-foreground">{notification.title}</span>
                                    {notification.message ? <span className="mt-1 block text-xs leading-5 text-muted">{notification.message}</span> : null}
                                    <span className="mt-1.5 block text-[0.68rem] text-muted">{formatNotificationTime(notification.created_at)}</span>
                                </span>
                            </span>
                        </button>
                        {actionHref ? <button type="button" onClick={() => openNotification(notification)} className="mt-3 rounded-md px-2 py-1 text-xs font-semibold text-primary hover:bg-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">{notification.action.label} <span aria-hidden="true">→</span></button> : null}
                    </article>;
                }) : <p className="px-3 py-8 text-center text-sm text-muted">You're all caught up.</p>}
            </div>
            <div className="border-t border-border px-4 py-3">
                <Link href={route('settings.edit', { section: 'notifications' })} className="text-xs font-semibold text-primary hover:text-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">Notification settings</Link>
            </div>
        </PopoverPanel>
    </Popover>;
}

function Sidebar({ onNavigate, activeSection = null }) {
    const page = usePage();
    const { auth, nav_badges: badges = {} } = page.props;
    const event = auth.active_event;
    const admin = auth.global_admin;
    const eventId = event?.id;
    const dashboard = activeSection ? activeSection === 'overview' : route().current('dashboard');
    const sportsActive = activeSection ? activeSection === 'sports' : route().current('admin.sports.*')
        || route().current('admin.discipline-entries.*')
        || route().current('admin.public-programme.*');
    const departmentsActive = activeSection ? activeSection === 'departments' : route().current('admin.departments.*')
        || route().current('admin.registrations.*')
        || route().current('admin.participants.*')
        || route().current('admin.coach-assignments.*')
        || route().current('admin.participant-import.*');
    const staffActive = activeSection ? activeSection === 'staff' : route().current('admin.staff.*') || route().current('admin.accounts.*');
    const resultsActive = activeSection ? activeSection === 'results' : route().current('admin.approvals.*');
    const settingsActive = route().current('settings') || route().current('settings.*');
    return <div className="flex h-full flex-col bg-sidebar text-white">
        <div className="border-b border-white/10 px-5 py-5"><Link href={route('dashboard')} onClick={onNavigate} className="flex items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"><ApplicationLogo className="size-9 text-accent"/><span><strong className="block font-serif text-lg tracking-[0.08em]">SYNTIX</strong><span className="text-[0.62rem] uppercase tracking-[0.2em] text-white/40">Event operations</span></span></Link></div>
        <div className="border-b border-white/10 px-5 py-4"><p className="text-[0.62rem] font-bold uppercase tracking-[0.16em] text-accent">Active event</p><p className="mt-1 truncate text-sm font-semibold">{event?.name ?? 'No event selected'}</p></div>
        <nav aria-label="Administration" className="flex-1 overflow-y-auto py-4">{eventId && admin ? <><NavItem href={route('dashboard', { event: eventId })} icon="overview" label="Overview" active={dashboard} onNavigate={onNavigate}/><NavItem href={route('admin.sports.index', eventId)} icon="trophy" label="Sports Directory" active={sportsActive} onNavigate={onNavigate}/><NavItem href={route('admin.departments.index', eventId)} icon="users" label="Departments" active={departmentsActive} onNavigate={onNavigate}/><NavItem href={route('admin.staff.index', eventId)} icon="badge" label="Event Staff" active={staffActive} badge={badges.staff} onNavigate={onNavigate}/><NavItem href={route('admin.approvals.index', eventId)} icon="clipboard-check" label="Results" active={resultsActive} badge={badges.results} onNavigate={onNavigate}/></> : <NavItem href={route('dashboard')} icon="overview" label="Overview" active={dashboard} onNavigate={onNavigate}/>}</nav>
        {admin ? <div className="border-t border-white/10 py-3"><NavItem href={route('admin.events.create')} icon="plus" label="Create Event" active={route().current('admin.events.create')} onNavigate={onNavigate}/><NavItem href={route('landing')} icon="external" label="View Public Site" external onNavigate={onNavigate}/></div> : null}
        {auth.user ? <div className="border-t border-white/10 py-3"><NavItem href={route('settings.edit')} icon="settings" label="Settings" active={settingsActive} onNavigate={onNavigate}/></div> : null}
        <div className="border-t border-white/10 p-4"><p className="truncate text-sm font-semibold">{auth.user?.name}</p><p className="truncate text-xs text-white/40">{auth.user?.email}</p><Link method="post" as="button" href={route('logout')} className="mt-3 flex items-center gap-2 text-xs font-semibold text-white/60 hover:text-white"><AppIcon name="logout" className="size-4"/>Sign out</Link></div>
    </div>;
}

export default function AuthenticatedLayout({ header, children, activeSection = null }) {
    const [open, setOpen] = useState(false);
    const { auth = {}, notifications = null } = usePage().props;
    const preferences = auth.preferences ?? auth.user?.preferences ?? {};

    useEffect(() => {
        const root = document.documentElement;
        const theme = preferences.theme ?? 'system';
        const applyCurrentTheme = () => applyTheme(theme);

        applyCurrentTheme();
        try {
            window.localStorage.setItem('syntix-theme', theme);
        } catch (_) {}

        const media = theme === 'system' && window.matchMedia
            ? window.matchMedia('(prefers-color-scheme: dark)')
            : null;
        if (media?.addEventListener) media.addEventListener('change', applyCurrentTheme);
        else media?.addListener?.(applyCurrentTheme);

        root.dataset.textSize = preferences.text_size ?? 'default';
        root.dataset.contrast = preferences.contrast ?? 'default';
        root.dataset.reduceMotion = preferences.reduce_motion ? 'true' : 'false';

        return () => {
            if (media?.removeEventListener) media.removeEventListener('change', applyCurrentTheme);
            else media?.removeListener?.(applyCurrentTheme);
            clearTheme();
            delete root.dataset.textSize;
            delete root.dataset.contrast;
            delete root.dataset.reduceMotion;
        };
    }, [preferences.theme, preferences.text_size, preferences.contrast, preferences.reduce_motion]);

    useEffect(() => { document.body.style.overflow = open ? 'hidden' : ''; return () => { document.body.style.overflow = ''; }; }, [open]);
    return <div className="min-h-screen bg-background text-foreground">
        <a href="#main-content" className="sr-only z-[60] rounded bg-surface px-4 py-2 text-foreground focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to content</a>
        <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 lg:block"><Sidebar activeSection={activeSection}/></aside>
        <header className="sticky top-0 z-30 border-b border-border bg-surface/95 backdrop-blur lg:ml-64"><div className="flex min-h-16 items-center gap-4 px-4 sm:px-6 lg:px-8"><button type="button" onClick={() => setOpen(true)} aria-label="Open navigation" aria-expanded={open} className="grid size-10 place-items-center rounded-lg text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:hidden"><AppIcon name="menu"/></button><div className="min-w-0 flex-1 py-3">{header}</div>{auth.global_admin && notifications ? <NotificationBell notifications={notifications} /> : null}</div></header>
        {open ? <div className="fixed inset-0 z-50 lg:hidden"><button type="button" aria-label="Close navigation backdrop" onClick={() => setOpen(false)} className="absolute inset-0 bg-foreground/55"/><aside role="dialog" aria-modal="true" aria-label="Administration navigation" className="relative h-full w-[min(20rem,88vw)] shadow-2xl"><button type="button" autoFocus onClick={() => setOpen(false)} aria-label="Close navigation" className="absolute right-3 top-3 z-10 grid size-10 place-items-center rounded-lg text-white focus-visible:ring-2 focus-visible:ring-accent"><AppIcon name="close"/></button><Sidebar onNavigate={() => setOpen(false)} activeSection={activeSection}/></aside></div> : null}
        <div id="main-content" tabIndex="-1" className="outline-none lg:ml-64">{children}</div>
    </div>;
}
