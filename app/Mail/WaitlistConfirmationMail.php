<?php

namespace App\Mail;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Event $event,
        public readonly int $position,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "【キャンセル待ち登録完了】{$this->event->title}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.waitlist-confirmation');
    }
}
