<?php

namespace App\Enums;

enum AccountState: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
