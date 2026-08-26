<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Yeni kullanıcı ekibe eklendiğinde davet edilen kişiye gönderilir.
 */
final class TeamInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $roleName,
        public readonly string $tenantName,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('%s sizi KobiConnect ekibine davet etti', $this->tenantName),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lifecycle.team-invitation',
        );
    }
}
