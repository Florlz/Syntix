import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import DepartmentRosters from '../../resources/js/Pages/Admin/Departments/Show';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { get: routerGet, patch: vi.fn() },
    useForm: (initial) => ({ data: initial, processing: false, errors: {}, setData: vi.fn(), post: vi.fn(), patch: vi.fn() }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));
vi.mock('@/Components/SlideOver', () => ({ default: ({ show, children }) => show ? <div role="dialog">{children}</div> : null }));

const department = {
    id: '10', name: 'College of Arts and Sciences', abbreviation: 'CAS', color: '#0B536D',
    counts: { players: 3, coaches: 1, rosters: 1, unassigned: 1 },
    sports: [{
        id: '20', name: 'Basketball', counts: { players: 3, coaches: 1, rosters: 1 },
        divisions: [
            { id: '30', name: 'Women', rosters: [{ id: '40', state: 'active', counts: { players: 3, coaches: 1 } }] },
            { id: '31', name: 'Men', rosters: [{ id: null, state: 'not_started', counts: { players: 0, coaches: 0 } }] },
        ],
    }],
};

beforeEach(() => {
    routerGet.mockReset();
    globalThis.route = (name, params) => {
        if (name === 'admin.departments.index') return `/admin/events/${params}/departments`;
        if (name === 'admin.departments.show') return `/admin/events/${params[0]}/departments/${params[1]}/rosters`;
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.registrations.directory-preview') return `/admin/events/${params}/registrations/directory/preview`;
        return `/${name}`;
    };
});

test('groups a department roster desk by sport and division', () => {
    render(<DepartmentRosters event={{ id: '1', name: 'SIKLAB 2026', archived: false }} department={department} departments={[department]} competitions={[]} />);

    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Women' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Men' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /Manage roster/ })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters&division=30&department=10');
    expect(screen.getByRole('link', { name: /Start roster/ })).toHaveAttribute('href', '/admin/events/1/sports/20?tab=rosters&division=31&department=10');
});

test('loads people only when a roster preview is opened', async () => {
    const user = userEvent.setup();
    global.fetch = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ people: [{ id: '50', display_name: 'Athlete One', student_number: 'CAS-001' }], total: 1, limit: 25, has_more: false }) });
    render(<DepartmentRosters event={{ id: '1', name: 'SIKLAB 2026', archived: false }} department={department} departments={[department]} competitions={[]} />);

    expect(screen.queryByText('Athlete One')).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'View players for Basketball Women' }));
    await waitFor(() => expect(screen.getByText('Athlete One')).toBeInTheDocument());
    expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('entry=40'), expect.objectContaining({ credentials: 'same-origin' }));
});

test('switching to coaches keeps the same department URL', async () => {
    const user = userEvent.setup();
    render(<DepartmentRosters event={{ id: '1', name: 'SIKLAB 2026', archived: false }} department={department} departments={[department]} competitions={[]} />);

    await user.click(screen.getByRole('button', { name: 'Coaches & support' }));
    expect(routerGet).toHaveBeenCalledWith('/admin/events/1/departments/10/rosters', { view: 'coaches' }, expect.objectContaining({ replace: true }));
});

test('filters a large department desk to sports that already have players', async () => {
    const user = userEvent.setup();
    const fullDepartment = {
        ...department,
        sports: [
            ...department.sports,
            {
                id: '21', name: 'Chess', counts: { players: 0, coaches: 0, rosters: 0 },
                divisions: [{ id: '32', name: 'Open', rosters: [{ id: null, state: 'not_started', counts: { players: 0, coaches: 0 } }] }],
            },
        ],
    };
    render(<DepartmentRosters event={{ id: '1', name: 'SIKLAB 2026', archived: false }} department={fullDepartment} departments={[fullDepartment]} competitions={[]} />);

    await user.selectOptions(screen.getByRole('combobox', { name: 'Show' }), 'with_people');
    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Chess' })).not.toBeInTheDocument();
});
