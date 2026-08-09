<?php

namespace App\Enums;

enum CompetitionFormat: string
{
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';
    case RoundRobin = 'round_robin';
    case Series = 'series';
    case Placement = 'placement';
    case CriteriaBased = 'criteria_based';
    case Aggregate = 'aggregate';
    case Custom = 'custom';
}
