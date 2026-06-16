<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReminder>
 */
class EventReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'sent_by_user_id' => User::factory(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'total_count' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
        ];
    }
}
