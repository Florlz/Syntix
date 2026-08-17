import React from 'react';
import { render, screen } from '@testing-library/react';
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

    expect(screen.getByRole('link', { name: 'Overview' })).toBeInTheDocument();
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
            source: { reliability: 'conflict', blocker: 'Criteria total 95 while the source prints 100.', pages: [19] },
            next_blocker: 'Criteria total 95 while the source prints 100.',
            counts: { entries: 7, judges: 0, tabulators: 0 },
            schedule: { starts_at: null, ends_at: null, venue: null },
            actions: { prepare: null, panel: null, lock: null },
        }]}
    />);

    expect(screen.getByRole('link', { name: 'People' })).not.toHaveAttribute('aria-current');
    expect(screen.getByRole('link', { name: 'Scoring Readiness' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByText('Essay Writing')).toBeInTheDocument();
    expect(screen.getByText('Criteria total 95 while the source prints 100.')).toBeInTheDocument();
    expect(screen.getByText('Blocked')).toBeInTheDocument();
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
            schedule: { starts_at: null, ends_at: null, venue: null },
            scorecards: [{ entry: 'CCS', status: 'not_started', status_label: 'Not started', href: '/judge/scorecards/1' }],
            readiness: { ready: true, next_blocker: null },
        }]}
    />);
    expect(screen.getByRole('heading', { name: 'My Judging' })).toBeInTheDocument();
    expect(screen.getByText('Pop Solo')).toBeInTheDocument();

    rerender(<TabulatorIndex
        event={{ id: '1', name: 'SIKLAB 2026' }}
        summary={{ judged: 1, objective: 1 }}
        judged={[{ name: 'Story Telling', completion: { submitted: 18, expected: 21, waiting: 3 }, readiness: { ready: false, next_blocker: 'Waiting for 3 Judge scorecards.' }, href: '/tabulator/contests/1' }]}
        objective={[{ name: 'Basketball Men', state_label: 'Ready', href: '/tabulator/contests/2' }]}
    />);
    expect(screen.getByRole('heading', { name: 'My Tabulation' })).toBeInTheDocument();
    expect(screen.getByText('Judged')).toBeInTheDocument();
    expect(screen.getByText('Objective')).toBeInTheDocument();
    expect(screen.getByText('Waiting for 3 Judge scorecards.')).toBeInTheDocument();
});
