<?php

namespace Database\Factories;

use App\Enums\ReminderRecipientStatus;
use App\Models\EventReminder;
use App\Models\EventReminderRecipient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReminderRecipient>
 */
class EventReminderRecipientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_reminder_id' => EventReminder::factory(),
            'user_id' => User::factory(),
            'email' => $this->faker->safeEmail(),
            'status' => ReminderRecipientStatus::Pending,
            'error' => null,
            'sent_at' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReminderRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReminderRecipientStatus::Failed,
            'error' => 'Connection timed out',
        ]);
    }
}
