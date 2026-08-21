<?php

namespace App\Marketplaces\Data;

/**
 * The outcome of an outbound mutation.
 *
 * Marketplace mutations are asynchronous: an accepted request only means the
 * batch was queued remotely, never that the items succeeded. The operation
 * stays open until the item level results have been read back and merged in
 * with withItemResults().
 */
final readonly class PushResult
{
    /**
     * @param  array<string, array{accepted: bool, degraded?: bool, code: string|null, message: string|null}>  $itemResults  keyed by the pushed item reference
     */
    public function __construct(
        public bool $accepted,
        public ?string $remoteBatchId = null,
        public array $itemResults = [],
        public ?string $failureReason = null,
    ) {}

    public static function accepted(?string $remoteBatchId = null): self
    {
        return new self(accepted: true, remoteBatchId: $remoteBatchId);
    }

    public static function rejected(string $failureReason): self
    {
        return new self(accepted: false, failureReason: $failureReason);
    }

    /**
     * @param  array<string, array{accepted: bool, degraded?: bool, code: string|null, message: string|null}>  $itemResults
     */
    public function withItemResults(array $itemResults): self
    {
        return new self($this->accepted, $this->remoteBatchId, $itemResults, $this->failureReason);
    }

    /**
     * Whether the item level results are still outstanding.
     */
    public function isPending(): bool
    {
        return $this->accepted && $this->itemResults === [];
    }

    /**
     * @return list<string>
     */
    public function failedReferences(): array
    {
        $failed = [];

        foreach ($this->itemResults as $reference => $result) {
            if (! $result['accepted']) {
                $failed[] = $reference;
            }
        }

        return $failed;
    }

    /**
     * Pazaryeri KABUL etti ama urunu etkisiz hale getirdi.
     *
     * Olculmus vaka (HEPSIBURADA.md §3): fiyat bandi ihlalinde urun
     * reddedilmez, 0 fiyat / 0 stok ile CANLIYA cikar ve geriye yalnizca bir
     * uyari dizesi kalir. `accepted: false` demek retry politikasini bozardi
     * (yeniden gondermek ayni sonucu verir); `accepted: true` demek saticiya
     * yalan soylemek olurdu.
     *
     * `degraded` opsiyoneldir — varsayilani false, dolayisiyla mevcut
     * surucular degistirilmeden calismaya devam eder.
     *
     * @return list<string>
     */
    public function degradedReferences(): array
    {
        $references = [];

        foreach ($this->itemResults as $reference => $result) {
            if (($result['degraded'] ?? false) === true) {
                $references[] = (string) $reference;
            }
        }

        return $references;
    }
}
