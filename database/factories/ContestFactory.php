<?php

namespace Database\Factories;

use App\Enums\ContestState;
use App\Models\Contest;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contest> */
class ContestFactory extends Factory
{
    protected $model = Contest::class;

    public function definition(): array
    {
        return [
            'competition_division_id' => DivisionFactory::new(),
            'name' => 'Contest '.$this->faker->unique()->numerify('####'),
            'state' => ContestState::Scheduled->value,
        ];
    }
}
