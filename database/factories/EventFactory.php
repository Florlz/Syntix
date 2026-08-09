<?php

namespace Database\Factories;

use App\Enums\EventState;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $name = 'SIKLAB '.$this->faker->unique()->year();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'state' => EventState::Preparation->value,
        ];
    }
}
