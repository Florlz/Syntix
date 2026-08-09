<?php

namespace Database\Factories;

use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Division> */
class DivisionFactory extends Factory
{
    protected $model = Division::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'competition_id' => CompetitionFactory::new(),
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
