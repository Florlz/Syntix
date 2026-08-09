<?php

namespace App\Enums;

enum ResultSubmissionState: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Submitted = 'submitted';
    case Rejected = 'rejected';
    case Approved = 'approved';
}
