<?php

namespace App\Enums;

enum BracketNodeType: string
{
    case Contest = 'contest';
    case Bye = 'bye';
    case ThirdPlace = 'third_place';
    case ResetFinal = 'reset_final';
}
