---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Sync commands start central and walk tenants; the licence is the gate
`sync:pull` and `sync:drain` extend SyncCommand, which reads active licences on the central connection FIRST and only then enters each tenant schema via `tenancy()->runForMultiple()`. Never query tenant tables before that walk.

`$license->isActive()` is the gate, not `hasAccess()`: grace period is read only mode and a sync is a write, so an expired licence stops synchronisation without touching data (BACKEND-PLAN 3.2).

Cadence comes from `licenses.limits['sync.interval_minutes']` (default 15), never from the schedule - both commands are scheduled everyMinute and decide per tenant. `sync_cursors.updated_at` is the "when did we last look" stamp.

One tenant is wrapped in `rescue()`; a broken schema must not take the whole run down.
