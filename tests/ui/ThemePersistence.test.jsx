import React from 'react';
import { cleanup, render } from '@testing-library/react';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import AuthenticatedLayout from '../../resources/js/Layouts/AuthenticatedLayout';

const usePage = vi.hoisted(() => vi.fn());
const applyTheme = vi.hoisted(() => vi.fn((theme) => document.documentElement.classList.toggle('dark', theme === 'dark')));
const clearTheme = vi.hoisted(() => vi.fn(() => document.documentElement.classList.remove('dark')));

vi.mock('@inertiajs/react', () => ({
    Link: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a>,
    router: { post: vi.fn() },
    usePage,
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a> }));
vi.mock('@/lib/theme', () => ({ applyTheme, clearTheme }));

afterEach(() => {
    cleanup();
    document.documentElement.classList.remove('dark');
});

beforeEach(() => {
    applyTheme.mockClear();
    clearTheme.mockClear();
    globalThis.route = (name) => name ? `/${name}` : { current: () => false };
    usePage.mockReturnValue({
        props: {
            auth: {
                global_admin: true,
                active_event: { id: '1', name: 'SIKLAB 2026', roles: [] },
                user: { name: 'Admin', email: 'admin@syntix.test', preferences: { theme: 'dark', text_size: 'default', contrast: 'default', reduce_motion: false } },
            },
            nav_badges: {},
            notifications: null,
        },
    });
});

test('keeps the authenticated theme while Inertia replaces an admin page layout', () => {
    const { unmount } = render(<AuthenticatedLayout header={<h1>Admin</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(applyTheme).toHaveBeenCalledWith('dark');
    expect(document.documentElement).toHaveClass('dark');

    unmount();

    expect(clearTheme).not.toHaveBeenCalled();
    expect(document.documentElement).toHaveClass('dark');
});
