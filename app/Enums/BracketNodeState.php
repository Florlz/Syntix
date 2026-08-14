<?php

namespace App\Enums;

enum BracketNodeState: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
    case ByeResolved = 'bye_resolved';
    case Skipped = 'skipped';
}
