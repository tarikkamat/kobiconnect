<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant oluşturulduğunda sahip kullanıcıya gönderilen hoş geldin e-postası.
 */
final class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $dashboardUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'KobiConnect\'a hoş geldiniz!',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lifecycle.welcome',
        );
    }
}
