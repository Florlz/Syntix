import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import Index from '../../resources/js/Pages/Admin/Approvals/Index';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ href, children, ...props }) => <a href={href} {...props}>{children}</a>,
    usePage: () => ({ props: { flash: {}, errors: {} } }),
    useForm: (initial = {}) => ({ data: initial, processing: false, post: vi.fn(), setData: vi.fn() }),
}));
vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <div>{children}</div> }));

const event = { id: '1', name: 'SIKLAB 2026', archived: false };
const workspace = {
    sport: { id: '20', name: 'Basketball', active: true, division_count: 2, entry_count: 9 },
    divisions: [{ id: '30', name: 'Women', active: true, entry_count: 4 }, { id: '31', name: 'Men', active: true, entry_count: 5 }],
};

beforeEach(() => {
    globalThis.route = (name, params) => {
        if (name === 'admin.sports.index') return `/admin/events/${params}/sports`;
        if (name === 'admin.sports.show') return `/admin/events/${params[0]}/sports/${params[1]}`;
        if (name === 'admin.sports.schedules') return `/admin/events/${params[0]}/schedules`;
        if (name === 'admin.approvals.index') return `/admin/events/${params[0]}/approvals`;
        if (name === 'dashboard') return `/admin/events/${params.event}`;
        return `/${name}`;
    };
});

test('uses the shared shell for selected sport results without the old context panel', () => {
    render(<Index
        event={event}
        scope={{ competition: 'Basketball', competition_id: '20', division: 'Women', division_id: '30' }}
        workspace={workspace}
    />);

    expect(screen.getByRole('heading', { name: 'Basketball' })).toBeInTheDocument();
    expect(screen.getByText('Women', { selector: '[data-workspace-division]' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Results' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('link', { name: 'Women' })).toHaveAttribute('aria-current', 'page');
    expect(screen.getByRole('heading', { name: 'Review submitted outcomes' })).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Sport context' })).not.toBeInTheDocument();
});
