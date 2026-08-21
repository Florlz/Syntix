import React from 'react';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { vi } from 'vitest';
import AuthenticatedLayout from '../../resources/js/Layouts/AuthenticatedLayout';
import AdminStaff from '../../resources/js/Pages/Admin/Staff/Index';
import JudgeIndex from '../../resources/js/Pages/Judge/Index';
import TabulatorIndex from '../../resources/js/Pages/Tabulator/Index';

const usePage = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    useForm: (initial = {}) => ({ data: { role: 'judge', scope_type: 'contest', target_id: '', judge_ids: [], ...initial }, setData: vi.fn(), post: vi.fn(), patch: vi.fn(), transform: vi.fn(function () { return this; }), processing: false, reset: vi.fn() }),
    usePage,
    router: { visit: vi.fn(), post: vi.fn() },
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));
vi.mock('qrcode', () => ({ default: { toDataURL: vi.fn() } }));

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (!name) return { current: () => false };
        if (name === 'judge.index') return '/judge';
        if (name === 'tabulator.index') return '/tabulator';
        if (name === 'admin.staff.index') {
            const query = new URLSearchParams();
            if (params?.section) query.set('section', params.section);
            if (params?.focus) query.set('focus', params.focus);
            return `/admin/events/${params?.event ?? params}/staff${query.size ? `?${query}` : ''}`;
        }
        if (name === 'admin.accounts.create') return `/admin/events/${params}/accounts/create`;
        return `/${name}`;
    };
    usePage.mockReturnValue({ props: { auth: { global_admin: false, active_event: { roles: ['judge'] }, user: { name: 'Judge', email: 'judge@example.com' } }, flash: {}, errors: {} } });
});

test('role-aware sidebar exposes only the current worker role destinations', () => {
    render(<AuthenticatedLayout header={<h1>Overview</h1>}><p>Content</p></AuthenticatedLayout>);

    expect(screen.queryByRole('link', { name: 'Overview' })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'My Judging' })).toHaveAttribute('href', '/judge');
    expect(screen.queryByRole('link', { name: 'My Tabulation' })).not.toBeInTheDocument();
});

