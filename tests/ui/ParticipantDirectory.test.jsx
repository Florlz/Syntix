import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import ParticipantDirectory from '../../resources/js/Pages/Admin/Registrations/ParticipantDirectory';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    router: { get: routerGet, patch: vi.fn() },
    useForm: (initial) => ({
        data: initial,
        processing: false,
        errors: {},
        setData: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
    }),
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({
    default: ({ children }) => <div>{children}</div>,
}));

vi.mock('@/Components/SlideOver', () => ({
    default: ({ show, children }) => show ? <div role="dialog">{children}</div> : null,
}));

const summary = {
    totals: { players: 3, unassigned: 1, rosters: 1, coaches: 1 },
    departments: [
        {
            id: '10', name: 'College of Arts and Sciences', abbreviation: 'CAS', color: '#0B536D',
            counts: { players: 3, unassigned: 1, rosters: 1, coaches: 1 },
            sports: [{
                id: '20', name: 'Basketball', counts: { players: 3, rosters: 1, coaches: 1 },
                divisions: [{
                    id: '30', name: 'Women', counts: { players: 3, rosters: 1, coaches: 1 },
                    rosters: [{ id: '40', name: 'CAS Basketball Women', code: 'CAS-BBW', state: 'active', counts: { players: 3, coaches: 1 } }],
                }],
            }],
        },
        {
            id: '11', name: 'College of Computer Studies', abbreviation: 'CCS', color: '#E4B84A',
            counts: { players: 0, unassigned: 0, rosters: 0, coaches: 0 }, sports: [],
        },
    ],
};

const props = {
    event: { id: '1', name: 'SIKLAB 2026', archived: false },
    departments: [{ id: '10', name: 'College of Arts and Sciences' }, { id: '11', name: 'College of Computer Studies' }],
    competitions: [{ id: '20', name: 'Basketball', programme_family: 'team', divisions: [{ id: '30', name: 'Women' }] }],
    directory_summary: summary,
    selection: { department: '10', sport: '20', division: '30', entry: '' },
    initialView: 'players',
    filters: { q: '', directory_status: 'all', directory_roster: '' },
};

beforeEach(() => {
    routerGet.mockReset();
    globalThis.route = (name, params) => name === 'admin.registrations.directory-preview'
        ? `/admin/events/${Array.isArray(params) ? params[0] : params}/registrations/directory/preview`
        : name === 'admin.sports.show'
            ? `/admin/events/${Array.isArray(params) ? params[0] : params}/sports/${Array.isArray(params) ? params[1] : ''}`
            : `/admin/events/${Array.isArray(params) ? params[0] : params}/registrations`;
});

test('shows the department explorer hierarchy instead of a flat person list', () => {
    render(<ParticipantDirectory {...props} />);

    expect(screen.getAllByText('College of Arts and Sciences')).toHaveLength(2);
    expect(screen.getByRole('tab', { name: /Basketball/ })).toBeInTheDocument();
    expect(screen.getByRole('tab', { name: 'Women' })).toBeInTheDocument();
    expect(screen.getByText('CAS Basketball Women')).toBeInTheDocument();
    expect(screen.queryByText('Segmentation')).not.toBeInTheDocument();
});

test('selecting a department updates the explorer URL state', async () => {
    const user = userEvent.setup();
    render(<ParticipantDirectory {...props} />);

    await user.click(screen.getByRole('button', { name: /College of Computer Studies/ }));

    expect(routerGet).toHaveBeenCalledWith('/admin/events/1/registrations', expect.objectContaining({ directory_department: '11' }), expect.objectContaining({ replace: true }));
});

test('loads a capped roster preview only when requested', async () => {
    const user = userEvent.setup();
    global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({
            roster: { id: '40', name: 'CAS Basketball Women', state: 'active', counts: { players: 1, coaches: 0 } },
            people: [{ id: '50', display_name: 'Showcase Athlete 1', student_number: 'CAS-001', is_active: true }],
            total: 1,
            limit: 25,
            has_more: false,
        }),
    });

    render(<ParticipantDirectory {...props} />);
    expect(screen.queryByText('Showcase Athlete 1')).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Preview players' }));

    await waitFor(() => expect(screen.getByText('Showcase Athlete 1')).toBeInTheDocument());
    expect(global.fetch).toHaveBeenCalledWith(expect.stringContaining('entry=40'), expect.objectContaining({ credentials: 'same-origin' }));
});
