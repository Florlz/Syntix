<?php

namespace App\Enums;

enum ScoringFamily: string
{
    case Objective = 'objective';
    case CriteriaBased = 'criteria_based';
    case Aggregate = 'aggregate';
    case CustomSeries = 'custom_series';
}
