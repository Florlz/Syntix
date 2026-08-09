<?php

namespace App\Enums;

enum OfficialOutcomeState: string
{
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Voided = 'voided';
}
