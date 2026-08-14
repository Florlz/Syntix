import React from 'react';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import PublicProgramme from '../../resources/js/Pages/Admin/Events/PublicProgramme';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: { flash: {} } }),
    useForm: (initial = {}) => {
        const [data, setDataState] = React.useState(initial);
        return {
            data,
            errors: {},
            processing: false,
            setData: (key, value) => setDataState((current) => typeof key === 'string' ? { ...current, [key]: value } : key),
            reset: vi.fn(),
            post: vi.fn(),
            patch: vi.fn(),
        };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Components/SportContextNav', () => ({ default: () => <nav aria-label="Sport context">Basketball context</nav> }));

const competitions = [
    { id: '20', name: 'Basketball', divisions: [{ id: '30', name: 'Men' }] },
    { id: '21', name: 'Volleyball', divisions: [{ id: '31', name: 'Women' }] },
];
const matches = [
    { id: 'c1', contest_id: 'c1', competition_id: '20', competition: 'Basketball', division_id: '30', division: 'Men', round: 'Round 1', teams: ['CAS', 'Buhi'], schedule: null },
    { id: 'c2', contest_id: 'c2', competition_id: '20', competition: 'Basketball', division_id: '30', division: 'Men', round: 'Semifinal', teams: ['Buh\u00ED', 'CSPC'], schedule: { id: 's2', venue_id: 'v1', venue: 'Main Gym', title: 'Basketball semifinal', starts_at: '2026-08-20T09:00:00Z', ends_at: null, status: 'scheduled', publication: null, has_unpublished_changes: true } },
    { id: 'c3', contest_id: 'c3', competition_id: '21', competition: 'Volleyball', division_id: '31', division: 'Women', round: 'Round 1', teams: ['CAS', 'CCS'], schedule: null },
];

beforeEach(() => {
    globalThis.route = (name, params) => `/${name}/${Array.isArray(params) ? params.join('/') : params || ''}`;
});

function renderProgramme(overrides = {}) {
    return render(<PublicProgramme event={{ id: '1', name: 'SIKLAB 2026', archived: false }} competitions={competitions} matches={matches} venues={[{ id: 'v1', name: 'Main Gym', location: 'Campus', is_active: true }]} schedule_statuses={[{ value: 'scheduled', label: 'Scheduled' }]} scope={{}} {...overrides} />);
}

test('starts as an event agenda and filters to one sport without mixing matches', async () => {
    const user = userEvent.setup();
    renderProgramme();

    expect(screen.getByRole('heading', { name: 'Match-day schedule' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Volleyball' })).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText('Sport'), '20');

    expect(screen.getAllByRole('button', { name: 'Set time' })).toHaveLength(1);
    expect(screen.getByRole('button', { name: 'Publish Basketball' })).toBeEnabled();
});

test('opens a focused editor for an unscheduled bracket match', async () => {
    const user = userEvent.setup();
    renderProgramme({ matches: matches.slice(0, 2), scope: { competition_id: '20', competition: 'Basketball' } });

    await user.click(screen.getByRole('button', { name: 'Set time' }));

    expect(screen.getByRole('dialog', { name: 'Set match time' })).toBeInTheDocument();
    expect(screen.getByText('This schedules the existing bracket match. It does not create another game.')).toBeInTheDocument();
});

test('loads the selected venue into the venue editor', async () => {
    const user = userEvent.setup();
    renderProgramme();

    await user.click(screen.getByRole('button', { name: 'Manage venues' }));
    await user.click(within(screen.getByRole('dialog', { name: 'Manage venues' })).getByRole('button', { name: 'Edit' }));

    await waitFor(() => expect(screen.getByDisplayValue('Main Gym')).toBeInTheDocument());
    fireEvent.change(screen.getByDisplayValue('Campus'), { target: { value: 'North Campus' } });
    expect(screen.getByDisplayValue('North Campus')).toBeInTheDocument();
});
