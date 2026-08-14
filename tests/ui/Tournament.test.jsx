import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import { SportRail } from '../../resources/js/Pages/Admin/Sports/Tournament';

vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

const event = { id: '1', name: 'SIKLAB 2026' };
const basketball = { id: '20', name: 'Basketball' };
const sports = [
    {
        id: '20',
        name: 'Basketball',
        divisions: [
            { id: '30', name: 'Men', format: 'single_elimination', disciplines: [] },
            { id: '31', name: 'Women', format: 'single_elimination', disciplines: [{ id: '301', name: '3x3' }] },
        ],
    },
    { id: '21', name: 'Volleyball', divisions: [{ id: '40', name: 'Women', format: 'single_elimination', disciplines: [] }] },
    { id: '22', name: 'Badminton', divisions: [{ id: '50', name: 'Men', format: 'double_elimination', disciplines: [] }] },
];

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.tournament') return `/admin/events/${params[0]}/divisions/${params[1]}/tournament`;
        if (name === 'admin.sports.discipline-tournament') return `/admin/events/${params[0]}/divisions/${params[1]}/disciplines/${params[2]}/tournament`;
        return `/${name}`;
    };
});

test('shows only divisions and disciplines for the current sport', () => {
    render(<SportRail event={event} sport={basketball} sports={sports} division={sports[0].divisions[1]} />);

    expect(screen.getByText('Basketball divisions')).toBeInTheDocument();
    expect(screen.getByText('SIKLAB 2026')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Men/ })).toHaveAttribute('href', '/admin/events/1/divisions/30/tournament');
    expect(screen.getByRole('link', { name: /Women/ })).toHaveAttribute('href', '/admin/events/1/divisions/31/tournament');
    expect(screen.getByRole('link', { name: '3x3' })).toHaveAttribute('href', '/admin/events/1/divisions/31/disciplines/301/tournament');
    expect(screen.queryByText('Volleyball')).not.toBeInTheDocument();
    expect(screen.queryByText('Badminton')).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Volleyball/ })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Badminton/ })).not.toBeInTheDocument();
});
