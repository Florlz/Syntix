import React, { useEffect, useMemo, useRef, useState } from 'react';
import AppIcon from '@/Components/AppIcon';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const DEFAULT_PREFERENCES = {
    text_size: 'default',
    contrast: 'default',
    reduce_motion: false,
    default_event_id: null,
    default_landing: 'overview',
    notifications: {
        approvals: true,
        security: true,
    },
};

const field = 'mt-2 block min-h-11 w-full rounded-sm border-border bg-surface px-3.5 py-2.5 text-sm text-foreground focus:border-primary focus:ring-primary';
const primaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-sm border border-primary bg-primary px-4 py-2 text-sm font-bold text-primary-foreground transition-colors hover:bg-primary-hover focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-55 motion-reduce:transition-none';
const secondaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-sm border border-border bg-surface px-4 py-2 text-sm font-bold text-primary transition-colors hover:border-primary hover:bg-surface-muted focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-55 motion-reduce:transition-none';
const surface = 'border border-border bg-surface p-5 sm:p-6';

const SETTINGS_SECTIONS = [
    { id: 'profile', group: 'Account', icon: 'users', title: 'Profile', summary: 'Identity and account status' },
    { id: 'accessibility', group: 'Preferences', icon: 'overview', title: 'Accessibility', summary: 'Display and motion' },
    { id: 'workspace', group: 'Preferences', icon: 'calendar', title: 'Workspace', summary: 'Opening defaults' },
    { id: 'notifications', group: 'Preferences', icon: 'bell', title: 'Notifications', summary: 'Admin activity' },
    { id: 'security', group: 'Security', icon: 'shield', title: 'Security', summary: 'Password and sessions' },
];

function readSettingsSection(sections) {
    const value = new URLSearchParams(window.location.search).get('section');
    return sections.some((section) => section.id === value) ? value : 'profile';
}

function useSettingsSection(sections) {
    const [selectedSection, setSelectedSection] = useState(() => readSettingsSection(sections));

    useEffect(() => {
        const syncSection = () => setSelectedSection(readSettingsSection(sections));
        syncSection();
        const handlePopState = () => syncSection();
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, [sections]);

    const selectSection = (section) => {
        if (!sections.some((item) => item.id === section)) return;
        const url = new URL(window.location.href);
        url.searchParams.set('section', section);
        window.history.pushState({ section }, '', `${url.pathname}${url.search}${url.hash}`);
        setSelectedSection(section);
    };

    return { selectedSection, selectSection };
}

function normalizePreferences(value = {}) {
    return {
        ...DEFAULT_PREFERENCES,
        ...value,
        notifications: {
            ...DEFAULT_PREFERENCES.notifications,
            ...(value.notifications ?? {}),
        },
        default_event_id: value.default_event_id ?? null,
        reduce_motion: Boolean(value.reduce_motion),
    };
}

function StatusMessage({ visible, children, tone = 'success' }) {
    if (!visible) return null;

    return <p role="status" aria-live="polite" className={`text-sm font-semibold ${tone === 'danger' ? 'text-danger' : 'text-emerald-700'}`}>{children}</p>;
}

function SettingsSaveBar({ processing, recentlySuccessful, isDirty = true, children = 'Save changes', processingLabel = 'Saving...' }) {
    return <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-5">
        <button type="submit" className={primaryButton} disabled={!isDirty || processing}>
            {processing ? processingLabel : children}
        </button>
        <StatusMessage visible={recentlySuccessful}>Saved.</StatusMessage>
    </div>;
}

function SettingsToggle({ id, label, detail, checked, onChange, disabled = false }) {
    return <div className="flex items-center justify-between gap-4 border-b border-border py-4 last:border-b-0">
        <div>
            <label htmlFor={id} className="block text-sm font-semibold text-foreground">{label}</label>
            <p className="mt-1 text-xs leading-5 text-muted">{detail}</p>
        </div>
        <button
            id={id}
            type="button"
            role="switch"
            aria-label={label}
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onChange(!checked)}
            className={`relative h-6 w-11 shrink-0 rounded-full transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transition-none ${checked ? 'bg-primary' : 'bg-muted'}`}
        >
            <span aria-hidden="true" className={`absolute left-1 top-1 size-4 rounded-full bg-white transition-transform motion-reduce:transition-none ${checked ? 'translate-x-5' : ''}`} />
        </button>
    </div>;
}

