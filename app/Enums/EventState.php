<?php

namespace App\Enums;

enum EventState: string
{
    case Preparation = 'preparation';
    case Configuration = 'configuration';
    case Live = 'live';
    case Closed = 'closed';
    case Archived = 'archived';
}
