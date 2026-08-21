<?php

namespace App\Marketplaces\Contracts;

use App\Marketplaces\Support\Capability;

/**
 * A marketplace adapter. Everything a driver can do beyond identifying itself
 * is declared by implementing the Supports* contracts in this namespace.
 */
interface MarketplaceDriver
{
    /**
     * The key this driver is registered under in config/marketplaces.php.
     */
    public function identifier(): string;

    /**
     * The name shown to the user.
     */
    public function displayName(): string;

    /**
     * Implemented as Capability::supportedBy($this) so it cannot drift.
     *
     * @return list<Capability>
     */
    public function capabilities(): array;

    /**
     * The credential form of this marketplace, declared once and consumed by
     * everything that needs it: the connection form renders it, ConnectionRequest
     * validates against it and ConnectionController persists by it. Marketplaces
     * disagree on every single field - Trendyol wants a numeric sellerId plus a
     * key pair, Hepsiburada a merchantId UUID plus one service key - so a
     * hardcoded form fits exactly one of them.
     *
     * `rules` are Laravel string rules and stay on the server; `identity` marks
     * the field mirrored into channel_connections.external_seller_id.
     *
     * @return list<array{
     *     name: string,
     *     label: string,
     *     type: 'text'|'secret'|'select'|'checkbox',
     *     rules: list<string>,
     *     help?: string,
     *     options?: list<string>,
     *     default?: string,
     *     identity?: bool,
     * }>
     */
    public function credentialFields(): array;
}
