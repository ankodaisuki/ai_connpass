<?php

namespace Database\Factories;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
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
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(EventCategory::cases()),
            'prefecture' => fake()->randomElement(['東京都', '大阪府', '京都府', '福岡県']),
            'location' => fake()->address(),
            'event_date' => fake()->dateTimeBetween('+1 day', '+3 months'),
            'capacity' => fake()->numberBetween(10, 100),
            'status' => EventStatus::Published,
        ];
    }

    /**
     * 下書き状態のイベントを生成
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Draft,
        ]);
    }

    /**
     * 非公開状態のイベントを生成
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Private,
        ]);
    }
}
