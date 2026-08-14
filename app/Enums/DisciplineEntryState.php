<?php

namespace App\Enums;

enum DisciplineEntryState: string
{
    case Draft = 'draft';
    case Locked = 'locked';
}
