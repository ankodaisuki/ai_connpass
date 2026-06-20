<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminEventDeletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【運営による削除】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.admin-event-deleted');
    }
}
