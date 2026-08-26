<?php

declare(strict_types=1);

namespace App\Mail\Digest;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Haftalık performans raporu — satış, iade, stok ve operasyon sağlığı özeti.
 */
final class WeeklyPerformanceReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{period: string, orders: array{count: int, total: string, average: string, change: string|null}, channels: list<array{name: string, count: int, total: string}>, topProducts: list<array{sku: string, name: string, quantity: int, total: string}>, claims: array{count: int, total: string}, criticalStock: int, failedSyncs: int, erroredConnections: int}  $data
     */
    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Haftalık Rapor — %s sipariş, %s', $this->data['orders']['count'], $this->data['orders']['total']),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest.weekly-performance-report',
        );
    }
}
