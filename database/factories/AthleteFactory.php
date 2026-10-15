<?php

namespace Database\Factories;

use App\Models\Athlete;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Athlete>
 */
class AthleteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'last_name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'kana' => 'キズナ タロウ',
            'gender' => 'M',
            'association' => '東京',
            'category' => '一般',
            'is_active' => true,
        ];
    }

    public function female(): static
    {
        return $this->state(fn () => ['gender' => 'F', 'kana' => 'キズナ ハナコ']);
    }
}
