import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import SportWorkspaceShell from '../../resources/js/Components/Sports/SportWorkspaceShell';

vi.mock('@/Components/PrefetchLink', () => ({
    default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
}));

const event = { id: '1', name: 'SIKLAB 2026' };
const sport = { id: '20', name: 'Basketball', division_count: 2, entry_count: 9, player_count: 27 };
const divisions = [
    { id: '30', name: 'Men', entry_count: 5, player_count: 15, locked_entry_count: 3 },
    { id: '31', name: 'Women', entry_count: 4, player_count: 12, locked_entry_count: 4 },
];

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.index') return `/admin/events/${params}/sports`;
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.sports.tournament') return `/admin/events/${params[0]}/divisions/${params[1]}/tournament`;
        if (name === 'admin.sports.schedules') return `/admin/events/${params.event}/sports-schedules`;
        if (name === 'admin.approvals.index') return `/admin/events/${params.event}/approvals`;
        return `/${name}`;
    };
});

test('keeps the selected division inside one sport workflow shell', () => {
    render(
        <SportWorkspaceShell
            event={event}
            sport={sport}
            division={divisions[0]}
            divisions={divisions}
            activeSection="teams"
        >
            <h2>Teams &amp; Rosters content</h2>
        </SportWorkspaceShell>,
    );

    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.getByText('Men', { selector: '[data-workspace-division]' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Sports Directory' })).toHaveAttribute('href', '/admin/events/1/sports');
    expect(screen.getByRole('link', { name: 'Men' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Women' })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters&division=31');
    expect(screen.getByRole('link', { name: 'All divisions' })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters');

    const workflow = screen.getByRole('navigation', { name: 'Sport workflow' });
    expect(workflow).toHaveTextContent('Overview');
    expect(workflow).toHaveTextContent('Teams & Rosters');
    expect(workflow).toHaveTextContent('Bracket');
    expect(workflow).toHaveTextContent('Schedule');
    expect(workflow).toHaveTextContent('Results');
    expect(screen.getByRole('link', { name: 'Teams & Rosters' })).toHaveAttribute('aria-current', 'page');
});

test('uses a compact all-divisions identity when no division is selected', () => {
    render(
        <SportWorkspaceShell event={event} sport={sport} divisions={divisions} activeSection="overview">
            <h2>Sport overview content</h2>
        </SportWorkspaceShell>,
    );

    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'All divisions' })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Men' })).not.toHaveAttribute('aria-current');
});