function SectionDivider({ children }) {
    return <div className="flex items-center gap-3 pt-2">
        <span className="shrink-0 text-[0.65rem] font-bold uppercase tracking-[0.14em] text-primary">{children}</span>
        <span aria-hidden="true" className="h-px flex-1 bg-border" />
    </div>;
}

function SettingsTabs({ sections, selectedSection, selectSection }) {
    return <nav aria-label="Settings sections" className="border-b border-border">
        <div className="flex gap-1 overflow-x-auto pb-px">
            {sections.map((section) => <SettingsTab key={section.id} section={section} selectedSection={selectedSection} selectSection={selectSection} />)}
        </div>
    </nav>;
}

function SettingsTab({ section, selectedSection, selectSection }) {
    const selected = selectedSection === section.id;

    return <a
        href={`${route('settings.edit')}?section=${section.id}`}
        aria-label={`${section.title}, ${section.group}`}
        aria-current={selected ? 'page' : undefined}
        data-settings-section={section.id}
        onClick={(event) => {
            event.preventDefault();
            selectSection(section.id);
        }}
        className={`group inline-flex min-h-11 shrink-0 items-center gap-2 rounded-t-lg border-b-2 px-3 py-3 text-sm font-bold transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-background motion-reduce:transition-none ${selected ? 'border-primary bg-primary/5 text-primary' : 'border-transparent text-muted hover:bg-surface-muted hover:text-primary'}`}
    >
        <AppIcon name={section.icon} className="size-4 shrink-0" />
        <span>{section.title}</span>
    </a>;
}

function FocusedPanel({ item, active, headingRef, children }) {
    return <section
        id={`${item.id}-panel`}
        aria-labelledby={`${item.id}-heading`}
        hidden={!active}
        data-settings-panel={item.id}
        className="border-b border-border pb-10"
    >
        <div className="flex flex-wrap items-start justify-between gap-3 pt-8">
            <div>
                <p className="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-primary">{item.group}</p>
                <h2 id={`${item.id}-heading`} ref={headingRef} tabIndex={active ? -1 : undefined} className="mt-1 font-serif text-2xl font-bold text-foreground focus-visible:outline-hidden">{item.heading ?? item.title}</h2>
                <p className="mt-1 max-w-2xl text-sm leading-5 text-muted">{item.summary}.</p>
            </div>
        </div>
        <div className="space-y-6 pt-7">{children}</div>
    </section>;
}

function SettingsSurface({ title, description, children, className = '' }) {
    return <section className={`${surface} ${className}`}>
        {(title || description) ? <header className="mb-5">
            {title ? <h3 className="text-base font-bold text-foreground">{title}</h3> : null}
            {description ? <p className="mt-1 max-w-2xl text-sm leading-5 text-muted">{description}</p> : null}
        </header> : null}
        {children}
    </section>;
}

function SettingsRow({ label, detail, children }) {
    return <div className="flex flex-col gap-4 border-b border-border py-4 first:pt-0 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
            <p className="text-sm font-semibold text-foreground">{label}</p>
            {detail ? <p className="mt-1 text-xs leading-5 text-muted">{detail}</p> : null}
        </div>
        <div className="shrink-0 sm:min-w-56 sm:max-w-sm">{children}</div>
    </div>;
}

