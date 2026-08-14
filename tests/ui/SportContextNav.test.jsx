import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import SportContextNav from '../../resources/js/Components/SportContextNav';

vi.mock('@/Components/PrefetchLink', () => ({ default: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a> }));

const event = { id: '1', name: 'SIKLAB 2026' };
const competition = { id: '20', name: 'Basketball' };
const division = { id: '30', name: 'Women' };

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.departments.index') return `/admin/events/${params}/departments`;
        if (name === 'admin.sports.tournament') return `/admin/events/${params[0]}/divisions/${params[1]}/tournament`;
        if (name === 'admin.sports.schedules') return `/admin/events/${params.event}/sports-schedules?competition=${params.competition}${params.division ? `&division=${params.division}` : ''}`;
        if (name === 'admin.approvals.index') return `/admin/events/${params.event}/approvals?competition=${params.competition}${params.division ? `&division=${params.division}` : ''}`;
        return `/${name}`;
    };
});

test('keeps every scoped workflow link on the selected sport and division', () => {
    render(<SportContextNav event={event} competition={competition} division={division} currentTask="bracket" />);

    expect(screen.getByText('Basketball / Women')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Back to Basketball' })).toHaveAttribute('href', '/admin/events/1/sports/20?division=30');
    expect(screen.getByRole('link', { name: 'Teams' })).toHaveAttribute('href', '/admin/events/1/departments');
    expect(screen.getByRole('link', { name: 'Bracket' })).toHaveAttribute('href', '/admin/events/1/divisions/30/tournament');
    expect(screen.getByRole('link', { name: 'Schedule' })).toHaveAttribute('href', '/admin/events/1/sports-schedules?competition=20&division=30');
    expect(screen.getByRole('link', { name: 'Results' })).toHaveAttribute('href', '/admin/events/1/approvals?competition=20&division=30');
});

test('marks the current workflow page for assistive technology', () => {
    render(<SportContextNav event={event} competition={competition} division={division} currentTask="schedule" />);

    expect(screen.getByRole('link', { name: 'Schedule' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Bracket' })).not.toHaveAttribute('aria-current');
});

test('keeps sport-wide schedule and results scoped while asking for a division before bracket', () => {
    render(<SportContextNav event={event} competition={competition} currentTask="results" />);

    const bracket = screen.getByText('Choose division for bracket');
    expect(bracket).toHaveAttribute('aria-disabled', 'true');
    expect(bracket).not.toHaveAttribute('href');
    expect(bracket).toHaveAttribute('title', 'Choose a division first');
    expect(screen.getByRole('link', { name: 'Schedule' })).toHaveAttribute('href', '/admin/events/1/sports-schedules?competition=20');
    expect(screen.getByRole('link', { name: 'Results' })).toHaveAttribute('href', '/admin/events/1/approvals?competition=20');
});

test('routes teams to Departments and keeps the hub canonical without a legacy tab', () => {
    render(<SportContextNav event={event} competition={competition} division={division} currentTask="results" />);

    expect(screen.getByRole('link', { name: 'Teams' })).toHaveAttribute('href', '/admin/events/1/departments');
    expect(screen.getByRole('link', { name: 'Back to Basketball' })).toHaveAttribute('href', '/admin/events/1/sports/20?division=30');
    expect(screen.getByRole('link', { name: 'Back to Basketball' })).not.toHaveAttribute('href', expect.stringContaining('tab='));
});
