<?php

namespace App\Enums;

enum BracketSlotSource: string
{
    case Winner = 'winner';
    case Loser = 'loser';
    case ResetParticipant = 'reset_participant';
}
