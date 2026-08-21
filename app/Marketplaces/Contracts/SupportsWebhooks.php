<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\OrderData;

interface SupportsWebhooks
{
    /**
     * Register a callback and return the remote subscription id.
     */
    public function registerWebhook(string $callbackUrl, MappingContext $context): string;

    /**
     * Registered subscriptions, keyed by remote subscription id. Used by the
     * health check that re-activates what the marketplace disabled.
     *
     * @return array<string, array{url: string, active: bool}>
     */
    public function listWebhooks(): array;

    public function activateWebhook(string $remoteWebhookId): void;

    public function deleteWebhook(string $remoteWebhookId): void;

    /**
     * Turn a delivered webhook payload into canonical orders. Webhooks only
     * carry order state; catalog drift is caught by reconciliation.
     *
     * @param  array<string, mixed>  $payload
     * @return list<OrderData>
     */
    public function parseWebhookOrders(array $payload): array;
}
