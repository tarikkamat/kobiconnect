---
paths:
  - 'database/migrations/tenant/**'
---

# Tenant

## Schema-qualify public objects in tenant migrations
PostgreSQLSchemaManager sets search_path to the tenant schema only — `public` is NOT on the path. Anything installed in public must be schema-qualified inside tenant migrations: `public.unaccent(...)`, `public.gin_trgm_ops`.

`unaccent()` is STABLE, so PG rejects it in a generated column. 2026_01_01_000000_create_catalog_tables creates a per-tenant IMMUTABLE wrapper `f_unaccent(text)` and products.search_vector is generated from it. Any query matching that vector must pass its term through `f_unaccent()` too, otherwise "şarj" never matches the indexed "sarj" — see Product::search().

Not expressible through the Blueprint, use DB::statement(): operator classes (gin_trgm_ops) and partial indexes (channel_ops_pending_uniq, channel_ops_drain).

PG 17: use `storedAs()`, never `virtualAs()` (PG 18+).