function TextField({ id, label, value, onChange, error, type = 'text', autoComplete, inputRef, required = true }) {
    const errorId = `${id}-error`;

    return <div>
        <label htmlFor={id} className="block text-sm font-semibold text-foreground">{label}</label>
        <input id={id} ref={inputRef} type={type} value={value ?? ''} onChange={(event) => onChange(event.target.value)} autoComplete={autoComplete} required={required} aria-invalid={Boolean(error)} aria-describedby={error ? errorId : undefined} className={field} />
        <InputError id={errorId} message={error} className="mt-2" />
    </div>;
}

function SelectField({ id, label, value, onChange, error, children, disabled = false }) {
    const errorId = `${id}-error`;

    return <div>
        <label htmlFor={id} className="block text-sm font-semibold text-foreground">{label}</label>
        <select id={id} value={value ?? ''} onChange={(event) => onChange(event.target.value)} disabled={disabled} aria-invalid={Boolean(error)} aria-describedby={error ? errorId : undefined} className={field}>{children}</select>
        <InputError id={errorId} message={error} className="mt-2" />
    </div>;
}

function PreferencePreview({ textSize, contrast, reduceMotion }) {
    const fontSize = { default: '1rem', large: '1.1rem', 'x-large': '1.22rem' }[textSize] ?? '1rem';
    const highContrast = contrast === 'high';

    return <div aria-label="Accessibility preview" className={`overflow-hidden rounded-lg border ${highContrast ? 'border-foreground bg-foreground text-surface' : 'border-border bg-surface-muted text-foreground'} ${reduceMotion ? '' : 'transition-colors duration-200'}`}>
        <div className="border-b border-current/15 px-4 py-2 text-[0.62rem] font-bold uppercase tracking-[0.13em] opacity-75">Live preview</div>
        <div className="space-y-2 px-4 py-3" style={{ fontSize }}><p className="text-lg font-bold">Ready for event day</p><p className="max-w-md text-sm leading-6 opacity-80">This is how text, contrast, and motion choices will feel across the admin dashboard.</p><button type="button" className={`rounded-sm border px-3 py-2 text-xs font-bold ${highContrast ? 'border-surface bg-surface text-foreground' : 'border-primary bg-primary text-primary-foreground'} transition-colors`}>Preview action</button></div>
    </div>;
}

