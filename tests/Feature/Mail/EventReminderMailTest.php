<?php

namespace Tests\Feature\Mail;

use App\Mail\EventReminderMail;
use App\Models\EventReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventReminderMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_renders_with_subject_and_body(): void
    {
        $reminder = EventReminder::factory()->create([
            'subject' => '明日の持ち物について',
            'body' => 'ノートPCをお持ちください。',
        ]);

        $mail = new EventReminderMail($reminder);
        $rendered = $mail->render();

        $this->assertStringContainsString('ノートPCをお持ちください。', $rendered);
        $this->assertSame('明日の持ち物について', $mail->envelope()->subject);
    }
}
