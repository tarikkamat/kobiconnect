---
paths:
  - 'app/Marketplaces/Trendyol/**'
---

# Trendyol

## Trendyol client rules: User-Agent, config-driven limits, no product writes yet
Every request needs `User-Agent: "{sellerId} - {integrator}"` or Trendyol answers 403 (TRENDYOL.md 2.3). TrendyolClient adds it; never bypass the client with a raw Http call.

Rate limits are two axis and live in config/marketplaces.php (`trendyol.rate_limits`), never in code: on 14 Sep 2026 the product limits move from per endpoint to per service group and updatePriceAndInventory goes from unlimited to 350-2000/min. Every endpoint the client calls must be listed under `rate_limits.endpoints` with its group, or acquire() throws. Trendyol publishes no `Retry-After` / `X-RateLimit-*` headers, so backoff is computed locally, never read from the response.

Credentials are per channel_connection, not per app: the container resolves TrendyolDriver/TrendyolClient without them, and callers bind them with `TrendyolDriver::for()` / `TrendyolClient::as()`.

Product create/update attribute payloads are NOT written yet: whether Trendyol wants `attributeValueId` + `customAttributeValue` or `attributeValueIds` + `attributeValue` is an open P0 contradiction between three official sources (TRENDYOL.md 9.6, Ek A #1). Verify it on stage against the getBatchRequestResult echo before writing a product serializer, and keep create and update serializers separate from day one.

Error envelopes come in three shapes (documented `errors[]`, 401 `exception`, order/429 `error`+`message`); TrendyolApiException parses all three. `errorCode` is only the HTTP status as a string - branch on `key`.

## Stock/price push: split path, send only what changed, numeric barcode keys
updatePriceAndInventory POSTs to `inventory/sellers/{id}/products/price-and-inventory` but its result is read from `product/sellers/{id}/products/batch-requests/{id}`. Two services, one flow - the most common path mistake.

pushStock sends barcode+quantity only, pushPrices sends barcode+salePrice+listPrice only. The three fields are independent; always sending all three maximises collisions with the 15 minute dedup window (K3).

A ProductInventoryUpdate batch returns NO top level `status`. Completion is read from the presence of `items[].status`; an item without one means the batch is still running, so `batchResult()` returns an empty item set (= pending) rather than a partial verdict that PollBatchResult would treat as final.

PushResult::itemResults is keyed by barcode, and PHP casts a numeric-string array key to int. Lookups still work (the cast is symmetric) but anything asserting on the returned keys must compare loosely.

Batch ceiling 1000 items, quantity ceiling 20.000 per product - both enforced in the driver.
