<?php

namespace Database\Factories;

use App\Models\GoogleCalendarToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarToken>
 */
class GoogleCalendarTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'expires_at' => now()->addHour(),
            'google_email' => fake()->safeEmail(),
        ];
    }
}
