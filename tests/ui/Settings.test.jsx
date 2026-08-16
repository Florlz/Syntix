import React from 'react';
import { act, fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, expect, test, vi } from 'vitest';

const forms = [];

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({
        props: {
            auth: {
                user: { name: 'Admin User', email: 'admin@syntix.test', email_verified: false, email_verified_at: '2026-01-01' },
                preferences: { text_size: 'large', contrast: 'high', reduce_motion: true, default_event_id: 2, default_landing: 'sports' },
            },
        },
    }),
    useForm: (initial) => {
        const [, rerender] = React.useReducer((value) => value + 1, 0);
        const formRef = React.useRef(null);
        if (!formRef.current) {
            const form = {
                data: { ...initial },
                errors: {},
                processing: false,
                recentlySuccessful: false,
                setData: (key, value) => {
                    form.data = { ...form.data, [key]: value };
                    rerender();
                },
                patch: vi.fn(),
                put: vi.fn(),
                delete: vi.fn(),
                reset: vi.fn(),
                setProcessing: (value) => {
                    form.processing = value;
                    rerender();
                },
            };
            formRef.current = form;
            forms.push(form);
        }
        return formRef.current;
    },
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ header, children }) => <div><header>{header}</header>{children}</div>,
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/InputError', () => ({ default: ({ message }) => message ? <p role="alert">{message}</p> : null }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ children, ...props }) => <button {...props}>{children}</button> }));

beforeEach(() => {
    window.history.replaceState({}, '', '/settings');
    forms.length = 0;
    globalThis.route = (name) => name === 'settings.edit' ? '/settings' : `/${name}`;
    document.documentElement.removeAttribute('data-text-size');
    document.documentElement.removeAttribute('data-contrast');
    document.documentElement.removeAttribute('data-reduce-motion');
});

test('opens the section named by the settings query string', async () => {
    window.history.replaceState({}, '', '/settings?section=security');
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);

    expect(screen.getByRole('link', { name: /Password & sessions/i })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('heading', { name: 'Security', level: 2 })).toBeVisible();
});

test('selecting a settings tab updates the query string and preserves entered values', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026', state: 'preparation' }]} />);
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'Updated name' } });
    fireEvent.click(screen.getByRole('link', { name: /Workspace/i }));
    fireEvent.click(screen.getByRole('link', { name: /Profile/i }));

    expect(window.location.search).toBe('?section=profile');
    expect(screen.getByLabelText('Name')).toHaveValue('Updated name');
});

test('uses a compact settings header instead of the dashboard hero and summary strip', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);

    expect(screen.getByRole('heading', { name: 'Settings', level: 1 })).toBeInTheDocument();
    expect(screen.getByText('Manage your account, preferences, and security.')).toBeInTheDocument();
    expect(screen.queryByText('Make Syntix work for you.')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Current preferences')).not.toBeInTheDocument();
});

test('renders Reduce Motion as a switch and updates its checked state', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);
    fireEvent.click(screen.getByRole('link', { name: /Accessibility/i }));

    const toggle = screen.getByRole('switch', { name: 'Reduce motion' });
    expect(toggle).toHaveAttribute('aria-checked', 'true');
    fireEvent.click(toggle);
    expect(toggle).toHaveAttribute('aria-checked', 'false');
});

test('labels archived and empty workspace states without blocking account settings', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2025', state: 'archived' }]} />);
    fireEvent.click(screen.getByRole('link', { name: /Workspace/i }));
    expect(screen.getByRole('option', { name: /SIKLAB 2025.*Archived/i })).toBeInTheDocument();
});

test('shows an empty workspace state without blocking account settings', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);
    fireEvent.click(screen.getByRole('link', { name: /Workspace/i }));
    expect(screen.getByText('No events available.')).toBeInTheDocument();
    expect(screen.getByLabelText('First page')).toBeEnabled();
});

test('shows the saving label and disables the active section action while processing', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[]} />);
    act(() => forms[0].setProcessing(true));

    expect(screen.getByRole('button', { name: 'Saving...' })).toBeDisabled();
});

