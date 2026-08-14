import React from 'react';
import AppIcon from '@/Components/AppIcon';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Link from '@/Components/PrefetchLink';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function NavItem({ href, icon, label, active, badge, external = false, onNavigate, className = '' }) {
    const classes = `group flex min-h-11 items-center gap-3 border-l-[3px] px-4 py-2.5 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D5A21F] ${active ? 'border-[#D5A21F] bg-white/10 text-white' : 'border-transparent text-white/65 hover:bg-white/5 hover:text-white'}`;
    return <Link href={href} onClick={onNavigate} aria-current={active ? 'page' : undefined} className={`${classes} ${className}`}><AppIcon name={icon} className="size-5 shrink-0 text-white/50 group-hover:text-[#D5A21F]"/><span className="min-w-0 flex-1">{label}</span>{badge > 0 ? <span className="rounded-full bg-[#D5A21F] px-2 py-0.5 font-mono text-[0.65rem] text-[#17212B]">{badge}</span> : null}{external ? <AppIcon name="external" className="size-4 text-white/35"/> : null}</Link>;
}

function NavGroup({ href, icon, label, active, badge, children, onNavigate, id }) {
    const [expanded, setExpanded] = useState(active);
    useEffect(() => { if (active) setExpanded(true); }, [active]);
    return <div>
        <div className="flex items-stretch">
            <NavItem href={href} icon={icon} label={label} active={active} badge={badge} onNavigate={onNavigate} className="min-w-0 flex-1" />
            <button type="button" aria-label={`${expanded ? 'Collapse' : 'Expand'} ${label}`} aria-expanded={expanded} aria-controls={id} onClick={() => setExpanded((value) => !value)} className={`mr-2 grid min-h-11 w-10 place-items-center border-l border-white/10 text-white/55 hover:bg-white/5 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#D5A21F] ${active ? 'bg-white/10' : ''}`}><AppIcon name="chevron" className={`size-4 transition-transform ${expanded ? 'rotate-180' : ''}`} /></button>
        </div>
        {expanded ? <div id={id} className="ml-5 border-l border-white/15 py-1">{children}</div> : null}
    </div>;
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
    return <div className="flex h-full flex-col bg-[#082944] text-white">
        <div className="border-b border-white/10 px-5 py-5"><Link href={route('dashboard')} onClick={onNavigate} className="flex items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F]"><ApplicationLogo className="size-9 text-[#D5A21F]"/><span><strong className="block font-serif text-lg tracking-[0.08em]">SYNTIX</strong><span className="text-[0.62rem] uppercase tracking-[0.2em] text-white/40">Event operations</span></span></Link></div>
        <div className="border-b border-white/10 px-5 py-4"><p className="text-[0.62rem] font-bold uppercase tracking-[0.16em] text-[#D5A21F]">Active event</p><p className="mt-1 truncate text-sm font-semibold">{event?.name ?? 'No event selected'}</p></div>
        <nav aria-label="Administration" className="flex-1 overflow-y-auto py-4">{eventId && admin ? <><NavItem href={route('dashboard', { event: eventId })} icon="overview" label="Overview" active={dashboard} onNavigate={onNavigate}/><NavItem href={route('admin.sports.index', eventId)} icon="trophy" label="Sports Directory" active={sportsActive} onNavigate={onNavigate}/><NavItem href={route('admin.departments.index', eventId)} icon="users" label="Departments" active={departmentsActive} onNavigate={onNavigate}/><NavItem href={route('admin.staff.index', eventId)} icon="badge" label="Event Staff" active={staffActive} badge={badges.staff} onNavigate={onNavigate}/><NavItem href={route('admin.approvals.index', eventId)} icon="clipboard-check" label="Results" active={resultsActive} badge={badges.results} onNavigate={onNavigate}/></> : <NavItem href={route('dashboard')} icon="overview" label="Overview" active={dashboard} onNavigate={onNavigate}/>}</nav>
        {admin ? <div className="border-t border-white/10 py-3"><NavItem href={route('admin.events.create')} icon="plus" label="Create Event" active={route().current('admin.events.create')} onNavigate={onNavigate}/><NavItem href={route('landing')} icon="external" label="View Public Site" external onNavigate={onNavigate}/></div> : null}
        <div className="border-t border-white/10 p-4"><p className="truncate text-sm font-semibold">{auth.user?.name}</p><p className="truncate text-xs text-white/40">{auth.user?.email}</p><Link method="post" as="button" href={route('logout')} className="mt-3 flex items-center gap-2 text-xs font-semibold text-white/60 hover:text-white"><AppIcon name="logout" className="size-4"/>Sign out</Link></div>
    </div>;
}

export default function AuthenticatedLayout({ header, children, activeSection = null }) {
    const [open, setOpen] = useState(false);
    useEffect(() => { document.body.style.overflow = open ? 'hidden' : ''; return () => { document.body.style.overflow = ''; }; }, [open]);
    return <div className="min-h-screen bg-[#F4F5F2] text-[#17212B]">
        <a href="#main-content" className="sr-only z-[60] rounded bg-white px-4 py-2 focus:not-sr-only focus:fixed focus:left-3 focus:top-3">Skip to content</a>
        <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 lg:block"><Sidebar activeSection={activeSection}/></aside>
        <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur lg:ml-64"><div className="flex min-h-16 items-center gap-4 px-4 sm:px-6 lg:px-8"><button type="button" onClick={() => setOpen(true)} aria-label="Open navigation" aria-expanded={open} className="grid size-10 place-items-center rounded-lg text-[#082944] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] lg:hidden"><AppIcon name="menu"/></button><div className="min-w-0 flex-1 py-3">{header}</div></div></header>
        {open ? <div className="fixed inset-0 z-50 lg:hidden"><button type="button" aria-label="Close navigation backdrop" onClick={() => setOpen(false)} className="absolute inset-0 bg-[#17212B]/55"/><aside role="dialog" aria-modal="true" aria-label="Administration navigation" className="relative h-full w-[min(20rem,88vw)] shadow-2xl"><button type="button" autoFocus onClick={() => setOpen(false)} aria-label="Close navigation" className="absolute right-3 top-3 z-10 grid size-10 place-items-center rounded-lg text-white focus-visible:ring-2 focus-visible:ring-[#D5A21F]"><AppIcon name="close"/></button><Sidebar onNavigate={() => setOpen(false)} activeSection={activeSection}/></aside></div> : null}
        <div id="main-content" tabIndex="-1" className="outline-none lg:ml-64">{children}</div>
    </div>;
}
