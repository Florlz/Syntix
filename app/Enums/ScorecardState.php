<?php

namespace App\Enums;

enum ScorecardState: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Submitted = 'submitted';
    case Rejected = 'rejected';
    case Approved = 'approved';
}
