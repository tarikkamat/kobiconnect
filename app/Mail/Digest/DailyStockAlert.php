<?php

declare(strict_types=1);

namespace App\Mail\Digest;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Günlük stok uyarı özeti — güvenlik stoğunun altına inmiş varyantlar.
 */
final class DailyStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{count: int, items: list<array{sku: string, name: string, available: int, safetyStock: int, warehouse: string}>}  $data
     */
    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Stok Uyarısı — %d ürün kritik seviyede', $this->data['count']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest.daily-stock-alert',
        );
    }
}
