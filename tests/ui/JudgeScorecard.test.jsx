import React from 'react';
import { act, fireEvent, render, screen, within } from '@testing-library/react';
import { beforeEach, expect, test, vi } from 'vitest';

const patch = vi.fn();
const post = vi.fn();
const routerPost = vi.fn();
const draftErrors = {};
const submitErrors = {};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, ...props }) => <a href={href} {...props}>{children}</a>,
    router: { post: routerPost },
    usePage: () => ({ props: { flash: {} } }),
    useForm: (initial) => {
        const [, rerender] = React.useReducer((value) => value + 1, 0);
        const ref = React.useRef();
        if (!ref.current) {
            let defaults = initial;
            let transformer = (data) => data;
            const errors = Object.hasOwn(initial, 'values') ? draftErrors : submitErrors;
            ref.current = {
                data: initial,
                errors,
                processing: false,
                isDirty: false,
                setData: (key, value) => {
                    ref.current.data = { ...ref.current.data, [key]: value };
                    ref.current.isDirty = true;
                    rerender();
                },
                transform: (callback) => {
                    transformer = callback;
                    return ref.current;
                },
                patch: (url, options) => patch(url, transformer(ref.current.data), options),
                post: (url, options) => post(url, transformer(ref.current.data), options),
                defaults: (nextDefaults) => { defaults = nextDefaults; },
                reset: () => {
                    ref.current.data = defaults;
                    ref.current.isDirty = false;
                    rerender();
                },
            };
        }
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
    routerPost.mockReset();
    Object.keys(draftErrors).forEach((key) => delete draftErrors[key]);
    Object.keys(submitErrors).forEach((key) => delete submitErrors[key]);
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

test('builds sparse draft payloads from nonblank rubric values only', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture({
        official_deduction_total: '1.0000',
        official_adjustments: [{ id: '5', label: 'Performance time penalty', points: '1.0000', input: '289 seconds', recorded_by: 'Tabulator' }],
        values: { 1: { raw_value: '90', deduction: '0', notes: 'Strong tone' }, 2: { raw_value: '', deduction: '0', notes: 'Ignore without a score' } },
    })} />);

    expect(screen.getByText('Performance time penalty')).toBeInTheDocument();
    expect(screen.queryByRole('spinbutton', { name: /Deduction/i })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
    expect(patch).toHaveBeenCalledWith('/judge.scorecards.update/11', {
        expected_revision: 0,
        values: [{ criterion_id: '1', raw_value: '90', deduction: 0, notes: 'Strong tone' }],
    }, expect.objectContaining({ preserveScroll: true }));
});

test('submits through its own form and keeps the server revision for the next draft save', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Review and submit' }));
    const firstSaveOptions = patch.mock.calls[0][2];
    act(() => {
        firstSaveOptions.onSuccess({ props: { scorecard: fixture({ revision: 7, calculated_total: '89.0000' }) } });
    });

    expect(post).toHaveBeenCalledWith('/judge.scorecards.submit/11', {}, expect.objectContaining({ preserveScroll: true }));
    expect(routerPost).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Save draft' }));
    expect(patch.mock.calls[1][1].expected_revision).toBe(7);
});

test('shows required scoring progress and a review submission action', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture({
        values: { 1: { raw_value: '90', deduction: '0', notes: '' }, 2: { raw_value: '', deduction: '0', notes: '' } },
    })} />);

    expect(screen.getByRole('progressbar', { name: 'Required criteria' })).toHaveAttribute('aria-valuenow', '1');
    expect(screen.getByText('1 of 2 required criteria scored')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Review and submit' })).toBeInTheDocument();
});

test('synchronizes values when fresh scorecard props arrive for a clean form', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    const { rerender } = render(<Scorecard scorecard={fixture()} />);

    rerender(<Scorecard scorecard={fixture({
        revision: 1,
        calculated_total: '91.0000',
        values: { 1: { raw_value: '91', deduction: '0', notes: '' }, 2: { raw_value: '87', deduction: '0', notes: '' } },
    })} />);

    expect(screen.getByRole('spinbutton', { name: 'Tone Quality Score' })).toHaveValue(91);
    expect(screen.getByText('91.00')).toBeInTheDocument();
});

test('preserves dirty local input when fresher scorecard props arrive', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    const { rerender } = render(<Scorecard scorecard={fixture()} />);
    const toneInput = screen.getByRole('spinbutton', { name: 'Tone Quality Score' });

    fireEvent.change(toneInput, { target: { value: '92' } });
    rerender(<Scorecard scorecard={fixture({
        revision: 1,
        values: { 1: { raw_value: '70', deduction: '0', notes: '' }, 2: { raw_value: '75', deduction: '0', notes: '' } },
    })} />);

    expect(screen.getByRole('spinbutton', { name: 'Tone Quality Score' })).toHaveValue(92);
});

test('renders field errors beside their criterion and top-level errors separately', async () => {
    draftErrors['values.1.raw_value'] = 'Enter a valid Musicianship score.';
    draftErrors.scorecard = 'The scorecard revision is stale.';
    submitErrors.scorecard = 'Every required criterion must have a score before submission.';
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');

    render(<Scorecard scorecard={fixture()} />);

    const musicianship = screen.getByRole('group', { name: 'Musicianship' });
    expect(within(musicianship).getByText('Enter a valid Musicianship score.')).toBeInTheDocument();
    expect(within(musicianship).getByRole('spinbutton', { name: 'Musicianship Score' })).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('alert')).toHaveTextContent('The scorecard revision is stale.');
    expect(screen.getByRole('alert')).toHaveTextContent('Every required criterion must have a score before submission.');
    expect(screen.getByRole('alert')).not.toHaveTextContent('Enter a valid Musicianship score.');
});

test('shows correction feedback for rejected scorecards', async () => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture({ state: 'rejected', rejection_reason: 'Complete the missing criterion.' })} />);

    expect(screen.getByRole('alert')).toHaveTextContent('Complete the missing criterion.');
    expect(screen.getByRole('button', { name: 'Save draft' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Resubmit scorecard' })).toBeInTheDocument();
});

test.each(['submitted', 'approved'])('hides editing actions for %s scorecards', async (state) => {
    const { default: Scorecard } = await import('../../resources/js/Pages/Judge/Scorecard');
    render(<Scorecard scorecard={fixture({ state })} />);

    expect(screen.queryByRole('button', { name: 'Save draft' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Review and submit' })).not.toBeInTheDocument();
    expect(screen.getByRole('spinbutton', { name: 'Tone Quality Score' })).toBeDisabled();
});
