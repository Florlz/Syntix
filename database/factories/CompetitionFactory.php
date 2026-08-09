<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Competition> */
class CompetitionFactory extends Factory
{
    protected $model = Competition::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'event_id' => EventFactory::new(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
