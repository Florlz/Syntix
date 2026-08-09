<?php

namespace App\Enums;

enum ScoringAssignmentScope: string
{
    case CompetitionDivision = 'competition_division';
    case Contest = 'contest';
    case EntryScorecard = 'entry_scorecard';
}
