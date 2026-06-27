<?php

namespace Tests\Feature\Events;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCoverImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_image_path_is_fillable_and_nullable(): void
    {
        $event = Event::factory()->create(['cover_image_path' => null]);
        $this->assertNull($event->fresh()->cover_image_path);

        $event->update(['cover_image_path' => 'events/1/cover.jpg']);
        $this->assertSame('events/1/cover.jpg', $event->fresh()->cover_image_path);
    }

    /** @return array<string, mixed> */
    private function validEventData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テストイベント',
            'description' => '説明',
            'category' => EventCategory::Backend->value,
            'prefecture' => '東京都',
            'location' => 'テスト会場',
            'event_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(7)->addHours(2)->format('Y-m-d\TH:i'),
            'capacity' => 10,
            'status' => EventStatus::Published->value,
        ], $overrides);
    }

    public function test_non_image_file_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('cover_image');
        Storage::disk('public')->assertDirectoryEmpty('events');
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('big.jpg', 1000, 1000)->size(6144), // 6 MB
        ]));

        $response->assertSessionHasErrors('cover_image');
    }

    public function test_too_large_dimensions_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('huge.jpg', 5000, 5000),
        ]));

        $response->assertSessionHasErrors('cover_image');
    }

    public function test_event_can_be_created_with_cover_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]));

        $event = Event::latest('id')->first();
        $response->assertRedirect(route('events.show', $event));
        $this->assertNotNull($event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        $this->assertStringStartsWith("events/{$event->id}/", $event->cover_image_path);
    }

    public function test_event_can_be_created_without_cover_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), $this->validEventData());

        $this->assertNull(Event::latest('id')->first()->cover_image_path);
    }
}
