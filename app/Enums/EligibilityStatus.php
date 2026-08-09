<?php

namespace App\Enums;

enum EligibilityStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
    case Withdrawn = 'withdrawn';
    case Disqualified = 'disqualified';
}
