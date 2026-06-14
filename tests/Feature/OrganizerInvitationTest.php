<?php

namespace Tests\Feature;

use App\Enums\OrganizerInvitationStatus;
use App\Mail\OrganizerInvitedMail;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrganizerInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_existing_user_by_email(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitee@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($owner)->post(route('events.organizers.store', $event), [
            'email' => 'invitee@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('event_co_organizers', [
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending->value,
        ]);
        Mail::assertSent(OrganizerInvitedMail::class);
    }

    public function test_non_owner_cannot_invite(): void
    {
        $owner = User::factory()->create();
        $coOrganizer = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $coOrganizer->id]);

        $this->actingAs($coOrganizer)
            ->post(route('events.organizers.store', $event), ['email' => 'x@example.com'])
            ->assertForbidden();
    }

    public function test_inviting_unknown_email_returns_validation_error(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_co_organizers', 0);
    }

    public function test_cannot_invite_the_owner_themselves(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'owner@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_co_organizers', 0);
    }

    public function test_cannot_invite_pending_user_again(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'dup@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->create(['event_id' => $event->id, 'user_id' => $invitee->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'dup@example.com'])
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('event_co_organizers', 1);
    }

    public function test_cannot_invite_accepted_co_organizer(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'accepted@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->accepted()->create(['event_id' => $event->id, 'user_id' => $invitee->id]);

        $this->actingAs($owner)
            ->from(route('events.show', $event))
            ->post(route('events.organizers.store', $event), ['email' => 'accepted@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_declined_user_can_be_reinvited(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $invitee = User::factory()->create(['email' => 'declined@example.com']);
        $event = Event::factory()->create(['user_id' => $owner->id]);
        EventCoOrganizer::factory()->declined()->create(['event_id' => $event->id, 'user_id' => $invitee->id]);

        $this->actingAs($owner)
            ->post(route('events.organizers.store', $event), ['email' => 'declined@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('event_co_organizers', [
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending->value,
        ]);
        $this->assertDatabaseCount('event_co_organizers', 1);
        Mail::assertSent(OrganizerInvitedMail::class);
    }
}
