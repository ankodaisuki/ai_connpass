<?php

namespace Tests\Feature\Console;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOrphanCoverImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_unreferenced_old_files_but_keeps_referenced_and_recent(): void
    {
        Storage::fake('public');

        // 参照あり（残す）
        $referenced = UploadedFile::fake()->image('ref.jpg')->store('events/1', 'public');
        Event::factory()->create(['cover_image_path' => $referenced]);

        // 参照なし・古い（消す）: 最終更新を過去にする
        $orphan = UploadedFile::fake()->image('orphan.jpg')->store('events/2', 'public');
        touch(Storage::disk('public')->path($orphan), now()->subDays(2)->timestamp);

        // 参照なし・新しい（消さない）
        $recent = UploadedFile::fake()->image('recent.jpg')->store('events/3', 'public');

        $this->artisan('covers:prune-orphans', ['--hours' => 24])->assertSuccessful();

        Storage::disk('public')->assertExists($referenced);
        Storage::disk('public')->assertMissing($orphan);
        Storage::disk('public')->assertExists($recent);
    }

    public function test_dry_run_deletes_nothing(): void
    {
        Storage::fake('public');
        $orphan = UploadedFile::fake()->image('o.jpg')->store('events/9', 'public');
        touch(Storage::disk('public')->path($orphan), now()->subDays(2)->timestamp);

        $this->artisan('covers:prune-orphans', ['--dry-run' => true, '--hours' => 24])->assertSuccessful();

        Storage::disk('public')->assertExists($orphan);
    }
}
