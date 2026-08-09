<?php

namespace App\Enums;

enum DisciplineResultState: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Voided = 'voided';
}
