import React, { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import RosterPlayerList from '../../resources/js/Pages/Admin/Sports/RosterPlayerList';

function Harness() {
    const [selected, setSelected] = useState(new Set());
    const members = [
        { id: '11', display_name: 'Alex Player', membership: { role: 'student_athlete', is_active: true } },
        { id: '12', display_name: 'Bea Player', membership: { role: 'reserve', is_active: true } },
    ];
    return <RosterPlayerList title="Active players" description="Players" members={members} selectedIds={selected} selectable onToggle={(id) => setSelected((current) => { const next = new Set(current); const key = String(id); next.has(key) ? next.delete(key) : next.add(key); return next; })} />;
}

test('clicking one player selects only that participant', async () => {
    const user = userEvent.setup();
    render(<Harness />);
    const first = screen.getByRole('checkbox', { name: 'Select Alex Player' });
    const second = screen.getByRole('checkbox', { name: 'Select Bea Player' });
    await user.click(first);
    expect(first).toBeChecked();
    expect(second).not.toBeChecked();
    expect(first.value).toBe('11');
    expect(second.value).toBe('12');
});

test('player list shows the roster role without an eligibility status', () => {
    const member = { id: '11', display_name: 'Alex Player', membership: { role: 'student_athlete', is_active: true } };
    render(<RosterPlayerList title="Active players" description="Players" members={[member]} />);
    expect(screen.getByText('student athlete')).toBeInTheDocument();
    expect(screen.queryByText('eligible')).not.toBeInTheDocument();
});

test('read-only membership keeps participant status access independent from generic disabled state', async () => {
    const user = userEvent.setup();
    const onManage = vi.fn();
    const member = {
        id: '11',
        display_name: 'Alex Player',
        membership: { role: 'student_athlete', is_active: true },
        capabilities: { can_manage: true, can_edit_membership: false },
    };
    render(<RosterPlayerList title="Active players" description="Players" members={[member]} onManage={onManage} disabled />);

    await user.click(screen.getByRole('button', { name: 'View status' }));
    expect(onManage).toHaveBeenCalledWith(member);
});

test('editable membership uses Manage while archived participants cannot be opened', () => {
    const onManage = vi.fn();
    const editable = {
        id: '11', display_name: 'Editable Player', membership: { role: 'student_athlete', is_active: true },
        capabilities: { can_manage: true, can_edit_membership: true },
    };
    const archived = {
        id: '12', display_name: 'Archived Player', membership: { role: 'reserve', is_active: true },
        capabilities: { can_manage: false, can_edit_membership: false },
    };
    render(<RosterPlayerList title="Active players" description="Players" members={[editable, archived]} onManage={onManage} />);

    expect(screen.getByRole('button', { name: 'Manage' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'View status' })).not.toBeInTheDocument();
});
