<?php

namespace App\Mail;

use App\Models\ExternalRatingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalRatingInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ExternalRatingRequest $invitation,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' external rating invitation',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.external-rating.invitation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
