<?php

namespace Tests\Feature\CoOrganizer;

use App\Enums\OrganizerInvitationStatus;
use App\Models\Event;
use App\Models\EventCoOrganizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerInvitationResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitee_can_accept(): void
    {
        $invitee = User::factory()->create();
        $event = Event::factory()->create();
        $invitation = EventCoOrganizer::factory()->create([
            'event_id' => $event->id,
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($invitee)
            ->patch(route('organizer-invitations.accept', $invitation))
            ->assertRedirect(route('my.organizer-invitations'));

        $invitation->refresh();
        $this->assertSame(OrganizerInvitationStatus::Accepted, $invitation->status);
        $this->assertNotNull($invitation->responded_at);
    }

    public function test_invitee_can_decline(): void
    {
        $invitee = User::factory()->create();
        $invitation = EventCoOrganizer::factory()->create([
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($invitee)
            ->patch(route('organizer-invitations.decline', $invitation))
            ->assertRedirect(route('my.organizer-invitations'));

        $this->assertSame(OrganizerInvitationStatus::Declined, $invitation->fresh()->status);
    }

    public function test_other_user_cannot_respond_to_invitation(): void
    {
        $invitee = User::factory()->create();
        $stranger = User::factory()->create();
        $invitation = EventCoOrganizer::factory()->create([
            'user_id' => $invitee->id,
            'status' => OrganizerInvitationStatus::Pending,
        ]);

        $this->actingAs($stranger)
            ->patch(route('organizer-invitations.accept', $invitation))
            ->assertForbidden();

        $this->assertSame(OrganizerInvitationStatus::Pending, $invitation->fresh()->status);
    }

    public function test_cannot_respond_to_already_resolved_invitation(): void
    {
        $invitee = User::factory()->create();
        $invitation = EventCoOrganizer::factory()->accepted()->create(['user_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->patch(route('organizer-invitations.decline', $invitation))
            ->assertForbidden();
    }
}
