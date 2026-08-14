import React, { useState } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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
