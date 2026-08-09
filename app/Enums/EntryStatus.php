<?php

namespace App\Enums;

enum EntryStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Locked = 'locked';
    case Withdrawn = 'withdrawn';
    case Disqualified = 'disqualified';
}
