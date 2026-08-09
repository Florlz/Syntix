<?php

namespace App\Enums;

enum ScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
