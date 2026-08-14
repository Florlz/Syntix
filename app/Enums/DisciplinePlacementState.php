<?php

namespace App\Enums;

enum DisciplinePlacementState: string
{
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Voided = 'voided';
}
