<?php

namespace App\Enums;

enum TournamentState: string
{
    case Draft = 'draft';
    case Preview = 'preview';
    case Published = 'published';
    case Uncontested = 'uncontested';
    case Archived = 'archived';
}
