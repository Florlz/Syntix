<?php

namespace App\Enums;

enum LedgerEntryType: string
{
    case Award = 'award';
    case Reversal = 'reversal';
    case Replacement = 'replacement';
}