function ProfileCard({ user, globalAdmin = false, mustVerifyEmail, status }) {
    const profile = useForm({ name: user.name ?? '', email: user.email ?? '' });

    const submit = (event) => {
        event.preventDefault();
        profile.patch(route('settings.profile.update'), { preserveScroll: true });
    };

    return <form onSubmit={submit} className={`${surface} space-y-6`}>
        <div className="rounded-lg border border-border bg-surface-muted p-4 sm:p-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <p className="truncate text-lg font-bold text-foreground">{user.name || 'Your account'}</p>
                    <p className="mt-1 truncate text-sm text-muted">{user.email}</p>
                </div>
                <div className="flex flex-wrap gap-2 sm:justify-end">
                    {globalAdmin ? <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-bold text-primary"><AppIcon name="shield" className="size-3.5" />Global Administrator</span> : null}
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-muted"><AppIcon name={user.email_verified ? 'check-circle' : 'warning'} className="size-3.5" />{user.email_verified ? 'Email verified' : 'Email not verified'}</span>
                    <span className="inline-flex items-center gap-1.5 rounded-full border border-border px-2.5 py-1 text-xs font-semibold text-muted"><span className="size-1.5 rounded-full bg-emerald-500" />{user.account_state === 'disabled' ? 'Account disabled' : 'Account active'}</span>
                </div>
            </div>
            {user.created_at ? <p className="mt-4 border-t border-border pt-3 text-xs text-muted">Member since {new Intl.DateTimeFormat(undefined, { month: 'long', year: 'numeric' }).format(new Date(user.created_at))}</p> : null}
        </div>
        <SectionDivider>Account information</SectionDivider>
        <div className="grid gap-5 md:grid-cols-2">
            <TextField id="settings-name" label="Name" value={profile.data.name} onChange={(value) => profile.setData('name', value)} error={profile.errors.name} autoComplete="name" />
            <TextField id="settings-email" label="Email" type="email" value={profile.data.email} onChange={(value) => profile.setData('email', value)} error={profile.errors.email} autoComplete="email" />
        </div>
        {mustVerifyEmail && user.email_verified === false ? <div className="border-l-4 border-amber-400 bg-amber-50 px-4 py-3 text-sm leading-5 text-amber-900">Your email is not verified. <Link href={route('verification.send')} method="post" as="button" className="font-semibold underline underline-offset-2">Send a new link</Link>{status === 'verification-link-sent' ? <span className="mt-1 block font-semibold text-emerald-700">A new link was sent.</span> : null}</div> : null}
        <SettingsSaveBar processing={profile.processing} recentlySuccessful={profile.recentlySuccessful} isDirty={profile.isDirty} />
        <InputError id="settings-profile-form-error" message={profile.errors.form} />
    </form>;
}

function PasswordCard() {
    const currentRef = useRef(null);
    const passwordRef = useRef(null);
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    const submit = (event) => {
        event.preventDefault();
        password.put(route('settings.password.update'), {
            preserveScroll: true,
            onSuccess: () => password.reset(),
            onError: (errors) => {
                if (errors.current_password) currentRef.current?.focus();
                else if (errors.password) passwordRef.current?.focus();
            },
        });
    };

    return <form onSubmit={submit} className={`${surface} space-y-6`}>
        <SectionDivider>Password</SectionDivider>
        <div className="grid gap-5 md:grid-cols-2">
            <TextField id="settings-current-password" label="Current password" type="password" inputRef={currentRef} value={password.data.current_password} onChange={(value) => password.setData('current_password', value)} error={password.errors.current_password} autoComplete="current-password" />
            <TextField id="settings-password" label="New password" type="password" inputRef={passwordRef} value={password.data.password} onChange={(value) => password.setData('password', value)} error={password.errors.password} autoComplete="new-password" />
            <TextField id="settings-password-confirmation" label="Confirm new password" type="password" value={password.data.password_confirmation} onChange={(value) => password.setData('password_confirmation', value)} error={password.errors.password_confirmation} autoComplete="new-password" />
        </div>
        <SettingsSaveBar processing={password.processing} recentlySuccessful={password.recentlySuccessful} isDirty={password.isDirty} processingLabel="Updating...">Update password</SettingsSaveBar>
    </form>;
}

function NotificationsCard({ initial }) {
    const notifications = useForm({
        approvals: initial.notifications.approvals,
        security: initial.notifications.security,
    });

    const submit = (event) => {
        event.preventDefault();
        notifications.patch(route('settings.preferences.update'), {
            preserveScroll: true,
            transform: (data) => ({
                notifications: {
                    approvals: data.approvals,
                    security: data.security,
                },
            }),
        });
    };

    return <form onSubmit={submit} className={`${surface} space-y-6`}>
        <SettingsSurface title="In-app notifications" description="Choose what appears in your admin notification center.">
            <SettingsToggle id="settings-notifications-approvals" label="Approval activity" detail="Results and final placements ready for review." checked={notifications.data.approvals} onChange={(value) => notifications.setData('approvals', value)} />
            <SettingsToggle id="settings-notifications-security" label="Security alerts" detail="Important sign-in and account activity." checked={true} onChange={() => {}} disabled />
        </SettingsSurface>
        <SettingsSurface title="Delivery" description="Email and browser push notifications are not enabled yet.">
            <SettingsRow label="In-app notifications" detail="Available now"><span className="text-sm font-semibold text-primary">Enabled</span></SettingsRow>
            <SettingsRow label="Email notifications" detail="Coming later"><span className="text-sm font-semibold text-muted">Not available</span></SettingsRow>
        </SettingsSurface>
        <SettingsSaveBar processing={notifications.processing} recentlySuccessful={notifications.recentlySuccessful} isDirty={notifications.isDirty} />
    </form>;
}

function AccessibilityCard({ initial }) {
    const preferences = useForm({
        text_size: initial.text_size,
        contrast: initial.contrast,
        reduce_motion: initial.reduce_motion,
    });

    useEffect(() => {
        const root = document.documentElement;
        root.dataset.textSize = preferences.data.text_size ?? 'default';
        root.dataset.contrast = preferences.data.contrast ?? 'default';
        root.dataset.reduceMotion = preferences.data.reduce_motion ? 'true' : 'false';
    }, [preferences.data.text_size, preferences.data.contrast, preferences.data.reduce_motion]);

    const submit = (event) => {
        event.preventDefault();
        preferences.patch(route('settings.preferences.update'), {
            preserveScroll: true,
            transform: (data) => ({
                text_size: data.text_size,
                contrast: data.contrast,
                reduce_motion: data.reduce_motion,
            }),
        });
    };

    return <form onSubmit={submit} className={`${surface} space-y-6`}>
        <SectionDivider>Display</SectionDivider>
        <div className="grid gap-5 md:grid-cols-2">
            <SelectField id="settings-text-size" label="Text size" value={preferences.data.text_size} onChange={(value) => preferences.setData('text_size', value)} error={preferences.errors.text_size}><option value="default">Default</option><option value="large">Large</option><option value="x-large">Extra large</option></SelectField>
            <SelectField id="settings-contrast" label="Contrast" value={preferences.data.contrast} onChange={(value) => preferences.setData('contrast', value)} error={preferences.errors.contrast}><option value="default">Default</option><option value="high">High contrast</option></SelectField>
        </div>
        <SectionDivider>Motion</SectionDivider>
        <SettingsToggle id="settings-reduce-motion" label="Reduce motion" detail="Use fewer moving effects and stop smooth scrolling." checked={preferences.data.reduce_motion} onChange={(value) => preferences.setData('reduce_motion', value)} />
        <PreferencePreview textSize={preferences.data.text_size} contrast={preferences.data.contrast} reduceMotion={preferences.data.reduce_motion} />
        <SettingsSaveBar processing={preferences.processing} recentlySuccessful={preferences.recentlySuccessful} isDirty={preferences.isDirty} />
    </form>;
}

function DashboardPreferencesCard({ initial, events }) {
    const dashboard = useForm({
        default_event_id: initial.default_event_id === null ? '' : String(initial.default_event_id),
        default_landing: initial.default_landing,
    });
    const eventOptions = useMemo(() => events.map((event) => ({ id: String(event.id), name: event.name, state: event.state })), [events]);

    const submit = (event) => {
        event.preventDefault();
        dashboard.patch(route('settings.preferences.update'), {
            preserveScroll: true,
            transform: (data) => ({
                default_event_id: data.default_event_id || null,
                default_landing: data.default_landing,
            }),
        });
    };

    return <form onSubmit={submit} className={`${surface} space-y-6`}>
        <SectionDivider>Opening defaults</SectionDivider>
        <SelectField id="settings-default-event" label="Default event" value={dashboard.data.default_event_id} onChange={(value) => dashboard.setData('default_event_id', value)} error={dashboard.errors.default_event_id} disabled={!eventOptions.length}>
            <option value="">Choose an event</option>
            {eventOptions.map((event) => <option key={event.id} value={event.id}>{event.name}{event.state === 'archived' ? ' — Archived' : ''}</option>)}
        </SelectField>
        {!eventOptions.length ? <p className="-mt-3 text-sm leading-5 text-muted">No events available.</p> : null}
        <SelectField id="settings-default-landing" label="First page" value={dashboard.data.default_landing} onChange={(value) => dashboard.setData('default_landing', value)} error={dashboard.errors.default_landing}><option value="overview">Overview</option><option value="sports">Sports Directory</option><option value="departments">Departments</option><option value="staff">Event Staff</option><option value="results">Results</option></SelectField>
        <SettingsSaveBar processing={dashboard.processing} recentlySuccessful={dashboard.recentlySuccessful} isDirty={dashboard.isDirty} />
    </form>;
}

function formatSessionActivity(value, isCurrent) {
    if (isCurrent) return 'Active now';

    const timestamp = Date.parse(value ?? '');
    if (Number.isNaN(timestamp)) return 'Last active recently';

    const hours = Math.max(1, Math.round((Date.now() - timestamp) / 3600000));
    if (hours < 24) return `Last active ${hours} hour${hours === 1 ? '' : 's'} ago`;
    const days = Math.round(hours / 24);
    return `Last active ${days} day${days === 1 ? '' : 's'} ago`;
}

function SessionRow({ session, onRevoke }) {
    return <div className="flex flex-col gap-4 border-b border-border py-4 first:pt-0 last:border-b-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex min-w-0 items-start gap-3">
            <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary"><AppIcon name={session.device_type === 'Mobile' ? 'mobile' : session.device_type === 'Tablet' ? 'laptop' : 'desktop'} className="size-5" /></span>
            <div className="min-w-0">
                <p className="truncate text-sm font-bold text-foreground">{session.browser}</p>
                <p className="mt-0.5 text-xs text-muted">{session.platform} · {session.device_type}</p>
                <p className="mt-1 text-xs text-muted">{formatSessionActivity(session.last_active_at, session.is_current)}{session.ip_address ? ` · ${session.ip_address}` : ''}</p>
            </div>
        </div>
        {session.is_current ? <span className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[0.65rem] font-bold uppercase tracking-[0.08em] text-primary">This device</span> : <button type="button" onClick={() => onRevoke(session)} className={secondaryButton} aria-label={`Sign out ${session.browser} session`}>Sign out</button>}
    </div>;
}

function SecurityCard({ otherSessionCount = 0, sessions = [], onRevoke = () => {} }) {
    const security = useForm({ current_password: '' });
    const sessionRevoke = useForm({ current_password: '' });
    const passwordRef = useRef(null);
    const [sessionToRevoke, setSessionToRevoke] = useState(null);

    const submit = (event) => {
        event.preventDefault();
        security.delete(route('settings.sessions.destroy'), {
            preserveScroll: true,
            onSuccess: () => security.reset(),
            onError: () => passwordRef.current?.focus(),
        });
    };

    const submitSessionRevoke = (event) => {
        event.preventDefault();
        if (!sessionToRevoke) return;

        sessionRevoke.delete(route('settings.sessions.destroy-one', { session: sessionToRevoke.key }), {
            preserveScroll: true,
            onSuccess: () => {
                sessionRevoke.reset();
                setSessionToRevoke(null);
            },
        });
    };

    return <form onSubmit={submit} className={`${surface} space-y-6`}>
        <SectionDivider>Active sessions</SectionDivider>
        {sessions.length ? <div className="rounded-lg border border-border bg-surface-muted p-4"><div className="divide-y divide-border">{sessions.map((session) => <SessionRow key={session.key} session={session} onRevoke={setSessionToRevoke} />)}</div></div> : <div className="border-l-4 border-danger bg-danger-surface px-4 py-3 text-sm leading-5 text-danger">{otherSessionCount > 0 ? [otherSessionCount, ' other ', otherSessionCount === 1 ? 'session is' : 'sessions are', ' signed in.'].join('') : 'No other sessions are signed in right now.'} Your current session stays active.</div>}
        <TextField id="settings-sessions-password" label="Current password" type="password" inputRef={passwordRef} value={security.data.current_password} onChange={(value) => security.setData('current_password', value)} error={security.errors.current_password} autoComplete="current-password" />
        <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-5"><button type="submit" className={secondaryButton} disabled={security.processing}>{security.processing ? 'Signing out...' : 'Sign out other sessions'}</button><StatusMessage visible={security.recentlySuccessful}>Other sessions ended.</StatusMessage></div>
        <Modal show={Boolean(sessionToRevoke)} maxWidth="md" onClose={() => setSessionToRevoke(null)}>
            {sessionToRevoke ? <form onSubmit={submitSessionRevoke} className="space-y-5 p-6">
                <div>
                    <p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-primary">Session security</p>
                    <h2 className="mt-1 font-serif text-xl font-bold text-foreground">Sign out {sessionToRevoke.browser} session</h2>
                    <p className="mt-2 text-sm leading-5 text-muted">Confirm your current password to sign out this device. Your current device will stay active.</p>
                </div>
                <TextField id="settings-session-revoke-password" label="Confirm your current password" type="password" value={sessionRevoke.data.current_password} onChange={(value) => sessionRevoke.setData('current_password', value)} error={sessionRevoke.errors.current_password ?? sessionRevoke.errors.session} autoComplete="current-password" />
                <div className="flex flex-wrap justify-end gap-3 border-t border-border pt-5">
                    <button type="button" onClick={() => setSessionToRevoke(null)} className={secondaryButton}>Cancel</button>
                    <button type="submit" className={primaryButton} disabled={!sessionRevoke.isDirty || sessionRevoke.processing}>{sessionRevoke.processing ? 'Signing out...' : 'Sign out session'}</button>
                </div>
            </form> : null}
        </Modal>
    </form>;
}

export default function Settings({ events: passedEvents = [], available_events: availableEvents = [], preferences: passedPreferences = {}, mustVerifyEmail = false, other_session_count: otherSessionCount = 0, sessions: passedSessions = [], status = null }) {
    const page = usePage();
    const auth = page.props.auth ?? {};
    const user = auth.user ?? {};
    const initial = normalizePreferences({ ...(auth.preferences ?? {}), ...passedPreferences });
    const events = passedEvents.length ? passedEvents : availableEvents;
    const sections = useMemo(
        () => SETTINGS_SECTIONS.filter((section) => section.id !== 'notifications' || auth.global_admin),
        [auth.global_admin],
    );
    const { selectedSection, selectSection } = useSettingsSection(sections);
    const selectedHeadingRef = useRef(null);
    const panelContent = {
        profile: <ProfileCard user={user} globalAdmin={auth.global_admin} mustVerifyEmail={mustVerifyEmail} status={status} />,
        accessibility: <AccessibilityCard initial={initial} />,
        workspace: <DashboardPreferencesCard initial={initial} events={events} />,
        notifications: <NotificationsCard initial={initial} />,
        security: <div className="space-y-8"><PasswordCard /><SecurityCard otherSessionCount={otherSessionCount} sessions={passedSessions} /></div>,
    };

    return <AuthenticatedLayout header={<div className="flex items-center gap-2 text-sm">
        <span className="font-semibold text-muted">Account</span>
        <span aria-hidden="true" className="text-border">/</span>
        <span className="font-semibold text-foreground">Settings</span>
    </div>}>
        <Head title="Settings" />
        <main className="min-h-[calc(100vh-4rem)] overflow-x-hidden bg-background p-4 text-foreground sm:p-7 lg:p-8"><div className="mx-auto max-w-5xl">
            <section className="border-b border-border pb-7">
                <h1 className="font-serif text-3xl font-bold text-foreground">Settings</h1>
                <p className="mt-2 max-w-xl text-sm leading-6 text-muted">Manage your account and event-day preferences.</p>
            </section>
            <SettingsTabs sections={sections} selectedSection={selectedSection} selectSection={selectSection} />
            <div>
                {sections.map((section) => <FocusedPanel key={section.id} item={section} active={selectedSection === section.id} headingRef={selectedSection === section.id ? selectedHeadingRef : undefined}>{panelContent[section.id]}</FocusedPanel>)}
            </div>
        </div></main>
    </AuthenticatedLayout>;
}
