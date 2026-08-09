<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The database calls this relationship a competition_division while the
 * domain calls the score-bearing unit a Division.
 */
class CompetitionDivision extends Division
{
    protected static function newFactory(): Factory
    {
        return DivisionFactory::new();
    }
}
