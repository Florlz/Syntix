<?php

namespace Database\Factories;

use App\Models\EntryScorecard;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EntryScorecard> */
class EntryScorecardFactory extends Factory
{
    protected $model = EntryScorecard::class;

    public function definition(): array
    {
        return [
            'contest_id' => ContestFactory::new(),
            'entry_reference' => $this->faker->optional()->bothify('entry-##??'),
        ];
    }
}
