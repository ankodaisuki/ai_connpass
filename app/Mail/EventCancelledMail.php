<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【イベント中止】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.event-cancelled');
    }
}
