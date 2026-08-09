<?php

namespace App\Enums;

enum ContestState: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Suspended = 'suspended';
}
