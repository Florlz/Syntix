<?php

namespace App\Models;

use Database\Factories\EntryScorecardFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

class JudgeScorecard extends EntryScorecard
{
    protected static function newFactory(): Factory
    {
        return EntryScorecardFactory::new();
    }
}
