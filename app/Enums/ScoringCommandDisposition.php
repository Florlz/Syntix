<?php

namespace App\Enums;

enum ScoringCommandDisposition: string
{
    case Processing = 'processing';
    case Applied = 'applied';
    case Rejected = 'rejected';
    case Conflicted = 'conflicted';
}
