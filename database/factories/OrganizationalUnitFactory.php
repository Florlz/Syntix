<?php

namespace Database\Factories;

use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrganizationalUnit> */
class OrganizationalUnitFactory extends Factory
{
    protected $model = OrganizationalUnit::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'abbreviation' => Str::upper($this->faker->lexify('???')),
            'slug' => Str::slug($name),
            'is_active' => true,
        ];
    }
}
