import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import DepartmentDirectory from '../../resources/js/Pages/Admin/Departments/Index';

vi.mock('@inertiajs/react', () => ({ Head: () => null }));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

const summary = {
    totals: { players: 15, coaches: 4, rosters: 3, unassigned: 2 },
    departments: [
        { id: '10', name: 'College of Arts and Sciences', abbreviation: 'CAS', color: '#0B536D', counts: { players: 10, coaches: 3, rosters: 2, unassigned: 2 }, sports: [{ id: '20', name: 'Basketball', divisions: [{ id: '30' }, { id: '31' }] }, { id: '21', name: 'Chess', divisions: [{ id: '32' }] }] },
        { id: '11', name: 'College of Computer Studies', abbreviation: 'CCS', color: '#D5A21F', counts: { players: 5, coaches: 1, rosters: 1, unassigned: 0 }, sports: [{ id: '20', name: 'Basketball', divisions: [{ id: '30' }] }] },
    ],
};

beforeEach(() => {
    globalThis.route = (name, params) => name === 'admin.departments.show'
        ? `/admin/events/${params[0]}/departments/${params[1]}/rosters`
        : `/admin/events/${params}/departments`;
});

test('shows department cards with roster capacity and dedicated links', () => {
    render(<DepartmentDirectory event={{ id: '1', name: 'SIKLAB 2026' }} directory_summary={summary} />);

    expect(screen.getByRole('heading', { name: 'College of Arts and Sciences' })).toBeInTheDocument();
    expect(screen.getByText('2/3')).toBeInTheDocument();
    expect(screen.getByText('2 players not yet rostered')).toBeInTheDocument();
    expect(screen.getAllByRole('link', { name: /Manage rosters/ })[0]).toHaveAttribute('href', '/admin/events/1/departments/10/rosters');
});

test('filters the department card grid without changing pages', async () => {
    const user = userEvent.setup();
    render(<DepartmentDirectory event={{ id: '1', name: 'SIKLAB 2026' }} directory_summary={summary} />);

    await user.type(screen.getByRole('searchbox', { name: 'Search departments' }), 'computer');
    expect(screen.queryByRole('heading', { name: 'College of Arts and Sciences' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'College of Computer Studies' })).toBeInTheDocument();
});
