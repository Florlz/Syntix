<?php

namespace App\Enums;

enum BracketVersionState: string
{
    case Preview = 'preview';
    case Published = 'published';
    case Replaced = 'replaced';
}
