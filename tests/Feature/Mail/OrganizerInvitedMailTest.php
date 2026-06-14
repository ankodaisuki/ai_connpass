<?php

namespace Tests\Feature\Mail;

use App\Mail\OrganizerInvitedMail;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerInvitedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_event_title_and_inviter(): void
    {
        $inviter = User::factory()->create(['name' => '招待 太郎']);
        $event = Event::factory()->create(['title' => 'Laravel 勉強会 #5', 'user_id' => $inviter->id]);

        $mail = new OrganizerInvitedMail($event, $inviter);
        $rendered = $mail->render();

        $this->assertStringContainsString('Laravel 勉強会 #5', $rendered);
        $this->assertStringContainsString('招待 太郎', $rendered);
        $this->assertStringContainsString(route('my.organizer-invitations'), $rendered);
        $this->assertStringContainsString('合同主催', $mail->envelope()->subject);
    }
}
