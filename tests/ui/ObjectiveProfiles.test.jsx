import React from 'react';
import { render, screen } from '@testing-library/react';
import { vi } from 'vitest';
import ObjectiveScoreProfile from '../../resources/js/Pages/Tabulator/Profiles/ObjectiveScoreProfile';

const base = { homeName: 'CCS', awayName: 'CAS', home: 0, away: 0, result: 'home_win', evidence: {}, onChange: vi.fn() };

test.each([
    ['team_total', 'Team total', 'Verification notes'],
    ['best_of_sets', 'Best of sets', 'CCS set scores'],
    ['team_tie', 'Team tie', 'Singles 1 winner'],
    ['chess', 'Chess', 'Board results'],
    ['combat_rounds', 'Combat', 'CCS round scores'],
    ['quiz_bowl', 'Quiz bowl', 'CCS quiz round scores'],
])('renders distinct %s operational evidence controls', (profile, eyebrow, evidenceLabel) => {
    render(<ObjectiveScoreProfile {...base} profile={profile}/>);

    expect(screen.getByText(eyebrow)).toBeInTheDocument();
    expect(screen.getByLabelText(`CCS ${profile === 'best_of_sets' ? 'Sets won' : profile === 'team_tie' ? 'Ties won' : profile === 'chess' ? 'Board points' : profile === 'combat_rounds' ? 'Rounds won' : 'Final points'}`)).toBeInTheDocument();
    expect(screen.getByLabelText(evidenceLabel)).toBeInTheDocument();
});

test('chess keeps an explicit draw-capable official result', () => {
    render(<ObjectiveScoreProfile {...base} profile="chess"/>);
    expect(screen.getByRole('combobox', { name: 'Official result' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Draw' })).toBeInTheDocument();
});