test('admin staff workspace uses normal URL sections and names the readiness blocker', () => {
    render(<AdminStaff
        event={{ id: '1', name: 'SIKLAB 2026', archived: false }}
        section="readiness"
        staff={[]}
        targets={{ competition_division: [], contest: [] }}
        readiness={[{
            name: 'Essay Writing',
            competition: 'Essay Writing',
            state: 'blocked',
            source: { reliability: 'conflict', blocker: 'Criteria total 95 while the source prints 100.', pages: [20] },
            next_blocker: 'Criteria total 95 while the source prints 100.',
            counts: { entries: 7, judges: 0, tabulators: 0 },
            schedule: { starts_at: '2026-08-18T09:00:00Z', ends_at: null, title: 'Morning judging call', venue: { id: '3', name: 'CSPC Auditorium', location: 'Main campus' } },
            readiness_steps: [{ key: 'contest', label: 'Contest', state: 'prepared', detail: 'Prepared' }, { key: 'panel', label: 'Judge panel', state: 'pending', detail: 'Not configured' }],
            actions: { prepare: null, panel: null, lock: null },
        }]}
    />);

    expect(screen.getByRole('link', { name: 'People' })).not.toHaveAttribute('aria-current');
    expect(screen.getByRole('link', { name: 'Scoring Readiness' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText('Essay Writing')).toBeInTheDocument();
    expect(screen.getByText('Criteria total 95 while the source prints 100.')).toBeInTheDocument();
    expect(screen.getByText('Blocked', { selector: 'span' })).toBeInTheDocument();
    expect(screen.getByText('Morning judging call')).toBeInTheDocument();
    expect(screen.getByText('CSPC Auditorium · Main campus')).toBeInTheDocument();
    expect(screen.getByText('Judge panel')).toBeInTheDocument();
});

test('assignments section is a separate operational workspace', () => {
    render(<AdminStaff
        event={{ id: '1', name: 'SIKLAB 2026', archived: false }}
        section="assignments"
        staff={[{
            id: '5',
            name: 'Maria Santos',
            email: 'maria@example.com',
            account_state: 'active',
            roles: [{ id: 'r1', role: 'judge' }],
            assignments: [],
            coverage: {
                judging_panels: [{ contest_id: '81', label: 'Pop Solo / Individual / Final', entry_count: 7, locked: false }],
                tabulator_targets: [],
                missing_roles: [],
                total: 1,
            },
            judging_assignments: [{ id: '81', scope: 'judging_panel', label: 'Pop Solo / Individual / Final', scorecard_count: 7 }],
            tabulator_assignments: [],
            invitation: null,
            event_memberships: [],
            audit: [],
        }]}
        targets={{ competition_division: [], contest: [] }}
        readiness={[]}
    />);

    expect(screen.getByRole('heading', { name: 'Assignment coverage' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Judges and Tabulators' })).not.toBeInTheDocument();
    expect(screen.getByText('Pop Solo / Individual / Final')).toBeInTheDocument();
    expect(screen.getByText('7 scorecards')).toBeInTheDocument();
});

test('assignment summary counts unique judging panels rather than Judge rows', () => {
    const person = (id, name) => ({
        id,
        name,
        email: `${id}@example.com`,
        roles: [{ id: `r-${id}`, role: 'judge' }],
        coverage: { judging_panels: [{ contest_id: '81', division_id: '30', label: 'Pop Solo / Final', entry_count: 7 }], tabulator_targets: [], missing_roles: [] },
    });

    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="assignments" staff={[person('5', 'Judge One'), person('6', 'Judge Two')]} targets={{ competition_division: [], contest: [] }} readiness={[]}/>);

    expect(screen.getByText('Judging panels').nextElementSibling).toHaveTextContent('1');
    expect(screen.getAllByText('Pop Solo / Final')).toHaveLength(2);
    expect(screen.getAllByRole('link', { name: 'Manage panel' })[0]).toHaveAttribute('href', '/admin/events/1/staff?section=readiness&focus=30');
});

test('readiness uses the server-provided next workflow action', () => {
    render(<AdminStaff
        event={{ id: '1', name: 'SIKLAB 2026', archived: false }}
        section="readiness"
        staff={[]}
        targets={{ competition_division: [], contest: [] }}
        readiness={[{
            id: '30',
            name: 'Pop Solo',
            state: 'needs_attention',
            next_action_key: 'aggregation',
            counts: { entries: 7, judges: 3, tabulators: 0 },
            next_blocker: 'Confirm aggregation authority.',
            readiness_steps: [],
            actions: {
                panel: '/panel',
                aggregation: '/aggregation',
                judge_options: [],
                tabulator_options: [],
            },
        }]}
    />);

    screen.getByText('Open setup').click();
    expect(screen.getByRole('heading', { name: 'Judge score aggregation' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Judging panel' })).not.toBeInTheDocument();
});

test('panel editing starts with the current Judge membership selected', () => {
    render(<AdminStaff
        event={{ id: '1', name: 'SIKLAB 2026', archived: false }}
        section="readiness"
        staff={[]}
        targets={{ competition_division: [], contest: [] }}
        readiness={[{
            id: '30',
            name: 'Pop Solo',
            state: 'needs_attention',
            next_action_key: 'panel',
            counts: { entries: 7, judges: 2, tabulators: 0 },
            next_blocker: 'Configure the judging panel.',
            current_judge_ids: ['10', '11'],
            readiness_steps: [],
            actions: {
                panel: '/panel',
                judge_options: [{ id: '10', name: 'Judge 10' }, { id: '11', name: 'Judge 11' }],
                tabulator_options: [],
            },
        }]}
    />);

    screen.getByText('Open setup').click();
    expect(screen.getByRole('checkbox', { name: 'Judge 10' })).toBeChecked();
    expect(screen.getByRole('checkbox', { name: 'Judge 11' })).toBeChecked();
});

test('staff drawer keeps Judge panel assignments separate from Tabulator scopes', () => {
    render(<AdminStaff
        event={{ id: '1', name: 'SIKLAB 2026', archived: false }}
        section="people"
        targets={{ competition_division: [{ id: '10', label: 'Pop Solo / Individual' }], contest: [{ id: '20', label: 'Pop Solo / Final' }] }}
        readiness={[]}
        staff={[{
            id: '5',
            name: 'Dual-role operator',
            email: 'dual@example.com',
            account_state: 'active',
            roles: [{ id: 'r1', role: 'judge' }, { id: 'r2', role: 'tabulator' }],
            assignments: [],
            judging_assignments: [{ id: 'j1', scope: 'judging_panel', label: 'Pop Solo / CCS' }],
            tabulator_assignments: [],
            invitation: null,
            event_memberships: [],
            audit: [],
        }]}
    />);

    fireEvent.click(screen.getByRole('button', { name: /Dual-role operator/ }));

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Judging assignments' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Tabulator assignments' })).toBeInTheDocument();
    expect(screen.getByText('Assignments are managed through judging panels.')).toBeInTheDocument();
    expect(screen.queryByRole('option', { name: 'Scorecard' })).not.toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: 'Tabulator assignment scope' })).toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: 'Role to grant' })).not.toBeInTheDocument();
    expect(screen.getByText('All supported scoring roles are already granted.')).toBeInTheDocument();
});

