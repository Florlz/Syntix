<?php

namespace App\Enums;

enum ParticipationExceptionType: string
{
    case Ineligible = 'ineligible';
    case Withdrawn = 'withdrawn';
    case Disqualified = 'disqualified';
}
