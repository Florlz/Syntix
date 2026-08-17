import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Tournament from '../../resources/js/Pages/Admin/Sports/Tournament';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { errors: {}, flash: {} } }),
    useForm: (initial = {}) => {
        const form = {
            data: initial,
            processing: false,
            transform: vi.fn(() => form),
            post: vi.fn(),
            patch: vi.fn(),
        };
        return form;
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

const event = { id: '1', name: 'SIKLAB 2026', archived: false };
const division = {
    id: '31',
    name: 'Women',
    format: 'single_elimination',
    participant_mode: 'team',
    entry_count: 4,
    locked_entry_count: 1,
    player_count: 12,
};
const sport = { id: '20', name: 'Basketball', active: true, entry_count: 4, player_count: 12 };

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.index') return `/admin/events/${params}/sports`;
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.sports.tournament') return `/admin/events/${params[0]}/divisions/${params[1]}/tournament`;
        if (name === 'admin.approvals.index') return `/admin/events/${params[0]}/approvals`;
        if (name === 'admin.draws.random') return `/admin/divisions/${params}/draw`;
        return `/${name}`;
    };
});

test('keeps the bracket inside the shared sport workspace and points blockers to rosters', () => {
    render(<Tournament
        event={event}
        sport={sport}
        sports={[{ ...sport, divisions: [division, { id: '32', name: 'Men' }] }, { id: '21', name: 'Volleyball', divisions: [] }]}
        division={division}
        proposal={{ supported_bracket: true }}
        entries={[]}
        blockers={['No Entries have been registered for this division.']}
        can_generate={false}
        can_redraw={false}
        can_publish={false}
        is_archived={false}
    />);

    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.getByText('Women', { selector: '[data-workspace-division]' })).toBeInTheDocument();
    expect(screen.getByRole('navigation', { name: 'Sport workflow' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Bracket' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Manage rosters' })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters&division=31');
    expect(screen.getByText(/No teams have been registered for this division/)).toBeInTheDocument();
    expect(screen.queryByText('Volleyball')).not.toBeInTheDocument();
});

test('shows an uncontested division as a successful terminal state without draw controls', () => {
    render(<Tournament
        event={event}
        sport={sport}
        sports={[{ ...sport, divisions: [division] }]}
        division={{ ...division, entry_count: 1, locked_entry_count: 1 }}
        proposal={{ supported_bracket: true }}
        entries={[{
            id: '41',
            name: 'CAS Women',
            status: 'locked',
            delegation: { name: 'College of Arts and Sciences', abbreviation: 'CAS' },
            participants: [{ id: '1', name: 'Athlete One', active: true }],
        }]}
        tournament={{ state: 'uncontested', eligible_entry_count: 1 }}
        bracket={null}
        blockers={[]}
        can_generate={false}
        can_redraw={false}
        can_publish={false}
        is_archived={false}
    />);

    expect(screen.getByText('UNCONTESTED')).toBeInTheDocument();
    expect(screen.getByText('Uncontested')).toBeInTheDocument();
    expect(screen.getByText('College of Arts and Sciences')).toBeInTheDocument();
    expect(screen.getByText(/advances without a bracket/i)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Open Results/ })).toHaveAttribute('href', '/admin/events/1/approvals?competition=20&division=31');
    expect(screen.queryByRole('button', { name: /Generate bracket/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Redraw/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Publish bracket/ })).not.toBeInTheDocument();
});

test('explains configuration blockers without sending admins to roster management', () => {
    render(<Tournament
        event={event}
        sport={sport}
        sports={[{ ...sport, divisions: [division] }]}
        division={division}
        proposal={{ supported_bracket: true }}
        entries={[]}
        blockers={['No rule version is configured.']}
        can_generate={false}
        can_redraw={false}
        can_publish={false}
        is_archived={false}
    />);

    expect(screen.getByText(/Competition format and roster rules still need to be configured/)).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Manage rosters' })).not.toBeInTheDocument();
});

test('archived and roster blockers do not expose a misleading roster action', () => {
    render(<Tournament
        event={{ ...event, archived: true }}
        sport={sport}
        sports={[{ ...sport, divisions: [division] }]}
        division={division}
        proposal={{ supported_bracket: true }}
        entries={[]}
        blockers={[
            'This event is archived and its tournament topology is read-only.',
            'No Entries have been registered for this Division.',
        ]}
        can_generate={false}
        can_redraw={false}
        can_publish={false}
        is_archived
    />);

    expect(screen.getByText(/event is archived and the bracket is read-only/i)).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Manage rosters' })).not.toBeInTheDocument();
});

test('empty discipline scope does not render a jump link without an assignment target', () => {
    render(<Tournament
        event={event}
        sport={sport}
        sports={[{ ...sport, divisions: [division] }]}
        division={division}
        discipline={{ id: '81', name: 'Flyweight', family: 'combat' }}
        proposal={{ supported_bracket: true }}
        entries={[]}
        blockers={['No department Entries have been assigned to this discipline.']}
        can_generate={false}
        can_redraw={false}
        can_publish={false}
        is_archived={false}
    />);

    expect(screen.queryByRole('link', { name: 'Review assignments' })).not.toBeInTheDocument();
    expect(document.querySelector('#discipline-entries')).not.toBeInTheDocument();
});
