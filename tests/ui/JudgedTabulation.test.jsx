import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import JudgedContest from '../../resources/js/Pages/Tabulator/JudgedContest';

const post = vi.hoisted(() => vi.fn());
const patch = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post, patch },
    usePage: () => ({ props: { auth: { user: { name: 'Tabulator' } }, flash: {}, errors: {} } }),
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
}));
vi.mock('@/Components/AppIcon', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/ApplicationLogo', () => ({ default: () => <span aria-hidden="true" /> }));
vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

beforeEach(() => {
    post.mockReset();
    patch.mockReset();
    globalThis.route = (name) => name ? `/${name}` : { current: () => false };
});

const props = {
    contest: { id: '9', name: 'Story Telling', state: 'live', panel_locked: true },
    tabulation: {
        operational_state: 'waiting',
        submission: null,
        readiness: { ready: false, blocker_codes: ['missing_scorecards'], blocker_labels: { missing_scorecards: 'Waiting for all Judges to submit their scorecards.' } },
        entries: [{
            entry_id: '3', entry: 'CCS', delegation: 'College of Computer Studies',
            scorecards: [
                { judge_id: '11', judge: 'Judge A', raw_total: '88.5000' },
                { judge_id: '12', judge: 'Judge B', raw_total: null },
            ],
            adjustments: [], adjustment_history: [], aggregate_raw_total: null, adjustment_total: '1.0000', final_total: null,
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
    expect(screen.getByText('Waiting for all Judges to submit their scorecards.')).toBeInTheDocument();
});

test('voids an active adjustment with PATCH and keeps voided history visible', () => {
    const view = {
        ...props,
        tabulation: {
            ...props.tabulation,
            operational_state: 'ready',
            readiness: { ready: true, blocker_codes: [], blocker_labels: {} },
            entries: [{
                ...props.tabulation.entries[0],
                adjustments: [{ id: '22', label: 'Performance time', points: '2.0000', input_value: '270', input_unit: 'seconds', recorded_by: 'Tabulator' }],
                adjustment_history: [{ id: '21', label: 'Performance time', points: '1.0000', input_value: '280', input_unit: 'seconds', status: 'voided', void_reason: 'Timing sheet corrected', recorded_by: 'Tabulator' }],
            }],
        },
    };
    vi.spyOn(window, 'prompt').mockReturnValue('Correction approved');
    render(<JudgedContest {...view} />);

    fireEvent.click(screen.getByRole('button', { name: 'Void & replace' }));

    expect(patch).toHaveBeenCalledWith('/tabulator.judged.adjustments.void', { reason: 'Correction approved' }, expect.any(Object));
    expect(screen.getByText('VOIDED')).toBeInTheDocument();
    expect(screen.getByText(/Timing sheet corrected/)).toBeInTheDocument();
});

test('records objective adjustment facts without exposing raw score editing', () => {
    render(<JudgedContest {...props} />);

    fireEvent.change(screen.getByLabelText('Objective seconds'), { target: { value: '451' } });
    fireEvent.click(screen.getByRole('button', { name: 'Record adjustment' }));

    expect(post).toHaveBeenCalledWith('/tabulator.judged.adjustments.store', expect.objectContaining({
        code: 'performance_time', input_value: 451, input_unit: 'seconds',
    }), expect.any(Object));
});
