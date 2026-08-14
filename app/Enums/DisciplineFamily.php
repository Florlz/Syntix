<?php

namespace App\Enums;

enum DisciplineFamily: string
{
    case Track = 'track';
    case Field = 'field';
    case Relay = 'relay';
    case Combat = 'combat';
}
