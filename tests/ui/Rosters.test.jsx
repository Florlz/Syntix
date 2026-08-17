import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import Rosters from '../../resources/js/Pages/Admin/Sports/Rosters';

const { routerPost, formPutCalls } = vi.hoisted(() => ({ routerPost: vi.fn(), formPutCalls: [] }));

vi.mock('@inertiajs/react', () => ({
    router: { post: routerPost },
    useForm: (initial) => {
        const form = {
            data: initial,
            transformedData: null,
            processing: false,
            errors: {},
            setData: vi.fn(),
            clearErrors: vi.fn(),
            transform: vi.fn((callback) => {
                form.transformedData = callback(form.data);
                return form;
            }),
            patch: vi.fn(),
            post: vi.fn(),
            put: vi.fn((url, options) => formPutCalls.push({ url, options, data: form.transformedData ?? form.data })),
        };
        return form;
    },
}));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, preserveScroll: _preserveScroll, ...props }) => <a href={href} {...props}>{children}</a> }));
vi.mock('@/Components/SlideOver', () => ({ default: ({ show, children, title }) => show ? <div role="dialog" aria-label={title}>{children}</div> : null }));
vi.mock('../../resources/js/Pages/Admin/Sports/RosterAddPlayers', () => ({ default: () => <p>Add players form</p> }));

const event = { id: '1', archived: false };
const sport = { id: '9', name: 'Arnis' };
const division = { id: '168', name: 'Men' };
const departments = [
    { id: '1', name: 'CSPC Buhi Campus', abbreviation: 'Buhi', color: 'Red', state: 'not_started', summary: 'Roster not started' },
    { id: '2', name: 'College of Arts and Sciences', abbreviation: 'CAS', color: 'Yellow', state: 'review', summary: '0 of 8 players' },
];
const options = { roster_roles: [] };

const entry = {
    id: '40', name: 'Buhi Arnis Men', status: 'active', approval_revision: 0,
    limits: { maximum: 8 },
    capabilities: { can_add_players: true, can_lock: true, can_reopen: false, published: false },
};

function selected(overrides = {}) {
    return {
        department_id: '1', entry, participants: [], coaches: [], counts: { active_players: 0 },
        readiness: { ready: false, blockers: ['Add at least 1 active athlete before approval.'], notices: ['Student coach is optional.'] },
        ...overrides,
    };
}

function renderScoped(selectedRoster, props = {}) {
    return render(<Rosters
        event={{ ...event, archived: props.archived ?? false }}
        sport={sport}
        division={division}
        selectedDepartment="1"
        workspace={{ departments, selected: selectedRoster }}
        options={options}
        archived={props.archived ?? false}
        departmentName="CSPC Buhi Campus"
    />);
}

beforeEach(() => {
    routerPost.mockReset();
    formPutCalls.length = 0;
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.department-rosters.store') return `/admin/events/${params[0]}/divisions/${params[1]}/departments/${params[2]}/roster`;
        if (name === 'admin.departments.show') return `/admin/events/${params[0]}/departments/${params[1]}/rosters`;
        if (name === 'admin.entries.status') return `/admin/events/${params[0]}/entries/${params[1]}/status`;
        if (name === 'admin.roster-coach-support.store') return `/admin/events/${params[0]}/divisions/${params[1]}/departments/${params[2]}/coach-support`;
        return `/${name}`;
    };
});

test('no-entry scoped state offers one focused start action and no management sections', async () => {
    const user = userEvent.setup();
    renderScoped(selected({ entry: null, readiness: { ready: false, blockers: [], notices: [] } }));

    expect(screen.getByRole('heading', { name: 'Start the Arnis Men roster' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Start team sheet' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Players' })).not.toBeInTheDocument();
    expect(screen.queryByText('Before approval')).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Start team sheet' }));
    expect(routerPost).toHaveBeenCalledWith('/admin/events/1/divisions/168/departments/1/roster', {}, { preserveScroll: true });
});

test('empty draft puts the first-player action inside the Players surface and shows each blocker once', () => {
    renderScoped(selected());

    expect(screen.getByRole('heading', { name: 'Players' })).toBeInTheDocument();
    expect(screen.getByText('0 of 8')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add first player' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Add player' })).not.toBeInTheDocument();
    expect(screen.getAllByText('Add at least 1 active athlete before approval.')).toHaveLength(1);
    expect(screen.queryByRole('button', { name: 'Approve team sheet' })).not.toBeInTheDocument();
    expect(screen.queryByText('Student coach is optional.')).not.toBeInTheDocument();
});

test('populated draft moves the add action into the Players header', () => {
    const participant = { id: '50', display_name: 'Athlete One', membership: { role: 'student_athlete', is_active: true } };
    renderScoped(selected({ participants: [participant], counts: { active_players: 1 } }));

    expect(screen.getByText('Athlete One')).toBeInTheDocument();
    expect(screen.getByText('1 of 8')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add player' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Add first player' })).not.toBeInTheDocument();
});

