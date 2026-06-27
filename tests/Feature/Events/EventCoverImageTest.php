<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
