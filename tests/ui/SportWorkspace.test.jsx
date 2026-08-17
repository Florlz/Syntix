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
        if (name === 'admin.sports.schedules') return `/admin/events/${params[0]}/schedules`;
        if (name === 'admin.approvals.index') return `/admin/events/${params[0]}/approvals`;
        if (name === 'admin.departments.index') return `/admin/events/${params}/departments`;
        if (name === 'admin.departments.show') return `/admin/events/${params[0]}/departments/${params[1]}/rosters`;
        return `/${name}`;
    };
});

function renderWorkspace(props = {}) {
    return render(<Workspace event={event} sport={sport} divisions={divisions} active_tab="overview" {...props} />);
}

test('shows the shared workflow and every division in all-divisions mode', () => {
    renderWorkspace();

    expect(screen.getByRole('navigation', { name: 'Sport workflow' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Women' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Men' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Women 4 teams/ })).toHaveAttribute('href', '/admin/events/1/sports/20?division=30');
    expect(screen.getByRole('link', { name: /Men 5 teams/ })).toHaveAttribute('href', '/admin/events/1/sports/20?division=31');
});

test('selected division offers direct workflow links and division-scoped roster management', () => {
    renderWorkspace({ selected_division: '30' });

    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Women' })).toBeInTheDocument();
    expect(screen.getByText('Teams', { exact: true }).parentElement).toHaveTextContent('4');
    expect(screen.getByRole('link', { name: /Bracket/ })).toHaveAttribute('href', '/admin/events/1/divisions/30/tournament');
    expect(screen.getByRole('link', { name: /Schedule/ })).toHaveAttribute('href', '/admin/events/1/schedules?competition=20&division=30');
    expect(screen.getByRole('link', { name: /Results/ })).toHaveAttribute('href', '/admin/events/1/approvals?competition=20&division=30');
    expect(screen.getByRole('link', { name: 'Teams & Rosters' })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters&division=30');
});

test('tab=rosters without a division falls back to the all-divisions hub', () => {
    renderWorkspace({ active_tab: 'rosters' });

    expect(screen.getByRole('heading', { name: 'Choose a division' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Roster editor' })).not.toBeInTheDocument();
});

test('focused roster editor keeps the existing editor and links back to Teams & Rosters', () => {
    renderWorkspace({
        active_tab: 'rosters',
        selected_division: '30',
        selected_department: '10',
        roster_workspace: { departments: [{ id: '10', name: 'College of Arts and Sciences', abbreviation: 'CAS' }] },
    });

    expect(screen.getByRole('heading', { name: 'Roster editor' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'College of Arts and Sciences' })).toBeInTheDocument();
    expect(screen.getByText('Team roster · Not started')).toBeInTheDocument();
    expect(screen.getByText('Scoped roster')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Back to Teams & Rosters' })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters&division=30');
    expect(screen.getByRole('link', { name: 'Sports Directory' })).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Sport workflow' })).toBeInTheDocument();
});

test('pending results need review and remain the next incomplete workflow step', () => {
    const pendingDivision = {
        ...divisions[0],
        locked_entry_count: 4,
        bracket_state: 'published',
        schedule_state: 'published',
        results_state: 'pending_review',
    };

    renderWorkspace({ divisions: [pendingDivision], selected_division: pendingDivision.id });

    expect(screen.getByText('Needs review')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Review submitted results/ })).toHaveAttribute(
        'href',
        '/admin/events/1/approvals?competition=20&division=30',
    );
    expect(screen.queryByRole('link', { name: /Division is ready/ })).not.toBeInTheDocument();
});

test('a draft schedule remains incomplete and sends the next step to publication', () => {
    const draftScheduleDivision = {
        ...divisions[0],
        entry_count: 7,
        participating_entry_count: 6,
        locked_entry_count: 6,
        unlocked_entry_count: 0,
        bracket_state: 'published',
        schedule_state: 'draft',
        results_state: 'not_started',
    };

    renderWorkspace({ divisions: [draftScheduleDivision], selected_division: draftScheduleDivision.id });

    expect(screen.getByText('6 of 6 team sheets ready')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Publish the schedule' })).toHaveAttribute(
        'href',
        '/admin/events/1/schedules?competition=20&division=30',
    );
});

test('workspaceUrl omits legacy tabs for normal hub links', () => {
    expect(workspaceUrl('1', '20')).toBe('/admin/events/1/sports/20');
    expect(workspaceUrl('1', '20', { division: '30' })).toBe('/admin/events/1/sports/20?division=30');
    expect(workspaceUrl('1', '20', 'rosters', '30', '10')).toBe('/admin/events/1/sports/20?tab=rosters&division=30&department=10');
});
