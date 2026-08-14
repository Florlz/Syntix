import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Dashboard from '../../resources/js/Pages/Dashboard';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: vi.fn() },
    usePage: () => ({ props: { flash: {} } }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children, header }) => <div>{header}{children}</div> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

const event = { id: '1', name: 'SIKLAB 2026', state: 'preparation' };
const summary = {
    competitions: 3,
    divisions: 12,
    blocked_divisions: 2,
    participants: 15,
    event_staff: 4,
    unassigned_staff: 1,
    pending_results: 2,
    pending_placements: 1,
    schedules: 5,
    published_schedules: 3,
};

beforeEach(() => {
    globalThis.route = (name, params) => ({
        dashboard: '/dashboard',
        'admin.sports.index': `/admin/events/${params}/sports`,
        'admin.departments.index': `/admin/events/${params}/departments`,
        'admin.sports.schedules': `/admin/events/${params}/sports-schedules`,
        'admin.staff.index': `/admin/events/${params}/staff`,
        'admin.approvals.index': `/admin/events/${params}/approvals`,
        'admin.events.create': '/admin/events/create',
    }[name]);
});

function renderDashboard(overrides = {}) {
    return render(<Dashboard
        events={[event]}
        event={event}
        summary={summary}
        teams={[{ id: 'a' }, { id: 'b' }, { id: 'c' }]}
        capabilities={{ global_admin: true }}
        {...overrides}
    />);
}

test('presents a student-friendly event home with a useful summary', () => {
    renderDashboard();

    expect(screen.getByRole('heading', { name: 'Event home' })).toBeInTheDocument();
    expect(screen.getAllByText('Event home')).toHaveLength(1);
    expect(screen.getByText('SIKLAB 2026 has 3 sports and 12 activities, 3 departments, and 15 registered players.')).toBeInTheDocument();
    expect(screen.getByText('2 activities still need setup.')).toBeInTheDocument();
    expect(screen.getByText('1 event staff still needs a task.')).toBeInTheDocument();
    expect(screen.getByText('3 results are ready for review.')).toBeInTheDocument();
    expect(screen.queryByText(/eligibility/i)).not.toBeInTheDocument();
});

test('shows exactly five task destinations with direct actions', () => {
    renderDashboard();

    expect(screen.getByRole('link', { name: /Open activities/ })).toHaveAttribute('href', '/admin/events/1/sports');
    expect(screen.getByRole('link', { name: /Manage teams/ })).toHaveAttribute('href', '/admin/events/1/departments');
    expect(screen.getByRole('link', { name: /Open schedule/ })).toHaveAttribute('href', '/admin/events/1/sports-schedules');
    expect(screen.getByRole('link', { name: /Manage event staff/ })).toHaveAttribute('href', '/admin/events/1/staff');
    expect(screen.getByRole('link', { name: /Review results/ })).toHaveAttribute('href', '/admin/events/1/approvals');
    expect(screen.getByRole('heading', { name: 'Sports & Activities' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Departments & Teams' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Schedule' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Event Staff' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Results' })).toBeInTheDocument();
});

test('confirms the workspace is up to date when no attention is needed', () => {
    renderDashboard({
        summary: {
            competitions: 1,
            divisions: 2,
            participants: 10,
            event_staff: 2,
            unassigned_staff: 0,
            pending_results: 0,
            pending_placements: 0,
            schedules: 1,
            published_schedules: 1,
        },
    });

    expect(screen.getByText('Your event workspace is up to date.')).toBeInTheDocument();
});
