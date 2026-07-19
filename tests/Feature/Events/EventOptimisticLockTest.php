<?php

namespace Tests\Feature\Events;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return array_merge([
            'title' => '後から更新',
            'description' => 'd',
            'category' => EventCategory::Backend->value,
            'prefecture' => '東京都',
            'location' => '会場',
            'event_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(3)->addHour()->format('Y-m-d\TH:i'),
            'capacity' => 10,
            'status' => EventStatus::Published->value,
        ], $overrides);
    }

    public function test_stale_update_is_rejected(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['title' => '元']);
        $stale = $event->updated_at->timestamp;

        // 別経路で更新して updated_at を進める
        sleep(1);
        $event->update(['title' => '先に更新']);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->formData([
            'expected_updated_at' => $stale,
        ]));

        $response->assertSessionHasErrors('expected_updated_at');
        $this->assertSame('先に更新', $event->fresh()->title); // 上書きされない
    }

    public function test_up_to_date_update_succeeds(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['title' => '元']);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->formData([
            'title' => '正しく更新',
            'expected_updated_at' => $event->updated_at->timestamp,
        ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame('正しく更新', $event->fresh()->title);
    }
}
