import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, test, vi } from 'vitest';
import CreateAccount from '../../resources/js/Pages/Admin/Accounts/Create';
import SetupAccount from '../../resources/js/Pages/Auth/SetupAccount';
import JudgeIndex from '../../resources/js/Pages/Judge/Index';
import TabulatorIndex from '../../resources/js/Pages/Tabulator/Index';

const usePage = vi.hoisted(() => vi.fn());
const post = vi.hoisted(() => vi.fn());
const qrToDataUrl = vi.hoisted(() => vi.fn().mockResolvedValue('data:image/png;base64,syntix-qr'));

vi.mock('qrcode', () => ({ default: { toDataURL: qrToDataUrl } }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, method, as: Component = 'a', ...props }) => React.createElement(Component, { href, 'data-method': method, ...props }, children),
    usePage,
    useForm: (initial) => {
        const [data, setDataState] = React.useState(initial);
        const setData = (key, value) => setDataState((current) => typeof key === 'function' ? key(current) : { ...current, [key]: value });
        return { data, setData, post, errors: {}, processing: false };
    },
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ header, children }) => <div>{header}{children}</div> }));
vi.mock('@/Components/InputError', () => ({ default: () => null }));

beforeEach(() => {
    post.mockReset();
    qrToDataUrl.mockClear();
    globalThis.route = (name, params) => {
        if (name === 'admin.accounts.store') return `/admin/events/${params}/accounts`;
        if (name === 'account.setup.switch') return `/account-setup/${params}/switch`;
        return `/${name}`;
    };
});

test('staff invitation creates identity and role without a scoring target and offers a printable setup card', async () => {
    usePage.mockReturnValue({ props: { flash: {
        setup_url: 'https://syntix.test/account-setup/secret-token',
        setup_invitation: { name: 'Juan Dela Cruz', role_label: 'Judge', expires_at: '2026-08-23T02:15:00+08:00' },
    } } });

    render(<CreateAccount event={{ id: '1', name: 'SIKLAB 2026' }} />);

    expect(screen.getByRole('heading', { name: 'Invite event staff', level: 2 })).toBeInTheDocument();
    expect(screen.queryByText('Assignment coverage')).not.toBeInTheDocument();
    expect(screen.queryByText('Exact assignment')).not.toBeInTheDocument();
    expect(screen.getByDisplayValue('https://syntix.test/account-setup/secret-token')).toHaveAttribute('readonly');
    expect(screen.queryByRole('link', { name: /account-setup\/secret-token/ })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Copy setup link' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Print setup card' })).toBeInTheDocument();
    expect(screen.getByText('Juan Dela Cruz')).toBeInTheDocument();
    expect(screen.getByText('PRIVATE ONE-TIME CREDENTIAL')).toBeInTheDocument();
    await waitFor(() => expect(qrToDataUrl).toHaveBeenCalledWith('https://syntix.test/account-setup/secret-token', expect.any(Object)));
});

test('setup link opened by another signed-in account explains the conflict instead of showing password fields', () => {
    usePage.mockReturnValue({ props: { flash: {} } });

    render(<SetupAccount
        valid
        email="judge@syntix.test"
        conflict
        authenticatedEmail="admin@syntix.test"
        switchUrl="/account-setup/token/switch"
    />);

    expect(screen.getByRole('heading', { name: 'Finish setup as the invited staff member' })).toBeInTheDocument();
    expect(screen.getByText(/signed in as admin@syntix\.test/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Sign out and continue' })).toHaveAttribute('data-method', 'post');
    expect(screen.queryByLabelText('Password')).not.toBeInTheDocument();
});

test('Judge queue is ordered by schedule with urgent work kept above the timeline', () => {
    usePage.mockReturnValue({ props: { auth: { user: { name: 'Judge' } }, flash: {} } });
    const contest = (name, startsAt, status = 'not_started') => ({
        name,
        competition: name,
        division: 'Open',
        scorecard_count: 1,
        counts: { not_started: status === 'not_started' ? 1 : 0, in_progress: 0, needs_correction: status === 'needs_correction' ? 1 : 0, submitted: 0, approved: 0, blocked: 0 },
        schedule: { starts_at: startsAt, title: name, venue: { name: 'Auditorium', location: 'Main campus' } },
        readiness: { ready: true, next_blocker: null },
        scorecards: [{ id: name, entry: `${name} entry`, status, status_label: status === 'needs_correction' ? 'Needs correction' : 'Not started', href: `/judge/${name}` }],
    });

    render(<JudgeIndex
        event={{ id: '1', name: 'SIKLAB 2026' }}
        summary={{ assigned: 3, needs_correction: 1 }}
        contests={[
            contest('Afternoon event', '2026-11-13T14:00:00+08:00'),
            contest('Correction event', '2026-11-13T13:00:00+08:00', 'needs_correction'),
            contest('Morning event', '2026-11-13T09:00:00+08:00'),
        ]}
    />);

    expect(screen.getByRole('heading', { name: 'Needs attention' })).toBeInTheDocument();
    const timeline = screen.getByRole('region', { name: "Today's judging schedule" });
    const timelineText = timeline.textContent;
    expect(timelineText.indexOf('Morning event')).toBeLessThan(timelineText.indexOf('Afternoon event'));
});

test('Tabulator queue combines judged and objective work into one schedule timeline', () => {
    usePage.mockReturnValue({ props: { auth: { user: { name: 'Tabulator' } }, flash: {} } });

    render(<TabulatorIndex
        event={{ id: '1', name: 'SIKLAB 2026' }}
        summary={{ judged: 1, objective: 1 }}
        judged={[{ name: 'Pop Solo', mode: 'judged', competition: 'Pop Solo', division: 'Open', schedule: { starts_at: '2026-11-13T13:00:00+08:00', venue: { name: 'Auditorium' } }, completion: { submitted: 3, expected: 3 }, readiness: { ready: true, next_blocker: null }, href: '/tabulator/1' }]}
        objective={[{ name: 'Basketball Men', mode: 'objective', competition: 'Basketball', division: 'Men', schedule: { starts_at: '2026-11-13T09:00:00+08:00', venue: { name: 'Gymnasium' } }, state: 'live', state_label: 'Live', href: '/tabulator/2' }]}
    />);

    expect(screen.queryByRole('heading', { name: 'Judged' })).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Objective' })).not.toBeInTheDocument();
    const timeline = screen.getByRole('region', { name: "Today's tabulation schedule" });
    expect(timeline.textContent.indexOf('Basketball Men')).toBeLessThan(timeline.textContent.indexOf('Pop Solo'));
});
