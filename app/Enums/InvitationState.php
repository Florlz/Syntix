<?php

namespace App\Enums;

enum InvitationState: string
{
    case Pending = 'pending';
    case Consumed = 'consumed';
    case Expired = 'expired';
}
