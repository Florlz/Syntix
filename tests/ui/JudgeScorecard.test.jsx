import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, expect, test, vi } from 'vitest';

const patch = vi.fn();
const post = vi.fn();

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a>,
    router: { post },
    usePage: () => ({ props: { flash: {} } }),
    useForm: (initial) => {
        const [, rerender] = React.useReducer((value) => value + 1, 0);
        const ref = React.useRef();
        if (!ref.current) ref.current = {
            data: initial,
            errors: {},
            processing: false,
            isDirty: false,
            setData: (key, value) => {
                ref.current.data = { ...ref.current.data, [key]: value };
                ref.current.isDirty = true;
                rerender();
            },
            patch,
            defaults: vi.fn(),
            reset: vi.fn(),
        };
        return ref.current;
    },
}));

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ header, children }) => <div><header>{header}</header>{children}</div> }));

const fixture = (overrides = {}) => ({
    id: '11', state: 'draft', revision: 0, calculated_total: '88.5000', rejection_reason: null,
    entry: 'CCS', delegation: 'College of Computer Studies', contest: 'Pop Solo', division: 'Open', competition: 'Pop Solo',
    source: { reference: 'Proposal', pages: [21, 22], reliability: 'confirmed', blocker: null },
    instructions: ['OPM selection', 'Minus-one accompaniment', '3–7 minutes'],
    schedule: { venue: 'CSPC Auditorium', starts_at: '2026-11-13T13:00:00+08:00', ends_at: null, fallback: null },
    official_adjustments: [], official_deduction_total: '0',
    navigation: { previous_id: '10', next_id: '12', position: 2, total: 7 },
    criteria: [
        { id: '1', name: 'Tone Quality', source_label: 'Tone Quality', weight: '40.0000', maximum: '100.0000', minimum: '0.0000', input_scale: 2, required: true },
        { id: '2', name: 'Musicianship', source_label: 'Musicianship', weight: '40.0000', maximum: '100.0000', minimum: '0.0000', input_scale: 2, required: true },
    ],
    values: { 1: { raw_value: '90', deduction: '0', notes: '' }, 2: { raw_value: '85', deduction: '0', notes: '' } },
    ...overrides,
});

beforeEach(() => {
    patch.mockReset();
    post.mockReset();
    globalThis.route = (name, id) => `/${name}/${id ?? ''}`;
    window.confirm = vi.fn(() => true);
});

test('renders proposal authority, scheduled venue, exact rubric, and own-entry navigation', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture()} />);

    expect(screen.getByRole('heading', { name: 'Approved proposal pp. 21–22' })).toBeInTheDocument();
    expect(screen.getByText('CSPC Auditorium')).toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Tone Quality Score' })).toHaveAttribute('inputmode', 'decimal');
    expect(screen.getByRole('link', { name: '← Previous entry' })).toHaveAttribute('href', '/judge.scorecards.show/10');
    expect(screen.getByRole('link', { name: 'Next entry →' })).toHaveAttribute('href', '/judge.scorecards.show/12');
});

test('keeps shared deductions read-only and submits only rubric values', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture({
        official_deduction_total: '1.0000',
        official_adjustments: [{ id: '5', label: 'Performance time penalty', points: '1.0000', input: '289 seconds', recorded_by: 'Tabulator' }],
    })} />);

    expect(screen.getByText('Performance time penalty')).toBeInTheDocument();
    expect(screen.queryByRole('spinbutton', { name: /Deduction/i })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
    expect(patch).toHaveBeenCalledWith('/judge.scorecards.update/11', expect.objectContaining({ preserveScroll: true }));
});

test('shows correction context and locks submitted scorecards', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    const { rerender } = render(<Scorecard scorecard={fixture({ state: 'rejected', rejection_reason: 'Complete the missing criterion.' })} />);
    expect(screen.getByRole('alert')).toHaveTextContent('Complete the missing criterion.');

    rerender(<Scorecard scorecard={fixture({ state: 'submitted' })} />);
    expect(screen.getByRole('button', { name: 'Save draft' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Submit scorecard' })).toBeDisabled();
});
