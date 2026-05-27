<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistPromotedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【参加確定】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.waitlist-promoted');
    }
}
