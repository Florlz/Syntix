import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Workspace, { workspaceUrl } from '../../resources/js/Pages/Admin/Sports/Workspace';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: vi.fn() },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children, activeSection }) => <div data-testid="authenticated-layout" data-active-section={activeSection || ''}>{children}</div> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));
vi.mock('@/Support/sportArtwork', () => ({ getSportArtwork: () => null }));
vi.mock('../../resources/js/Pages/Admin/Sports/Rosters', () => ({ default: ({ selectedDepartment }) => <section><h2>Roster editor</h2>{selectedDepartment ? <p>Scoped roster</p> : <p>Department chooser</p>}</section> }));

const divisions = [
    { id: '30', name: 'Women', active: true, entry_count: 4, locked_entry_count: 2, unlocked_entry_count: 2, player_count: 12, bracket_state: 'preview', schedule_state: 'published', next_schedule: { title: 'Women semifinal', starts_at: '2026-08-14T10:00:00Z', venue: 'Court 1' } },
    { id: '31', name: 'Men', active: true, entry_count: 5, locked_entry_count: 5, unlocked_entry_count: 0, player_count: 15, bracket_state: 'not_generated', schedule_state: 'not_scheduled', next_schedule: null },
];
const event = { id: '1', name: 'SIKLAB 2026', archived: false };
const sport = { id: '20', name: 'Basketball', active: true, division_count: 2, entry_count: 9, player_count: 27, cover: null };

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.sports.index') return `/admin/events/${params}/sports`;
        if (name === 'admin.sports.tournament') return `/admin/events/${params[0]}/divisions/${params[1]}/tournament`;
        if (name === 'admin.sports.schedules') return `/admin/events/${params.event}/schedules?competition=${params.competition}${params.division ? `&division=${params.division}` : ''}`;
        if (name === 'admin.approvals.index') return `/admin/events/${params.event}/approvals?competition=${params.competition}&division=${params.division}`;
        if (name === 'admin.departments.index') return `/admin/events/${params}/departments`;
        if (name === 'admin.departments.show') return `/admin/events/${params[0]}/departments/${params[1]}/rosters`;
        return `/${name}`;
    };
});

function renderWorkspace(props = {}) {
    return render(<Workspace event={event} sport={sport} divisions={divisions} active_tab="overview" {...props} />);
}

test('does not render redundant workspace tabs and shows every division in all-divisions mode', () => {
    renderWorkspace();

    expect(screen.queryByRole('navigation', { name: /workspace/i })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Women' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Men' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Open Women/ })).toHaveAttribute('href', '/admin/events/1/sports/20?division=30');
    expect(screen.getByRole('link', { name: /Open Men/ })).toHaveAttribute('href', '/admin/events/1/sports/20?division=31');
});

test('selected division offers direct bracket, schedule, results, and department actions', () => {
    renderWorkspace({ selected_division: '30' });

    expect(screen.getByRole('heading', { name: 'Women' })).toBeInTheDocument();
    expect(screen.getByText('Teams', { exact: true }).parentElement).toHaveTextContent('4');
    expect(screen.getByRole('link', { name: /Bracket/ })).toHaveAttribute('href', '/admin/events/1/divisions/30/tournament');
    expect(screen.getByRole('link', { name: /Schedule/ })).toHaveAttribute('href', '/admin/events/1/schedules?competition=20&division=30');
    expect(screen.getByRole('link', { name: /Results/ })).toHaveAttribute('href', '/admin/events/1/approvals?competition=20&division=30');
    expect(screen.getByRole('link', { name: /Teams & rosters/ })).toHaveAttribute('href', '/admin/events/1/departments');
});

test('tab=rosters without a division falls back to the all-divisions hub', () => {
    renderWorkspace({ active_tab: 'rosters' });

    expect(screen.getByRole('heading', { name: 'Choose a division' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Roster editor' })).not.toBeInTheDocument();
});

test('focused roster editor keeps the existing editor and links back to its department', () => {
    renderWorkspace({
        active_tab: 'rosters',
        selected_division: '30',
        selected_department: '10',
        roster_workspace: { departments: [{ id: '10', name: 'College of Arts and Sciences', abbreviation: 'CAS' }] },
    });

    expect(screen.getByRole('heading', { name: 'Roster editor' })).toBeInTheDocument();
    expect(screen.getByText(/College of Arts and Sciences.*Not started/)).toBeInTheDocument();
    expect(screen.getByText('Scoped roster')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Back to College of Arts and Sciences rosters' })).toHaveAttribute('href', '/admin/events/1/departments/10/rosters');
    expect(screen.getByRole('heading', { name: 'Basketball Women roster' })).toBeInTheDocument();
    expect(screen.getByTestId('authenticated-layout')).toHaveAttribute('data-active-section', 'departments');
    expect(screen.queryByRole('link', { name: 'Sports Directory' })).not.toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: /workspace/i })).not.toBeInTheDocument();
});

test('workspaceUrl omits legacy tabs for normal hub links', () => {
    expect(workspaceUrl('1', '20')).toBe('/admin/events/1/sports/20');
    expect(workspaceUrl('1', '20', { division: '30' })).toBe('/admin/events/1/sports/20?division=30');
    expect(workspaceUrl('1', '20', 'rosters', '30', '10')).toBe('/admin/events/1/sports/20?tab=rosters&division=30&department=10');
});
