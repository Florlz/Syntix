import React from 'react';
import { render, screen } from '@testing-library/react';
import { expect, test } from 'vitest';
import OperationalStatus from '../../resources/js/Components/LiveDesk/OperationalStatus';
import ScheduleTime from '../../resources/js/Components/LiveDesk/ScheduleTime';
import LiveProgress from '../../resources/js/Components/LiveDesk/LiveProgress';

test('operational status exposes its label and explanation', () => {
    render(<OperationalStatus label="Waiting" detail="Waiting for 3 Judge scorecards." tone="danger" />);

    expect(screen.getByText('Waiting')).toBeInTheDocument();
    expect(screen.getByText('Waiting for 3 Judge scorecards.')).toBeInTheDocument();
});

test('schedule time presents scheduled and pending values', () => {
    const { rerender } = render(<ScheduleTime startsAt="2026-11-13T09:00:00+08:00" />);

    expect(screen.getByText(/9:00/)).toBeInTheDocument();

    rerender(<ScheduleTime startsAt={null} />);

    expect(screen.getByText('Unscheduled')).toBeInTheDocument();
    expect(screen.getByText('Date pending')).toBeInTheDocument();
});

test('live progress exposes completed work to assistive technology', () => {
    render(<LiveProgress label="Required criteria" value={2} max={3} detail="2 of 3 scored" />);

    expect(screen.getByRole('progressbar', { name: 'Required criteria' })).toHaveAttribute('aria-valuenow', '2');
    expect(screen.getByText('2 of 3 scored')).toBeInTheDocument();
});
