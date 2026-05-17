<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    /** ゲストはマイページにアクセスできない（login へリダイレクト） */
    public function test_index_requires_auth(): void
    {
        $this->get(route('my.attendances'))->assertRedirect(route('login'));
    }

    /** 認証済みユーザーはマイページに 200 でアクセスできる */
    public function test_index_returns_200_for_auth_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('my.attendances'))->assertStatus(200);
    }

    /** 自分の Applied 参加のみ表示（Cancelled・他人の参加は除外） */
    public function test_index_shows_only_own_applied_attendances(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();
        $other = User::factory()->create();

        $event1 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'my-applied-event']);
        $event2 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'my-cancelled-event']);
        $event3 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'others-event']);

        // 自分の Applied
        EventAttendance::factory()->for($event1)->for($user)->create();
        // 自分の Cancelled（除外される）
        EventAttendance::factory()->for($event2)->for($user)->cancelled()->create();
        // 他人の Applied（除外される）
        EventAttendance::factory()->for($event3)->for($other)->create();

        $response = $this->actingAs($user)->get(route('my.attendances'));

        $response->assertSee('my-applied-event');
        $response->assertDontSee('my-cancelled-event');
        $response->assertDontSee('others-event');
    }

    /** 15 件/ページ（16件 → 1ページ目に 15件・2ページ目に 1件） */
    public function test_index_paginates_with_15_per_page(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();

        for ($i = 1; $i <= 15; $i++) {
            $event = Event::factory()->for($organizer)->create([
                'status' => EventStatus::Published,
                'title' => "event-{$i}",
            ]);
            EventAttendance::factory()->for($event)->for($user)->create([
                'applied_at' => now()->addMinutes($i),
            ]);
        }

        $event16 = Event::factory()->for($organizer)->create([
            'status' => EventStatus::Published,
            'title' => 'event-page-2',
        ]);
        EventAttendance::factory()->for($event16)->for($user)->create([
            'applied_at' => now()->addMinutes(16),
        ]);

        $this->actingAs($user)->get(route('my.attendances'))->assertDontSee('event-page-2');
        $this->actingAs($user)->get(route('my.attendances', ['page' => 2]))->assertSee('event-page-2');
    }

    /** applied_at 昇順で表示（早い申込が先頭） */
    public function test_index_sorts_by_applied_at_ascending(): void
    {
        $user = User::factory()->create();
        $organizer = User::factory()->create();

        $event1 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'later-applied']);
        EventAttendance::factory()->for($event1)->for($user)->create(['applied_at' => now()->addHours(2)]);

        $event2 = Event::factory()->for($organizer)->create(['status' => EventStatus::Published, 'title' => 'sooner-applied']);
        EventAttendance::factory()->for($event2)->for($user)->create(['applied_at' => now()->addHours(1)]);

        $response = $this->actingAs($user)->get(route('my.attendances'));
        $content = $response->getContent();

        $this->assertLessThan(strpos($content, 'later-applied'), strpos($content, 'sooner-applied'));
    }
}
