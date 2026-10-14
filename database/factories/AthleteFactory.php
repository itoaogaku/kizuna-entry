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
            'last_kana' => 'キズナ',
            'first_kana' => 'タロウ',
            'gender' => 'M',
            'birthdate' => fake()->dateTimeBetween('-40 years', '-13 years')->format('Y-m-d'),
            'category' => '一般',
            'is_active' => true,
        ];
    }

    public function female(): static
    {
        return $this->state(fn () => ['gender' => 'F', 'first_kana' => 'ハナコ']);
    }
}
