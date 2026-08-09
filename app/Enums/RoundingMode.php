<?php

namespace App\Enums;

enum RoundingMode: string
{
    case HalfUp = 'half_up';
    case HalfDown = 'half_down';
    case HalfEven = 'half_even';
    case Floor = 'floor';
    case Ceiling = 'ceiling';
    case None = 'none';
}
