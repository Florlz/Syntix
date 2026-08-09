<?php

namespace App\Enums;

enum OutcomeType: string
{
    case Played = 'played';
    case Walkover = 'walkover';
    case Forfeit = 'forfeit';
    case NoShow = 'no_show';
    case Withdrawal = 'withdrawal';
    case Disqualification = 'disqualification';
    case Ruled = 'ruled';
}
