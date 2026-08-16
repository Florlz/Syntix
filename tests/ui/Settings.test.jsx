import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
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

test('renders the account hub with profile selected and applies saved preferences', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }, { id: 2, name: 'Freshers Cup' }]} />);

    expect(screen.getByRole('tablist', { name: 'Settings categories' })).toBeInTheDocument();
    expect(screen.getAllByRole('tab')).toHaveLength(4);
    expect(screen.getByRole('tab', { name: /Profile/ })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', 'profile-panel');
    expect(screen.getByRole('heading', { name: 'Profile', level: 2 })).toBeInTheDocument();
    expect(screen.getByLabelText('Text size')).toHaveValue('large');
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

    const accessibilityTab = screen.getByRole('tab', { name: /Accessibility/ });
    fireEvent.click(accessibilityTab);
    expect(accessibilityTab).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('tabpanel')).toHaveAttribute('id', 'accessibility-panel');

    fireEvent.change(screen.getByLabelText('Text size'), { target: { value: 'x-large' } });
    fireEvent.click(screen.getByRole('tab', { name: /Profile/ }));
    fireEvent.click(accessibilityTab);
    expect(screen.getByLabelText('Text size')).toHaveValue('x-large');
});

test('keyboard selection focuses the newly opened panel heading', async () => {
    const { default: Settings } = await import('../../resources/js/Pages/Settings/Index');

    render(<Settings events={[{ id: 1, name: 'SIKLAB 2026' }]} />);

    const workspaceTab = screen.getByRole('tab', { name: /Workspace/ });
    workspaceTab.focus();
    fireEvent.keyDown(workspaceTab, { key: 'Enter', code: 'Enter' });
    fireEvent.click(workspaceTab, { detail: 0 });

    expect(screen.getByRole('heading', { name: 'Workspace' })).toHaveFocus();
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

    fireEvent.click(screen.getByRole('tab', { name: /Accessibility/ }));
    fireEvent.change(screen.getByLabelText('Text size'), { target: { value: 'x-large' } });
    const accessibilityForm = screen.getByLabelText('Text size').closest('form');
    fireEvent.submit(accessibilityForm);

    const accessibilityRequest = forms[1].patch.mock.calls.at(-1)[1];
    expect(accessibilityRequest.transform(forms[1].data)).toEqual({
        text_size: 'x-large',
        contrast: 'high',
        reduce_motion: true,
    });

    fireEvent.click(screen.getByRole('tab', { name: /Workspace/ }));
    const workspaceForm = screen.getByLabelText('Default event', { selector: 'select', hidden: true }).closest('form');
    fireEvent.submit(workspaceForm);

    const workspaceRequest = forms[2].patch.mock.calls.at(-1)[1];
    expect(workspaceRequest.transform(forms[2].data)).toEqual({
        default_event_id: '2',
        default_landing: 'sports',
    });
});
