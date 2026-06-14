<?php

namespace App\Mail;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizerInvitedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly User $inviter,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【合同主催のお願い】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.organizer-invited');
    }
}
