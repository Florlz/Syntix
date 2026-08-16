import React, { useEffect, useMemo, useRef, useState } from 'react';
import AppIcon from '@/Components/AppIcon';
import InputError from '@/Components/InputError';
import Link from '@/Components/PrefetchLink';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

const DEFAULT_PREFERENCES = {
    text_size: 'default',
    contrast: 'default',
    reduce_motion: false,
    default_event_id: null,
    default_landing: 'overview',
};

const panel = 'rounded-2xl border border-[#D8DEDC] bg-white shadow-[0_10px_28px_rgba(23,33,43,0.05)]';
const field = 'mt-2 block w-full rounded-lg border-[#C8D2CF] bg-white px-3.5 py-2.5 text-sm text-[#17212B] shadow-sm focus:border-[#0B536D] focus:ring-[#0B536D]';
const primaryButton = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#0B536D] px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-[#083F53] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-55 motion-reduce:transition-none';
const secondaryButton = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-[#B8C3C0] bg-white px-4 py-2 text-sm font-bold text-[#0B536D] transition hover:border-[#0B536D] hover:bg-[#F4F8F8] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-55 motion-reduce:transition-none';

const SETTINGS_SECTIONS = [
    { id: 'profile', group: 'Account', icon: 'users', title: 'Profile', summary: 'Name and email' },
    { id: 'accessibility', group: 'Preferences', icon: 'overview', title: 'Accessibility', summary: 'Display and motion' },
    { id: 'workspace', group: 'Preferences', icon: 'calendar', title: 'Workspace', summary: 'Opening defaults' },
    { id: 'security', group: 'Security', icon: 'settings', title: 'Password & sessions', heading: 'Security', summary: 'Sign-in safety' },
];

const SETTINGS_SECTION_IDS = SETTINGS_SECTIONS.map((section) => section.id);

function readSettingsSection() {
    const value = new URLSearchParams(window.location.search).get('section');
    return SETTINGS_SECTION_IDS.includes(value) ? value : 'profile';
}

function useSettingsSection() {
    const [selectedSection, setSelectedSection] = useState(readSettingsSection);

    useEffect(() => {
        const handlePopState = () => setSelectedSection(readSettingsSection());
        window.addEventListener('popstate', handlePopState);
        return () => window.removeEventListener('popstate', handlePopState);
    }, []);

    const selectSection = (section) => {
        if (!SETTINGS_SECTION_IDS.includes(section)) return;
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
        default_event_id: value.default_event_id ?? null,
        reduce_motion: Boolean(value.reduce_motion),
    };
}

function StatusMessage({ visible, children, tone = 'success' }) {
    if (!visible) return null;

    return <p role="status" className={`text-sm font-semibold ${tone === 'danger' ? 'text-rose-700' : 'text-emerald-700'}`}>{children}</p>;
}

function SaveButton({ processing, children = 'Save changes' }) {
    return <button type="submit" className={primaryButton} disabled={processing}>{processing ? 'Saving...' : children}</button>;
}

function Card({ id, icon, eyebrow, title, description, children, tone = 'default' }) {
    return <section aria-labelledby={id} className={`${panel} overflow-hidden ${tone === 'danger' ? 'border-rose-200' : ''}`}>
        <div className="border-b border-[#E6EAE8] px-5 py-5 sm:px-6">
            <div className="flex items-start gap-3">
                <span className={`grid size-10 shrink-0 place-items-center rounded-xl ${tone === 'danger' ? 'bg-rose-50 text-rose-700' : 'bg-[#EAF1F5] text-[#0B536D]'}`}><AppIcon name={icon} className="size-5" /></span>
                <div className="min-w-0"><p className="text-[0.65rem] font-bold uppercase tracking-[0.14em] text-[#0B536D]">{eyebrow}</p><h3 id={id} className="mt-1 font-serif text-2xl font-bold text-[#17212B]">{title}</h3><p className="mt-1 max-w-xl text-sm leading-5 text-[#68767E]">{description}</p></div>
            </div>
        </div>
        <div className="px-5 py-5 sm:px-6">{children}</div>
    </section>;
}

function SettingsTabs({ selectedSection, selectSection }) {
    return <nav aria-label="Settings sections" className="border-b border-[#C8D2CF]">
        <div className="hidden gap-7 pb-2 md:flex">
            {[...new Set(SETTINGS_SECTIONS.map((section) => section.group))].map((group) => <p key={group} className="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-[#78858A]">{group}</p>)}
        </div>
        <div className="flex gap-5 overflow-x-auto">
            {SETTINGS_SECTIONS.map((section) => <SettingsTab key={section.id} section={section} selectedSection={selectedSection} selectSection={selectSection} />)}
        </div>
    </nav>;
}

function SettingsTab({ section, selectedSection, selectSection }) {
    const selected = selectedSection === section.id;

    return <a
        href={`${route('settings.edit')}?section=${section.id}`}
        aria-current={selected ? 'page' : undefined}
        data-settings-section={section.id}
        onClick={(event) => {
            event.preventDefault();
            selectSection(section.id);
        }}
        className={`group inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-1 py-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#D5A21F] focus-visible:ring-offset-2 motion-reduce:transition-none ${selected ? 'border-[#0B536D] text-[#0B536D]' : 'border-transparent text-[#68767E] hover:border-[#B8C3C0] hover:text-[#0B536D]'}`}
    >
        <AppIcon name={section.icon} className="size-4 shrink-0" />
        <span>{section.title}</span>
        <span className="sr-only">{section.group}</span>
    </a>;
}

function FocusedPanel({ item, active, headingRef, children }) {
    return <section
        id={`${item.id}-panel`}
        aria-labelledby={`${item.id}-heading`}
        hidden={!active}
        data-settings-panel={item.id}
        className="border-b border-[#E6EAE8] pb-8"
    >
        <div className="flex flex-wrap items-start justify-between gap-3 pt-6">
            <div>
                <p className="text-[0.62rem] font-bold uppercase tracking-[0.14em] text-[#0B536D]">{item.group}</p>
                <h2 id={`${item.id}-heading`} ref={headingRef} tabIndex={active ? -1 : undefined} className="mt-1 font-serif text-2xl font-bold text-[#17212B] focus-visible:outline-none">{item.heading ?? item.title}</h2>
                <p className="mt-1 max-w-2xl text-sm leading-5 text-[#68767E]">{item.summary}.</p>
            </div>
        </div>
        <div className="space-y-5 pt-6">{children}</div>
    </section>;
}

function TextField({ id, label, value, onChange, error, type = 'text', autoComplete, inputRef, required = true }) {
    return <div>
        <label htmlFor={id} className="block text-sm font-semibold text-[#17212B]">{label}</label>
        <input id={id} ref={inputRef} type={type} value={value ?? ''} onChange={(event) => onChange(event.target.value)} autoComplete={autoComplete} required={required} className={field} />
        <InputError message={error} className="mt-2" />
    </div>;
}

function SelectField({ id, label, value, onChange, error, children, disabled = false }) {
    return <div>
        <label htmlFor={id} className="block text-sm font-semibold text-[#17212B]">{label}</label>
        <select id={id} value={value ?? ''} onChange={(event) => onChange(event.target.value)} disabled={disabled} className={field}>{children}</select>
        <InputError message={error} className="mt-2" />
    </div>;
}

function CheckField({ id, label, detail, checked, onChange }) {
    return <label htmlFor={id} className="flex cursor-pointer items-start gap-3 rounded-xl border border-[#D8DEDC] bg-[#FAFBF9] p-3.5 transition hover:border-[#0B536D] focus-within:border-[#0B536D] focus-within:ring-2 focus-within:ring-[#D5A21F]/60 motion-reduce:transition-none">
        <input id={id} type="checkbox" checked={checked} onChange={(event) => onChange(event.target.checked)} className="mt-0.5 size-4 rounded border-[#AAB8B4] text-[#0B536D] focus:ring-[#D5A21F]" />
        <span className="min-w-0"><span className="block text-sm font-semibold text-[#17212B]">{label}</span><span className="mt-0.5 block text-xs leading-5 text-[#68767E]">{detail}</span></span>
    </label>;
}

function PreferencePreview({ textSize, contrast, reduceMotion }) {
    const fontSize = { default: '1rem', large: '1.1rem', 'x-large': '1.22rem' }[textSize] ?? '1rem';
    const highContrast = contrast === 'high';

    return <div aria-label="Accessibility preview" className={`mt-5 overflow-hidden rounded-xl border ${highContrast ? 'border-[#17212B] bg-[#17212B] text-white' : 'border-[#C8D2CF] bg-[#F4F8F8] text-[#17212B]'} ${reduceMotion ? '' : 'transition-colors duration-200'}`}>
        <div className="border-b border-current/15 px-4 py-2.5 text-[0.65rem] font-bold uppercase tracking-[0.13em] opacity-75">Live preview</div>
        <div className="space-y-2 px-4 py-4" style={{ fontSize }}><p className="font-serif text-lg font-bold">Ready for event day</p><p className="max-w-md text-sm leading-6 opacity-80">This is how text, contrast, and motion choices will feel across the admin dashboard.</p><button type="button" className={`rounded-lg px-3 py-2 text-xs font-bold ${highContrast ? 'bg-white text-[#17212B]' : 'bg-[#0B536D] text-white'} ${reduceMotion ? '' : 'transition-transform duration-200 hover:-translate-y-0.5'} motion-reduce:transform-none`}>Preview action</button></div>
    </div>;
}

function ProfileCard({ user, mustVerifyEmail, status }) {
    const profile = useForm({ name: user.name ?? '', email: user.email ?? '' });

    const submit = (event) => {
        event.preventDefault();
        profile.patch(route('settings.profile.update'), { preserveScroll: true });
    };

    return <Card id="profile-title" icon="users" eyebrow="Your account" title="Profile" description="Keep your name and email up to date.">
        <form onSubmit={submit} className="space-y-4">
            <TextField id="settings-name" label="Name" value={profile.data.name} onChange={(value) => profile.setData('name', value)} error={profile.errors.name} autoComplete="name" />
            <TextField id="settings-email" label="Email" type="email" value={profile.data.email} onChange={(value) => profile.setData('email', value)} error={profile.errors.email} autoComplete="email" />
            {mustVerifyEmail && user.email_verified === false ? <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm leading-5 text-amber-900">Your email is not verified. <Link href={route('verification.send')} method="post" as="button" className="font-semibold underline underline-offset-2">Send a new link</Link>{status === 'verification-link-sent' ? <span className="mt-1 block font-semibold text-emerald-700">A new link was sent.</span> : null}</div> : null}
            <div className="flex flex-wrap items-center gap-3 pt-1"><SaveButton processing={profile.processing} /><StatusMessage visible={profile.recentlySuccessful}>Saved.</StatusMessage></div>
            <InputError message={profile.errors.form} />
        </form>
    </Card>;
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

    return <Card id="password-title" icon="settings" eyebrow="Keep it safe" title="Password" description="Use a new password if you think your account may be at risk.">
        <form onSubmit={submit} className="space-y-4">
            <TextField id="settings-current-password" label="Current password" type="password" inputRef={currentRef} value={password.data.current_password} onChange={(value) => password.setData('current_password', value)} error={password.errors.current_password} autoComplete="current-password" />
            <TextField id="settings-password" label="New password" type="password" inputRef={passwordRef} value={password.data.password} onChange={(value) => password.setData('password', value)} error={password.errors.password} autoComplete="new-password" />
            <TextField id="settings-password-confirmation" label="Confirm new password" type="password" value={password.data.password_confirmation} onChange={(value) => password.setData('password_confirmation', value)} error={password.errors.password_confirmation} autoComplete="new-password" />
            <div className="flex flex-wrap items-center gap-3 pt-1"><SaveButton processing={password.processing} /><StatusMessage visible={password.recentlySuccessful}>Password updated.</StatusMessage></div>
        </form>
    </Card>;
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

    return <Card id="accessibility-title" icon="overview" eyebrow="Make it comfortable" title="Accessibility" description="Choose settings that make the dashboard easier to read and use.">
        <form onSubmit={submit}>
            <div className="space-y-4">
                <SelectField id="settings-text-size" label="Text size" value={preferences.data.text_size} onChange={(value) => preferences.setData('text_size', value)} error={preferences.errors.text_size}><option value="default">Default</option><option value="large">Large</option><option value="x-large">Extra large</option></SelectField>
                <SelectField id="settings-contrast" label="Contrast" value={preferences.data.contrast} onChange={(value) => preferences.setData('contrast', value)} error={preferences.errors.contrast}><option value="default">Default</option><option value="high">High contrast</option></SelectField>
                <CheckField id="settings-reduce-motion" label="Reduce motion" detail="Use fewer moving effects and stop smooth scrolling." checked={preferences.data.reduce_motion} onChange={(value) => preferences.setData('reduce_motion', value)} />
            </div>
            <PreferencePreview textSize={preferences.data.text_size} contrast={preferences.data.contrast} reduceMotion={preferences.data.reduce_motion} />
            <div className="mt-5 flex flex-wrap items-center gap-3"><SaveButton processing={preferences.processing} /><StatusMessage visible={preferences.recentlySuccessful}>Saved.</StatusMessage></div>
        </form>
    </Card>;
}

function DashboardPreferencesCard({ initial, events }) {
    const dashboard = useForm({
        default_event_id: initial.default_event_id === null ? '' : String(initial.default_event_id),
        default_landing: initial.default_landing,
    });
    const eventOptions = useMemo(() => events.map((event) => ({ id: String(event.id), name: event.name })), [events]);

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

    return <Card id="dashboard-preferences-title" icon="calendar" eyebrow="Start in the right place" title="Dashboard preferences" description="Choose the event and page you want to see first.">
        <form onSubmit={submit} className="space-y-4">
            <SelectField id="settings-default-event" label="Default event" value={dashboard.data.default_event_id} onChange={(value) => dashboard.setData('default_event_id', value)} error={dashboard.errors.default_event_id} disabled={!eventOptions.length}><option value="">Choose an event</option>{eventOptions.map((event) => <option key={event.id} value={event.id}>{event.name}</option>)}</SelectField>
            {!eventOptions.length ? <p className="-mt-2 text-xs leading-5 text-[#68767E]">You do not have any events to choose from yet.</p> : null}
            <SelectField id="settings-default-landing" label="First page" value={dashboard.data.default_landing} onChange={(value) => dashboard.setData('default_landing', value)} error={dashboard.errors.default_landing}><option value="overview">Overview</option><option value="sports">Sports Directory</option><option value="departments">Departments</option><option value="staff">Event Staff</option><option value="results">Results</option></SelectField>
            <div className="flex flex-wrap items-center gap-3 pt-1"><SaveButton processing={dashboard.processing} /><StatusMessage visible={dashboard.recentlySuccessful}>Saved.</StatusMessage></div>
        </form>
    </Card>;
}

function SecurityCard({ otherSessionCount = 0 }) {
    const security = useForm({ current_password: '' });
    const passwordRef = useRef(null);

    const submit = (event) => {
        event.preventDefault();
        security.delete(route('settings.sessions.destroy'), {
            preserveScroll: true,
            onSuccess: () => security.reset(),
            onError: () => passwordRef.current?.focus(),
        });
    };

    return <Card id="security-title" icon="logout" eyebrow="Sign-in safety" title="Security" description="Sign out other devices while keeping this device signed in.">
        <form onSubmit={submit} className="space-y-4">
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-sm leading-5 text-amber-900">{otherSessionCount > 0 ? [otherSessionCount, ' other ', otherSessionCount === 1 ? 'session is' : 'sessions are', ' signed in.'].join('') : 'No other sessions are signed in right now.'} Your current session stays active.</div>
            <TextField id="settings-sessions-password" label="Current password" type="password" inputRef={passwordRef} value={security.data.current_password} onChange={(value) => security.setData('current_password', value)} error={security.errors.current_password} autoComplete="current-password" />
            <div className="flex flex-wrap items-center gap-3 pt-1"><button type="submit" className={secondaryButton} disabled={security.processing}>{security.processing ? 'Signing out...' : 'Sign out other sessions'}</button><StatusMessage visible={security.recentlySuccessful}>Other sessions ended.</StatusMessage></div>
        </form>
    </Card>;
}

export default function Settings({ events: passedEvents = [], available_events: availableEvents = [], preferences: passedPreferences = {}, mustVerifyEmail = false, other_session_count: otherSessionCount = 0, status = null }) {
    const page = usePage();
    const auth = page.props.auth ?? {};
    const user = auth.user ?? {};
    const initial = normalizePreferences({ ...(auth.preferences ?? {}), ...passedPreferences });
    const events = passedEvents.length ? passedEvents : availableEvents;
    const { selectedSection, selectSection } = useSettingsSection();
    const selectedHeadingRef = useRef(null);

    return <AuthenticatedLayout header={<div><p className="text-[0.68rem] font-bold uppercase tracking-[0.15em] text-[#0B536D]">Your account</p><h1 className="font-serif text-2xl font-bold">Settings</h1></div>}>
        <Head title="Settings" />
        <main className="min-h-[calc(100vh-4rem)] overflow-x-hidden p-4 sm:p-7 lg:p-8"><div className="mx-auto max-w-6xl space-y-6">
            <section className="relative overflow-hidden rounded-2xl bg-[#0B2E4F] px-5 py-7 text-white shadow-[0_16px_34px_rgba(11,46,79,0.18)] sm:px-8 sm:py-9"><div className="absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_top_right,rgba(213,162,31,0.2),transparent_58%)]" aria-hidden="true" /><div className="relative max-w-2xl"><p className="text-xs font-bold uppercase tracking-[0.16em] text-[#E7C865]">Your account</p><h2 className="mt-2 font-serif text-3xl font-bold sm:text-4xl">Make Syntix work for you.</h2><p className="mt-3 text-sm leading-6 text-white/75">Change your profile, password, and dashboard choices here. These settings belong to you, not to one event.</p></div></section>
            <section aria-label="Current preferences" className={`${panel} overflow-hidden`}><dl className="grid grid-cols-1 divide-y divide-[#E6EAE8] sm:grid-cols-3 sm:divide-x sm:divide-y-0"><div className="min-w-0 px-4 py-4 sm:px-5"><dt className="text-[0.62rem] font-bold uppercase tracking-[0.1em] text-[#78858A]">Signed in as</dt><dd className="mt-1 truncate text-sm font-semibold text-[#0B2E4F]">{user.email ?? 'Your account'}</dd></div><div className="min-w-0 px-4 py-4 sm:px-5"><dt className="text-[0.62rem] font-bold uppercase tracking-[0.1em] text-[#78858A]">Text size</dt><dd className="mt-1 text-sm font-semibold text-[#0B2E4F]">{initial.text_size === 'x-large' ? 'Extra large' : initial.text_size === 'large' ? 'Large' : 'Default'}</dd></div><div className="min-w-0 px-4 py-4 sm:px-5"><dt className="text-[0.62rem] font-bold uppercase tracking-[0.1em] text-[#78858A]">Starts at</dt><dd className="mt-1 text-sm font-semibold text-[#0B2E4F]">{initial.default_landing === 'overview' ? 'Overview' : initial.default_landing}</dd></div></dl></section>
            <SettingsTabs selectedSection={selectedSection} selectSection={selectSection} />
            <div className="space-y-5">
                <FocusedPanel item={SETTINGS_SECTIONS[0]} active={selectedSection === 'profile'} headingRef={selectedSection === 'profile' ? selectedHeadingRef : undefined}><ProfileCard user={user} mustVerifyEmail={mustVerifyEmail} status={status} /></FocusedPanel>
                <FocusedPanel item={SETTINGS_SECTIONS[1]} active={selectedSection === 'accessibility'} headingRef={selectedSection === 'accessibility' ? selectedHeadingRef : undefined}><AccessibilityCard initial={initial} /></FocusedPanel>
                <FocusedPanel item={SETTINGS_SECTIONS[2]} active={selectedSection === 'workspace'} headingRef={selectedSection === 'workspace' ? selectedHeadingRef : undefined}><DashboardPreferencesCard initial={initial} events={events} /></FocusedPanel>
                <FocusedPanel item={SETTINGS_SECTIONS[3]} active={selectedSection === 'security'} headingRef={selectedSection === 'security' ? selectedHeadingRef : undefined}><div className="space-y-5"><PasswordCard /><SecurityCard otherSessionCount={otherSessionCount} /></div></FocusedPanel>
            </div>
        </div></main>
    </AuthenticatedLayout>;
}
