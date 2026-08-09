<?php

namespace App\Models;

use Database\Factories\RosterMemberFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntryMember extends RosterMember
{
    protected static function newFactory(): Factory
    {
        return RosterMemberFactory::new();
    }
}
