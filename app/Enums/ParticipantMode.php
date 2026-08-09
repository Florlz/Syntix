<?php

namespace App\Enums;

enum ParticipantMode: string
{
    case Team = 'team';
    case Individual = 'individual';
    case Pair = 'pair';
    case Relay = 'relay';
    case Mixed = 'mixed';
}
