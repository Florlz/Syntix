import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import { router } from '@inertiajs/react';
import AuthenticatedLayout from '../../resources/js/Layouts/AuthenticatedLayout';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { global_admin: true, active_event: { id: '1', name: 'SIKLAB 2026' }, user: { name: 'Admin', email: 'admin@syntix.test' }, preferences: { theme: 'dark', text_size: 'default', contrast: 'default', reduce_motion: false } }, nav_badges: {}, notifications: { unread_count: 3, recent: [{ id: 'n1', kind: 'approval_result', title: 'Result ready for review', message: 'Basketball Men · Semifinal', action: { label: 'Review result', route: 'admin.approvals.index', params: { event: '1' } }, read_at: null, created_at: '2026-08-16T04:00:00+00:00' }] } } }),
    router: { post: vi.fn(), visit: vi.fn() },
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, method: _method, as: _as, ...props }) => <a href={href} {...props}>{children}</a> }));

beforeEach(() => {
    document.documentElement.classList.remove('dark');
    globalThis.route = (name, params) => {
        if (!name) return { current: (pattern) => pattern === 'admin.sports.*' };
        if (name === 'dashboard') return '/dashboard';
        if (name === 'admin.sports.index') return `/admin/events/${params}/sports`;
        if (name === 'admin.departments.index') return `/admin/events/${params}/departments`;
        if (name === 'admin.staff.index') return `/admin/events/${params}/staff`;
        if (name === 'admin.approvals.index') return `/admin/events/${params.event}/approvals`;
        if (name === 'notifications.read') return `/notifications/${params.notification}/read`;
        return `/${name}`;
    };
    router.post.mockReset();
    router.visit.mockReset();
});

test('applies the persisted dark theme to the document root', () => {
    render(<AuthenticatedLayout header={<h1>Settings</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(document.documentElement).toHaveClass('dark');
});

test('cleans the admin theme from the document root when the layout unmounts', () => {
    const { unmount } = render(<AuthenticatedLayout header={<h1>Settings</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(document.documentElement).toHaveClass('dark');
    unmount();

    expect(document.documentElement).not.toHaveClass('dark');
    expect(document.documentElement.style.colorScheme).toBe('');
});

test('activeSection override marks Departments instead of route-derived Sports Directory', () => {
    render(<AuthenticatedLayout activeSection="departments" header={<h1>Roster</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(screen.getByRole('link', { name: 'Departments' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Sports Directory' })).not.toHaveAttribute('aria-current');
});

test('route-derived navigation remains unchanged without an override', () => {
    render(<AuthenticatedLayout header={<h1>Sport</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(screen.getByRole('link', { name: 'Sports Directory' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Departments' })).not.toHaveAttribute('aria-current');
});

test('shows the Global Admin notification bell and recent activity popover', () => {
    render(<AuthenticatedLayout header={<h1>Dashboard</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(screen.getByRole('button', { name: 'Open notifications' })).toHaveTextContent('3');
    expect(screen.queryByText('Result ready for review')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Open notifications' }));

    expect(screen.getByRole('heading', { name: 'Notifications' })).toBeInTheDocument();
    expect(screen.getByText('Result ready for review')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Mark all read' })).toBeInTheDocument();
});

test('opening an unread notification marks it read before navigating to its action', () => {
    render(<AuthenticatedLayout header={<h1>Dashboard</h1>}><p>Content</p></AuthenticatedLayout>);
    fireEvent.click(screen.getByRole('button', { name: 'Open notifications' }));
    fireEvent.click(screen.getByRole('button', { name: 'Review result' }));

    expect(router.post).toHaveBeenCalledWith('/notifications/n1/read', {}, expect.objectContaining({ preserveScroll: true, onSuccess: expect.any(Function) }));
    const options = router.post.mock.calls.at(-1)[2];
    options.onSuccess();

    expect(router.visit).toHaveBeenCalledWith('/admin/events/1/approvals');
});
