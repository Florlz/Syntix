<?php

namespace Database\Factories;

use App\Models\EventDelegation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventDelegation> */
class EventDelegationFactory extends Factory
{
    protected $model = EventDelegation::class;

    public function definition(): array
    {
        return [
            'event_id' => EventFactory::new(),
            'organizational_unit_id' => OrganizationalUnitFactory::new(),
            'name' => $this->faker->company(),
            'abbreviation' => $this->faker->lexify('???'),
            'color' => $this->faker->safeColorName(),
            'is_active' => true,
        ];
    }
}
