<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventCoverImageCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_delete_keeps_image_but_force_delete_removes_it(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;

        $event->delete(); // ソフトデリート
        Storage::disk('public')->assertExists($path);

        $event->forceDelete(); // 物理削除
        Storage::disk('public')->assertMissing($path);
    }
}
