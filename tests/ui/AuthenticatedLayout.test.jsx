import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import AuthenticatedLayout from '../../resources/js/Layouts/AuthenticatedLayout';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { global_admin: true, active_event: { id: '1', name: 'SIKLAB 2026' }, user: { name: 'Admin', email: 'admin@syntix.test' } }, nav_badges: {} } }),
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, method: _method, as: _as, ...props }) => <a href={href} {...props}>{children}</a> }));

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (!name) return { current: (pattern) => pattern === 'admin.sports.*' };
        if (name === 'dashboard') return '/dashboard';
        if (name === 'admin.sports.index') return `/admin/events/${params}/sports`;
        if (name === 'admin.departments.index') return `/admin/events/${params}/departments`;
        if (name === 'admin.staff.index') return `/admin/events/${params}/staff`;
        if (name === 'admin.approvals.index') return `/admin/events/${params}/approvals`;
        return `/${name}`;
    };
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
