<?php

namespace Database\Factories;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventOrganizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventOrganizer>
 */
class EventOrganizerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => OrganizerInvitationStatus::Pending,
            'invited_at' => now(),
            'responded_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizerInvitationStatus::Accepted,
            'responded_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OrganizerInvitationStatus::Declined,
            'responded_at' => now(),
        ]);
    }
}