test('renders the settings navigation with profile selected and applies saved preferences', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }, { id: 2, name: 'Freshers Cup' }]} />);

    expect(screen.getByRole('navigation', { name: 'Settings sections' })).toBeInTheDocument();
    expect(screen.getAllByRole('link')).toHaveLength(4);
    expect(screen.getByRole('link', { name: /Profile/ })).toHaveAttribute('aria-current', 'page');
    expect(document.querySelector('[data-settings-panel="profile"]')).not.toHaveAttribute('hidden');
    expect(screen.getByRole('heading', { name: 'Profile', level: 2 })).toBeInTheDocument();
    expect(screen.getByLabelText('Text size', { selector: 'select', hidden: true })).toHaveValue('large');
    expect(document.documentElement).toHaveAttribute('data-text-size', 'large');
    expect(document.documentElement).toHaveAttribute('data-contrast', 'high');
    expect(document.documentElement).toHaveAttribute('data-reduce-motion', 'true');
});

test('shows the verification prompt from the shared verification flag', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings mustVerifyEmail events={[]} />);

    expect(screen.getByText('Your email is not verified.')).toBeInTheDocument();
});

test('switches focused panels while preserving entered values', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }]} />);

    const accessibilityLink = screen.getByRole('link', { name: /Accessibility/ });
    fireEvent.click(accessibilityLink);
    expect(accessibilityLink).toHaveAttribute('aria-current', 'page');
    expect(document.querySelector('[data-settings-panel="accessibility"]')).not.toHaveAttribute('hidden');

    fireEvent.change(screen.getByLabelText('Text size'), { target: { value: 'x-large' } });
    fireEvent.click(screen.getByRole('link', { name: /Profile/ }));
    fireEvent.click(accessibilityLink);
    expect(screen.getByLabelText('Text size')).toHaveValue('x-large');
});

test('selecting a settings link opens its matching panel', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }]} />);

    const workspaceLink = screen.getByRole('link', { name: /Workspace/ });
    fireEvent.click(workspaceLink);

    expect(workspaceLink).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('heading', { name: 'Workspace' })).toBeVisible();
});

test('each card uses its own approved settings action', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }]} />);
    fireEvent.submit(screen.getByLabelText('Name').closest('form'));
    fireEvent.submit(screen.getByLabelText('New password', { selector: 'input', hidden: true }).closest('form'));
    fireEvent.submit(screen.getByLabelText('Text size', { selector: 'select', hidden: true }).closest('form'));
    fireEvent.submit(screen.getByLabelText('Default event', { selector: 'select', hidden: true }).closest('form'));
    fireEvent.submit(screen.getByRole('button', { name: 'Sign out other sessions', hidden: true }).closest('form'));

    expect(forms[0].patch).toHaveBeenCalledWith('/settings.profile.update', { preserveScroll: true });
    expect(forms[3].put).toHaveBeenCalledWith('/settings.password.update', expect.objectContaining({ preserveScroll: true }));
    expect(forms[1].patch).toHaveBeenCalledWith('/settings.preferences.update', expect.objectContaining({ preserveScroll: true }));
    expect(forms[2].patch).toHaveBeenCalledWith('/settings.preferences.update', expect.objectContaining({ preserveScroll: true }));
    expect(forms[4].delete).toHaveBeenCalledWith('/settings.sessions.destroy', expect.objectContaining({ preserveScroll: true }));
});

test('accessibility and workspace saves submit only their own preference fields', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }, { id: 2, name: 'Freshers Cup' }]} />);

    fireEvent.click(screen.getByRole('link', { name: /Accessibility/ }));
    fireEvent.change(screen.getByLabelText('Text size'), { target: { value: 'x-large' } });
    const accessibilityForm = screen.getByLabelText('Text size').closest('form');
    fireEvent.submit(accessibilityForm);

    const accessibilityRequest = forms[1].patch.mock.calls.at(-1)[1];
    expect(accessibilityRequest.transform(forms[1].data)).toEqual({
        text_size: 'x-large',
        contrast: 'high',
        reduce_motion: true,
    });

    fireEvent.click(screen.getByRole('link', { name: /Workspace/ }));
    const workspaceForm = screen.getByLabelText('Default event', { selector: 'select' }).closest('form');
    fireEvent.submit(workspaceForm);

    const workspaceRequest = forms[2].patch.mock.calls.at(-1)[1];
    expect(workspaceRequest.transform(forms[2].data)).toEqual({
        default_event_id: '2',
        default_landing: 'sports',
    });
});
