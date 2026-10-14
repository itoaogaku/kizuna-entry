<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Meet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meet_id' => Meet::factory(),
            'name' => '一般男子5000m',
            'gender' => 'M',
            'fee' => 1500,
            'capacity' => null,
            'sort_order' => 0,
        ];
    }
}
