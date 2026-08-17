import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import JudgedContest from '../../resources/js/Pages/Tabulator/JudgedContest';

const post = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post },
    usePage: () => ({ props: { auth: { user: { name: 'Tabulator' } }, flash: {}, errors: {} } }),
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

beforeEach(() => {
    post.mockReset();
    globalThis.route = (name) => name ? `/${name}` : { current: () => false };
});

const props = {
    contest: { id: '9', name: 'Story Telling', state: 'in_progress', panel_locked: true },
    tabulation: {
        readiness: { ready: false, blocker_codes: ['missing_scorecards'] },
        entries: [{
            entry_id: '3', entry: 'CCS', delegation: 'College of Computer Studies',
            scorecards: [
                { judge_id: '11', judge: 'Judge A', raw_total: '88.5000' },
                { judge_id: '12', judge: 'Judge B', raw_total: null },
            ],
            adjustments: [], aggregate_raw_total: null, adjustment_total: '1.0000', final_total: null,
        }],
    },
    adjustment_configuration: { code: 'performance_time', calculation_status: 'authorized', input_unit: 'seconds' },
};

test('renders judge evidence as read-only and blocks finalization while incomplete', () => {
    render(<JudgedContest {...props} />);

    expect(screen.getByRole('heading', { name: 'Judge score matrix' })).toBeInTheDocument();
    expect(screen.getAllByText('88.5000').length).toBeGreaterThan(0);
    expect(screen.queryByRole('textbox', { name: /Judge A/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Finalize & submit result' })).toBeDisabled();
    expect(screen.getByText('missing scorecards')).toBeInTheDocument();
});

test('records objective adjustment facts without exposing raw score editing', () => {
    render(<JudgedContest {...props} />);

    fireEvent.change(screen.getByLabelText('Objective seconds'), { target: { value: '451' } });
    fireEvent.click(screen.getByRole('button', { name: 'Record adjustment' }));

    expect(post).toHaveBeenCalledWith('/tabulator.judged.adjustments.store', expect.objectContaining({
        code: 'performance_time', input_value: 451, input_unit: 'seconds',
    }), expect.any(Object));
});
