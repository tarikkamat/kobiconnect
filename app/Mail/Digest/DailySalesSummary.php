<?php

declare(strict_types=1);

namespace App\Mail\Digest;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Günlük satış özeti — dünkü sipariş, ciro ve kanal dağılımı.
 */
final class DailySalesSummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{count: int, total: string, average: string, change: string|null, channels: list<array{name: string, count: int, total: string}>, topSkus: list<array{sku: string, name: string, quantity: int}>, cancellations: int}  $data
     */
    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Günlük Satış Özeti — %s sipariş, %s', $this->data['count'], $this->data['total']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest.daily-sales-summary',
        );
    }
}