test('ready roster enables review and moves non-blocking notices into the dialog', async () => {
    const user = userEvent.setup();
    renderScoped(selected({ readiness: { ready: true, blockers: [], notices: ['Preview draw will need regeneration.'] } }));

    expect(screen.getByRole('heading', { name: 'Team sheet is ready for approval' })).toBeInTheDocument();
    const review = screen.getByRole('button', { name: 'Approve team sheet' });
    expect(review).toBeEnabled();
    expect(screen.queryByText('Preview draw will need regeneration.')).not.toBeInTheDocument();
    await user.click(review);
    expect(screen.getByRole('dialog', { name: 'Approve team sheet' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Good to know' })).toBeInTheDocument();
    expect(screen.getByText('Preview draw will need regeneration.')).toBeInTheDocument();
});

test('locked roster hides add and review actions and exposes reopen only when permitted', () => {
    renderScoped(selected({ entry: { ...entry, status: 'locked', approval_revision: 2, capabilities: { ...entry.capabilities, can_add_players: false, can_lock: false, can_reopen: true } }, readiness: { ready: true, blockers: [], notices: [] } }));

    expect(screen.queryByRole('button', { name: 'Add player' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Add first player' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Approve team sheet' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Edit approved team sheet' })).toBeInTheDocument();
});

test('locked player remains available for status review and participation exceptions', async () => {
    const user = userEvent.setup();
    const participant = {
        id: '50',
        display_name: 'Locked Athlete',
        membership: { role: 'student_athlete', is_active: true, notes: 'Approved player' },
        capabilities: {
            can_manage: true,
            can_edit_profile: true,
            can_edit_membership: false,
            can_restore_membership: false,
            can_record_exception: true,
        },
    };
    renderScoped(selected({
        entry: {
            ...entry,
            status: 'locked',
            approval_revision: 2,
            capabilities: { ...entry.capabilities, can_add_players: false, can_lock: false, can_reopen: true },
        },
        participants: [participant],
        counts: { active_players: 1 },
        readiness: { ready: true, blockers: [], notices: [] },
    }));

    const viewStatus = screen.getByRole('button', { name: 'View status' });
    await user.click(viewStatus);

    expect(screen.getByRole('dialog', { name: 'Manage Locked Athlete' })).toBeInTheDocument();
    expect(screen.getByLabelText('Display name')).toBeEnabled();
    expect(screen.getByLabelText('Role')).toBeDisabled();
    expect(screen.getByLabelText('Notes')).toBeDisabled();
    expect(screen.getByLabelText('Active and cleared to compete')).toBeDisabled();
    expect(screen.getByText('Participation exception')).toBeInTheDocument();
    expect(screen.getByLabelText('Exception type')).toBeInTheDocument();
    expect(screen.getByLabelText('Required reason')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Record exception' })).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Save changes' }));
    expect(formPutCalls.at(-1).data).toEqual({
        profile: {
            display_name: 'Locked Athlete',
            given_name: '',
            family_name: '',
            student_number: '',
            email: '',
            phone: '',
            private_notes: '',
        },
    });
});

test('archived and published rosters remain read-only', () => {
    const archivedView = renderScoped(selected({ readiness: { ready: true, blockers: [], notices: [] } }), { archived: true });
    expect(screen.queryByRole('button', { name: 'Add player' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Add first player' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add coach or staff' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Approve team sheet' })).not.toBeInTheDocument();
    expect(screen.getByText('Archived events are read-only.')).toBeInTheDocument();
    archivedView.unmount();

    renderScoped(selected({ entry: { ...entry, capabilities: { ...entry.capabilities, can_add_players: false, can_lock: false, published: true } }, readiness: { ready: true, blockers: [], notices: [] } }));
    expect(screen.queryByRole('button', { name: 'Add player' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Add first player' })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Add coach or staff' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Approve team sheet' })).not.toBeInTheDocument();
    expect(screen.getByText(/approved team sheet can no longer be edited/)).toBeInTheDocument();
});

test('team staff and history stay in quiet disclosures with a department-scoped add flow', async () => {
    const user = userEvent.setup();
    const coach = { id: '60', display_name: 'Coach One', title: 'Student coach', coach_type: 'student_coach', scope_type: 'division', scope_key: '168' };
    const inactive = { id: '51', display_name: 'Former Athlete', membership: { role: 'reserve', is_active: false } };
    renderScoped(selected({ coaches: [coach], participants: [inactive] }));

    expect(screen.getByText('Team staff (optional) - 1')).toBeInTheDocument();
    expect(screen.getByText('Roster history - 1 inactive')).toBeInTheDocument();
    await user.click(screen.getByText('Team staff (optional) - 1'));
    expect(screen.getByText('Coach One')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Manage all team staff' })).toHaveAttribute('href', '/admin/events/1/departments/1/rosters?view=coaches');
    await user.click(screen.getByRole('button', { name: 'Add coach or staff' }));
    expect(screen.getByRole('dialog', { name: 'Add coach or staff' })).toBeInTheDocument();
    expect(screen.getByText(/CSPC Buhi Campus.*Arnis Men/)).toBeInTheDocument();
    expect(screen.getByText('This coverage is fixed to the current roster.')).toBeInTheDocument();
    await user.click(screen.getByText('Roster history - 1 inactive'));
    expect(screen.getByText('Former Athlete')).toBeInTheDocument();
});

test('legacy unscoped mode keeps the department chooser', () => {
    render(<Rosters event={event} sport={sport} division={division} selectedDepartment={null} workspace={{ departments, selected: null }} options={options} archived={false} />);

    expect(screen.getByRole('heading', { name: 'Men' })).toBeInTheDocument();
    expect(screen.getByLabelText('Search teams')).toBeInTheDocument();
    expect(screen.getByLabelText('Team status')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /CAS College of Arts and Sciences/ })).toBeInTheDocument();
    expect(screen.getByText('Manage the teams competing in Arnis Men.')).toBeInTheDocument();
});

test('department rows keep their own visual color coding', () => {
    render(<Rosters event={event} sport={sport} division={division} selectedDepartment={null} workspace={{ departments, selected: null }} options={options} archived={false} />);

    expect(screen.getByRole('link', { name: /CSPC Buhi Campus/ })).toHaveStyle({ '--department-accent': '#C9362B' });
    expect(screen.getByRole('link', { name: /College of Arts and Sciences/ })).toHaveStyle({ '--department-accent': '#BD8B00' });
});
