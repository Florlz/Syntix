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
    useForm: () => ({ data: { role: 'judge', scope_type: 'contest', target_id: '', judge_ids: [] }, setData: vi.fn(), post: vi.fn(), patch: vi.fn(), transform: vi.fn(function () { return this; }), processing: false, reset: vi.fn() }),
    usePage,
    router: { visit: vi.fn(), post: vi.fn() },
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (!name) return { current: () => false };
        if (name === 'judge.index') return '/judge';
        if (name === 'tabulator.index') return '/tabulator';
        if (name === 'admin.staff.index') return `/admin/events/${params?.event ?? params}/staff${params?.section ? `?section=${params.section}` : ''}`;
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
    expect(screen.getByText('Blocked')).toBeInTheDocument();
    expect(screen.getByText('Morning judging call')).toBeInTheDocument();
    expect(screen.getByText('CSPC Auditorium · Main campus')).toBeInTheDocument();
    expect(screen.getByText('Judge panel')).toBeInTheDocument();
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
