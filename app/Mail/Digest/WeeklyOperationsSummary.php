<?php

declare(strict_types=1);

namespace App\Mail\Digest;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Haftalık operasyon özeti — bağlantı sağlığı, senkron hataları ve reddedilen ürünler.
 */
final class WeeklyOperationsSummary extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{connections: list<array{name: string, marketplace: string, status: string}>, failedSyncs: int, rejectedProducts: int, webhookIssues: int}  $data
     */
    public function __construct(public readonly array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Haftalık Operasyon Özeti',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.digest.weekly-operations-summary',
        );
    }
}
