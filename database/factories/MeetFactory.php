<?php

namespace Database\Factories;

use App\Models\Meet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meet>
 */
class MeetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => '第1回 絆記録会',
            'held_on' => now()->addMonth()->toDateString(),
            'venue' => '〇〇陸上競技場',
            'entry_opens_at' => now()->subDay(),
            'entry_closes_at' => now()->addWeeks(2),
            'max_events_per_athlete' => 2,
            'is_published' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['entry_closes_at' => now()->subMinute()]);
    }
}
