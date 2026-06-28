<?php

namespace Tests\Feature\Console;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MigrateCoverImagesToR2Test extends TestCase
{
    use RefreshDatabase;

    public function test_copies_existing_images_from_public_to_s3(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->artisan('covers:migrate-to-r2')->assertSuccessful();

        Storage::disk('s3')->assertExists($event->cover_image_path);
    }

    public function test_is_idempotent_and_skips_existing(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->artisan('covers:migrate-to-r2')->assertSuccessful();
        $this->artisan('covers:migrate-to-r2')
            ->expectsOutputToContain('スキップ: 1')
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_copy(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);

        $this->artisan('covers:migrate-to-r2', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('s3')->assertMissing($event->cover_image_path);
    }

    public function test_ignores_events_without_cover_image(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        Event::factory()->create(['cover_image_path' => null]);

        $this->artisan('covers:migrate-to-r2')
            ->expectsOutputToContain('コピー: 0')
            ->assertSuccessful();
    }

    public function test_copies_images_of_soft_deleted_events(): void
    {
        Storage::fake('public');
        Storage::fake('s3');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $path = $event->cover_image_path;
        $event->delete();

        $this->artisan('covers:migrate-to-r2')->assertSuccessful();

        Storage::disk('s3')->assertExists($path);
    }
}