test('staff drawer offers only scoring roles the person does not already have', () => {
    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="people" targets={{ competition_division: [], contest: [] }} readiness={[]} staff={[{
        id: '5', name: 'Judge only', email: 'judge@example.com', account_state: 'active',
        roles: [{ id: 'r1', role: 'judge' }], assignments: [], judging_assignments: [], tabulator_assignments: [], invitation: null, event_memberships: [], audit: [],
    }]}/>);
    fireEvent.click(screen.getByRole('button', { name: /Judge only/ }));
    const roleSelect = screen.getByRole('combobox', { name: 'Role to grant' });
    expect(within(roleSelect).queryByRole('option', { name: 'Judge' })).not.toBeInTheDocument();
    expect(within(roleSelect).getByRole('option', { name: 'Tabulator' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Revoke' }));
    expect(screen.getByRole('dialog', { name: 'Revoke judge role' })).toBeInTheDocument();
});

test('staff drawer offers Judge to a Tabulator-only person', () => {
    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="people" targets={{ competition_division: [], contest: [] }} readiness={[]} staff={[{
        id: '6', name: 'Tabulator only', email: 'tabulator@example.com', account_state: 'active',
        roles: [{ id: 'r2', role: 'tabulator' }], assignments: [], judging_assignments: [], tabulator_assignments: [], invitation: null, event_memberships: [], audit: [],
    }]}/>);
    fireEvent.click(screen.getByRole('button', { name: /Tabulator only/ }));
    const roleSelect = screen.getByRole('combobox', { name: 'Role to grant' });
    expect(within(roleSelect).getByRole('option', { name: 'Judge' })).toBeInTheDocument();
    expect(within(roleSelect).queryByRole('option', { name: 'Tabulator' })).not.toBeInTheDocument();
});

test('readiness separates setup from live status and presents aggregation as fixed policy', () => {
    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="readiness" staff={[]} targets={{ competition_division: [], contest: [] }} readiness={[{
        id: '30', name: 'Pop Solo', state: 'needs_attention', next_action_key: 'aggregation', counts: { entries: 7, judges: 3, tabulators: 0 },
        next_blocker: 'Confirm aggregation authority.', readiness_steps: [
            { key: 'contest', label: 'Contest', state: 'prepared', detail: 'Prepared' },
            { key: 'aggregation', label: 'Aggregation', state: 'pending', detail: 'Not confirmed' },
            { key: 'scores', label: 'Judge scores', state: 'pending', detail: 'Waiting for scores' },
        ], actions: { aggregation: '/aggregation', judge_options: [], tabulator_options: [] },
    }]}/>);
    screen.getByText('Open setup').click();
    expect(screen.getByRole('heading', { name: 'Setup readiness' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Live readiness' })).toBeInTheDocument();
    expect(screen.getByText('Average of Judge totals')).toBeInTheDocument();
    expect(screen.queryByRole('combobox', { name: /aggregation/i })).not.toBeInTheDocument();
});

test('readiness summarizes workflow states and blocks an unavailable Tabulator step', () => {
    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="readiness" staff={[]} targets={{ competition_division: [], contest: [] }} readiness={[
        { id: '30', name: 'Pop Solo', competition: 'Pop Solo', state: 'needs_attention', next_action_key: 'tabulator', tabulator_available: false, counts: { entries: 7, judges: 3, tabulators: 0 }, next_blocker: 'Assign an active Tabulator to this activity.', readiness_steps: [{ key: 'tabulator', label: 'Tabulator', state: 'pending', detail: 'Not assigned' }], actions: { tabulator_options: [] } },
        { id: '31', name: 'Photography', competition: 'Photography', state: 'ready', next_action_key: null, counts: { entries: 7, judges: 3, tabulators: 1 }, readiness_steps: [], actions: {} },
        { id: '32', name: 'Essay Writing', competition: 'Essay Writing', state: 'needs_attention', next_action_key: 'panel', counts: { entries: 7, judges: 0, tabulators: 0 }, readiness_steps: [], actions: { panel: '/panel', judge_options: [] } },
    ]}/>);

    expect(screen.getByText('Ready', { selector: 'dt' }).nextElementSibling).toHaveTextContent('1');
    expect(screen.getByText('Need Setup', { selector: 'dt' }).nextElementSibling).toHaveTextContent('1');
    expect(screen.getByText('Blocked', { selector: 'dt' }).nextElementSibling).toHaveTextContent('1');
    const blockedActivity = screen.getByRole('heading', { name: 'Pop Solo' }).closest('details');
    const tabulatorStep = within(blockedActivity).getByText('Tabulator', { selector: 'span' }).closest('li');
    expect(tabulatorStep).toHaveTextContent('Blocked');
    expect(screen.getByRole('link', { name: 'Open People' })).toBeInTheDocument();
});

test('lock confirmation opens only on demand and closes through the accessible dialog', () => {
    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="readiness" staff={[]} targets={{ competition_division: [], contest: [] }} readiness={[{
        id: '30', name: 'Pop Solo', competition: 'Pop Solo', state: 'needs_attention', next_action_key: 'lock', tabulator_available: true,
        counts: { entries: 7, judges: 3, tabulators: 1 }, readiness_steps: [], actions: { lock: '/lock' },
    }]}/>);
    screen.getByText('Open setup').click();
    expect(screen.queryByRole('dialog', { name: 'Lock judging panel' })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Lock judging panel' }));
    expect(screen.getByRole('dialog', { name: 'Lock judging panel' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(screen.queryByRole('dialog', { name: 'Lock judging panel' })).not.toBeInTheDocument();
});

test('tie resolution remains actionable in live operations', () => {
    render(<AdminStaff event={{ id: '1', name: 'SIKLAB 2026', archived: false }} section="readiness" staff={[]} targets={{ competition_division: [], contest: [] }} readiness={[{
        id: '30', name: 'Pop Solo', competition: 'Pop Solo', state: 'needs_attention', next_action_key: 'tie', tabulator_available: true,
        counts: { entries: 2, judges: 3, tabulators: 1 }, readiness_steps: [], actions: {},
        tie: { entry_ids: ['9', '10'], entries: [{ id: '9', name: 'Team A' }, { id: '10', name: 'Team B' }], action: '/tie-resolution' },
    }]}/>);
    screen.getByText('Open setup').click();
    expect(screen.getByText('Resolve tied entries')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Move Team B up' })).toBeEnabled();
    expect(screen.getByRole('textbox', { name: 'Tie resolution administrative reference' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Authorize tie order' })).toBeInTheDocument();
});

test('archived readiness keeps panel setup controls disabled', () => {
    render(<AdminStaff
        event={{ id: '1', name: 'SIKLAB 2026', archived: true }}
        section="readiness"
        staff={[]}
        targets={{ competition_division: [], contest: [] }}
        readiness={[{
            id: '30',
            name: 'Pop Solo',
            state: 'needs_attention',
            next_action_key: 'panel',
            counts: { entries: 7, judges: 0, tabulators: 0 },
            readiness_steps: [],
            current_judge_ids: [],
            actions: { panel: '/panel', judge_options: [{ id: '10', name: 'Judge 10' }] },
        }]}
    />);

    screen.getByText('Open setup').click();

    expect(screen.getByRole('checkbox', { name: 'Judge 10' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Save panel' })).toBeDisabled();
});

test('judge and tabulator landing pages present operational work modes', () => {
    const { rerender } = render(<JudgeIndex
        event={{ id: '1', name: 'SIKLAB 2026' }}
        summary={{ assigned: 1, submitted: 0 }}
        contests={[{
            name: 'Pop Solo',
            competition: 'Pop Solo',
            division: 'Individual',
            entry_count: 1,
            scorecard_count: 1,
            counts: { not_started: 1, in_progress: 0, needs_correction: 0, submitted: 0, approved: 0, blocked: 0 },
            schedule: { starts_at: '2026-11-13T13:00:00+08:00', ends_at: null, venue: { name: 'CSPC Auditorium', location: 'Main campus' } },
            scorecards: [{ id: '1', entry: 'CCS', status: 'not_started', status_label: 'Not started', href: '/judge/scorecards/1' }],
            readiness: { ready: true, next_blocker: null },
        }]}
    />);
    expect(screen.getByRole('heading', { name: 'My Judging' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Pop Solo' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: "Today's judging schedule" })).toBeInTheDocument();
    expect(screen.getByText('Pop Solo · Individual')).toBeInTheDocument();
    expect(screen.getByText('CSPC Auditorium · Main campus')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Start scorecard' })).toHaveAttribute('href', '/judge/scorecards/1');

    rerender(<TabulatorIndex
        event={{ id: '1', name: 'SIKLAB 2026' }}
        summary={{ judged: 1, objective: 1 }}
        judged={[{ name: 'Story Telling', mode: 'judged', completion: { submitted: 18, expected: 21, waiting: 3 }, readiness: { ready: false, next_blocker: 'Waiting for 3 Judge scorecards.' }, href: '/tabulator/contests/1' }]}
        objective={[{ name: 'Basketball Men', mode: 'objective', state: 'scheduled', state_label: 'Ready', href: '/tabulator/contests/2' }]}
    />);
    expect(screen.getByRole('heading', { name: 'My Tabulation' })).toBeInTheDocument();
    expect(screen.getByText('Judged')).toBeInTheDocument();
    expect(screen.getByText('Objective')).toBeInTheDocument();
    expect(screen.getAllByText('Waiting for 3 Judge scorecards.')).not.toHaveLength(0);
});

test('blocked judge scorecards remain visible without a link', () => {
    render(<JudgeIndex
        event={{ id: '1', name: 'SIKLAB 2026' }}
        summary={{ assigned: 1, submitted: 0, blocked: 1 }}
        contests={[{
            name: 'Essay Writing',
            competition: 'Literary Events',
            division: 'Individual',
            scorecard_count: 1,
            counts: { not_started: 0, in_progress: 0, needs_correction: 0, submitted: 0, approved: 0, blocked: 1 },
            schedule: { starts_at: null, ends_at: null, venue: null },
            scorecards: [{ id: '22', entry: 'CCS Essayist', status: 'blocked', status_label: 'Blocked', href: null }],
            readiness: { ready: false, next_blocker: 'Criteria total 95 while the source prints 100.' },
        }]}
    />);

    const blockedCard = screen.getByText('CCS Essayist').closest('article');
    expect(within(blockedCard).getByText('Blocked')).toBeInTheDocument();
    expect(within(blockedCard).getByText('Waiting for readiness')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /open|start/i })).not.toBeInTheDocument();
});
