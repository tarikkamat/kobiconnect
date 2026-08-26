<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant'ın ilk pazaryeri bağlantısı kurulduğunda gönderilir.
 */
final class FirstConnectionEstablished extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $connectionName,
        public readonly string $marketplace,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('%s bağlantınız başarıyla kuruldu!', $this->connectionName),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lifecycle.first-connection',
        );
    }
}
