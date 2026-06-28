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

    public function test_updating_cover_image_replaces_and_deletes_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $oldPath = $event->cover_image_path;
        Storage::disk('public')->assertExists($oldPath);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('new.jpg', 800, 600),
        ]));

        $response->assertRedirect(route('events.show', $event));
        $event->refresh();
        $this->assertNotSame($oldPath, $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_updating_without_new_image_keeps_existing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('keep.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;

        $this->actingAs($user)->put(route('events.update', $event), $this->validEventData());

        $this->assertSame($path, $event->fresh()->cover_image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_non_organizer_cannot_upload_cover_image(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->for($owner)->create();

        $response = $this->actingAs($stranger)->put(route('events.update', $event), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('x.jpg', 800, 600),
        ]));

        $response->assertForbidden();
        $this->assertNull($event->fresh()->cover_image_path);
    }

    public function test_create_form_has_cover_image_input(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('events.create'));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="cover_image"', false);
    }

    public function test_edit_form_has_cover_image_input(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create();
        $response = $this->actingAs($user)->get(route('events.edit', $event));

        $response->assertOk();
        $response->assertSee('enctype="multipart/form-data"', false);
        $response->assertSee('name="cover_image"', false);
    }

    public function test_show_page_displays_cover_image_when_present(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
            'status' => EventStatus::Published->value,
        ]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();
        $response->assertSee($event->cover_image_path, false);
    }

    public function test_show_page_displays_placeholder_when_absent(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => null,
            'status' => EventStatus::Published->value,
        ]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();
        $response->assertSee('event-placeholder.svg', false);
    }

    public function test_cover_image_can_be_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;
        Storage::disk('public')->assertExists($path);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'remove_cover_image' => '1',
        ]));

        $response->assertRedirect(route('events.show', $event));
        $this->assertNull($event->fresh()->cover_image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cover_image_is_kept_when_not_removing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('keep.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;

        $this->actingAs($user)->put(route('events.update', $event), $this->validEventData());

        $this->assertSame($path, $event->fresh()->cover_image_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_new_upload_takes_precedence_over_remove(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $oldPath = $event->cover_image_path;

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('new.jpg', 800, 600),
            'remove_cover_image' => '1',
        ]));

        $response->assertRedirect(route('events.show', $event));
        $event->refresh();
        $this->assertNotNull($event->cover_image_path);
        $this->assertNotSame($oldPath, $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_removing_when_no_image_is_safe(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['cover_image_path' => null]);

        $response = $this->actingAs($user)->put(route('events.update', $event), $this->validEventData([
            'remove_cover_image' => '1',
        ]));

        $response->assertRedirect(route('events.show', $event));
        $this->assertNull($event->fresh()->cover_image_path);
    }

    public function test_edit_form_shows_remove_checkbox_when_image_exists(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $response = $this->actingAs($user)->get(route('events.edit', $event));
        $response->assertOk();
        $response->assertSee('name="remove_cover_image"', false);
    }

    public function test_edit_form_hides_remove_checkbox_when_no_image(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create(['cover_image_path' => null]);

        $response = $this->actingAs($user)->get(route('events.edit', $event));
        $response->assertOk();
        $response->assertDontSee('name="remove_cover_image"', false);
    }

    public function test_cover_image_url_returns_disk_url_when_present(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = Event::factory()->for($user)->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->assertSame(
            Storage::disk('public')->url($event->cover_image_path),
            $event->cover_image_url,
        );
    }

    public function test_cover_image_url_returns_placeholder_when_absent(): void
    {
        $event = Event::factory()->create(['cover_image_path' => null]);

        $this->assertStringContainsString('event-placeholder.svg', $event->cover_image_url);
    }

    public function test_cover_image_is_stored_on_configured_disk(): void
    {
        config(['filesystems.cover_disk' => 'r2test']);
        Storage::fake('r2test');
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('events.store'), $this->validEventData([
            'cover_image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ]));

        $event = Event::latest('id')->first();
        $this->assertNotNull($event->cover_image_path);
        Storage::disk('r2test')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertDirectoryEmpty('events');
    }
}
