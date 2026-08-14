<?php

namespace App\Enums;

/** Formats supported by the persisted tournament draw tables. */
enum TournamentFormat: string
{
    case SingleElimination = 'single_elimination';
    case DoubleElimination = 'double_elimination';
    case RoundRobin = 'round_robin';
}
