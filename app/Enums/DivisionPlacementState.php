<?php

namespace App\Enums;

enum DivisionPlacementState: string
{
    case Candidate = 'candidate';
    case Submitted = 'submitted';
    case Rejected = 'rejected';
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Voided = 'voided';
}
