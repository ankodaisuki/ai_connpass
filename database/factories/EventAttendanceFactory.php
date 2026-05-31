<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventAttendance>
 */
class EventAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => AttendanceStatus::Applied,
            'applied_at' => now(),
            'cancelled_at' => null,
        ];
    }

    /**
     * キャンセル済み状態の申し込みを生成
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * 出席記録済み状態の申し込みを生成
     */
    public function attended(): static
    {
        return $this->state(fn (array $attributes) => [
            'attended_at' => now(),
        ]);
    }

    /**
     * キャンセル待ち状態の申し込みを生成
     */
    public function waitlisted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceStatus::Waitlisted,
            'waitlisted_at' => now(),
            'applied_at' => null,
        ]);
    }
}
