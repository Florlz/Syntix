<?php

namespace App\Enums;

enum PublicationState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';
}
