<?php

declare(strict_types=1);

namespace App\Mail\Lifecycle;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tenant'ın ilk siparişi geldiğinde tüm kullanıcılara gönderilir.
 */
final class FirstOrderReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{orderNumber: string, channel: string, total: string}  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly string $orderUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'İlk siparişiniz geldi! 🎉',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lifecycle.first-order',
        );
    }
}
