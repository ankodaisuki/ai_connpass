<?php

namespace Tests\Feature\Events;

use App\Enums\EventCategory;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\CoverImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverImageServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CoverImageService
    {
        return app(CoverImageService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(User $user, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'title' => 'テスト',
            'description' => '説明',
            'category' => EventCategory::Backend->value,
            'prefecture' => '東京都',
            'location' => '会場',
            'event_date' => now()->addDays(3),
            'end_date' => now()->addDays(3)->addHour(),
            'capacity' => 10,
            'status' => EventStatus::Draft->value,
        ], $overrides);
    }

    public function test_replacing_image_stores_new_deletes_old_and_updates_db(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $oldPath = $event->cover_image_path;

        $this->service()->updateCover($event, ['title' => '更新後'], UploadedFile::fake()->image('new.jpg'), false);

        $event->refresh();
        $this->assertNotSame($oldPath, $event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertSame('更新後', $event->title);
    }

    public function test_removing_image_nulls_db_and_deletes_file(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('c.jpg')->store('events/1', 'public'),
        ]);
        $old = $event->cover_image_path;

        $this->service()->updateCover($event, ['title' => 'x'], null, true);

        $event->refresh();
        $this->assertNull($event->cover_image_path);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_old_image_survives_when_new_upload_fails(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create([
            'cover_image_path' => UploadedFile::fake()->image('old.jpg')->store('events/1', 'public'),
        ]);
        $old = $event->cover_image_path;

        // アップロード先ディスクを putFileAs で例外を投げるモックに差し替える
        $throwing = \Mockery::mock(Storage::disk('public'))->makePartial();
        $throwing->shouldReceive('putFileAs')->andThrow(new \RuntimeException('upload failed'));
        Storage::shouldReceive('disk')->andReturn($throwing);

        try {
            $this->service()->updateCover($event, ['title' => 'x'], UploadedFile::fake()->image('new.jpg'), false);
            $this->fail('例外が送出されるはず');
        } catch (\Throwable $e) {
            // 期待通り
        }

        $event->refresh();
        $this->assertSame($old, $event->cover_image_path); // DBは旧のまま
    }

    public function test_create_with_cover_persists_event_and_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $event = $this->service()->createWithCover(
            $this->validData($user),
            UploadedFile::fake()->image('c.jpg'),
        );

        $this->assertNotNull($event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'cover_image_path' => $event->cover_image_path]);
    }

    public function test_create_without_image_persists_event(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $event = $this->service()->createWithCover($this->validData($user), null);

        $this->assertNull($event->cover_image_path);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
    }

    // #5: 画像なしのイベントに、編集で画像を新規追加する（N→I）
    public function test_adding_image_to_event_without_one(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create(['cover_image_path' => null]);

        $this->service()->updateCover($event, ['title' => '画像追加'], UploadedFile::fake()->image('add.jpg'), false);

        $event->refresh();
        $this->assertNotNull($event->cover_image_path);
        Storage::disk('public')->assertExists($event->cover_image_path);
        $this->assertSame('画像追加', $event->title);
    }

    // #3: 作成時にアップロードが失敗したら、イベント行もファイルも残さない（部分成功・孤児を作らない）
    public function test_create_failure_persists_no_event_and_no_orphan(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $throwing = \Mockery::mock(Storage::disk('public'))->makePartial();
        $throwing->shouldReceive('putFileAs')->andThrow(new \RuntimeException('upload failed'));
        Storage::shouldReceive('disk')->andReturn($throwing);

        $countBefore = Event::count();

        try {
            $this->service()->createWithCover($this->validData($user), UploadedFile::fake()->image('c.jpg'));
            $this->fail('例外が送出されるはず');
        } catch (\Throwable $e) {
            // 期待通り
        }

        $this->assertSame($countBefore, Event::count()); // イベント行はロールバックされ残らない
    }
}
