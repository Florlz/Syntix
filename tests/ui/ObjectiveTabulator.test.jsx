import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Contest from '../../resources/js/Pages/Tabulator/Contest';

const enqueueContestCommand = vi.hoisted(() => vi.fn());
const synchronizePendingCommands = vi.hoisted(() => vi.fn().mockResolvedValue([]));
const reload = vi.hoisted(() => vi.fn());

vi.mock('@/lib/commandOutbox', () => ({ enqueueContestCommand, synchronizePendingCommands }));
vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { reload },
    usePage: () => ({ props: { auth: { user: { name: 'Tabulator' } }, flash: {}, errors: {} } }),
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

const propsFor = (state) => ({ contest: {
    id: '9', event_id: '1', division_id: '2', name: 'Basketball Men', competition: 'Basketball', division: 'Men', state,
    revision: 0, outcome_profile: 'team_total', live_payload: { home: 0, away: 0 }, result_payload: null,
    entries: [{ id: '1', name: 'Home', slot: 1 }, { id: '2', name: 'Away', slot: 2 }],
} });

beforeEach(() => {
    enqueueContestCommand.mockReset();
    synchronizePendingCommands.mockReset().mockResolvedValue([]);
    reload.mockReset();
    globalThis.route = (name) => name ? `/${name}` : { current: () => false };
});

test('scheduled contests expose only the start action', () => {
    render(<Contest {...propsFor('scheduled')} />);
    expect(screen.getByRole('button', { name: 'Start contest' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Save live evidence' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Complete contest' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Submit for review' })).not.toBeInTheDocument();
});

test('live contests expose save and complete actions only', () => {
    render(<Contest {...propsFor('live')} />);
    expect(screen.getByRole('button', { name: 'Save live evidence' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Complete contest' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Start contest' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Submit for review' })).not.toBeInTheDocument();
});

test('completed contests expose submission without live mutation controls', () => {
    render(<Contest {...propsFor('completed')} />);
    expect(screen.getByRole('button', { name: 'Submit for review' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Start contest' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Save live evidence' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Complete contest' })).not.toBeInTheDocument();
});

test('submitted contests are read-only', () => {
    render(<Contest {...propsFor('submitted')} />);
    expect(screen.getAllByText('Submitted for review')).toHaveLength(2);
    expect(screen.queryByRole('button', { name: 'Start contest' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Save live evidence' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Complete contest' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Submit for review' })).not.toBeInTheDocument();
});
