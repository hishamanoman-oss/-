# Architecture Decision Records (ADR)

**Document Status:** Phase 01 Architectural Blueprint (Approved Baseline)  

---

## ADR-001: Single Codebase Supporting Both SaaS and Self-Hosted Deployment
- **Status:** Accepted
- **Context:** The platform must serve both cloud SaaS customers (multi-tenant shared DB) and self-hosted customers requiring local/private hosting on their own MySQL/MariaDB server.
- **Decision:** Maintain one Laravel 13 codebase with mandatory `tenant_id` across all tenant tables and an environment-driven `APP_DEPLOYMENT_MODE` (`saas` vs. `self_hosted`) switching entitlement providers (Subscriptions vs. Signed Licenses) and cache/queue backends (Redis mandatory in SaaS prod; Database/Redis optional in Self-Hosted).
- **Rationale:** Eliminates schema drift, dual bug-fixing, and feature divergence between SaaS and Self-Hosted editions.
- **Trade-offs & Risks:** Self-Hosted single-tenant instances carry `tenant_id` columns on every table (trivial overhead) and require strict license signature verification to prevent tampering.

## ADR-002: Laravel 13 Modular Monolith + Blade/Livewire/Alpine.js + Reverb
- **Status:** Accepted
- **Context:** The team needs rapid full-stack delivery, native Arabic RTL + English LTR server rendering, real-time WebSocket updates, and clean domain boundaries without premature microservice operational complexity.
- **Decision:** Build a Laravel 13 Modular Monolith (`PHP 8.3–8.5`) with internal module namespaces, `Blade + Livewire + Alpine.js + Chart.js` frontend, `Laravel Reverb` for WebSockets, and `Laravel Queues`.
- **Rationale:** Unified type-safe PHP backend/frontend reactivity, first-party WebSocket support via Reverb, zero separate BFF/REST serialization boilerplate for standard UI, and clean path to extract modules later if needed.
- **Trade-offs & Risks:** Requires strict discipline (architecture tests/code review) so modules do not bypass boundaries with cross-module Eloquent queries.

## ADR-003: `tenant_memberships` Many-to-Many Model & Defense-in-Depth Isolation
- **Status:** Accepted
- **Context:** A direct `tenants -> users` foreign key breaks when a consultant, accountant, or multi-business owner belongs to multiple SaaS tenants, and blurs Platform Super Admin boundaries.
- **Decision:** Model `tenant_memberships` (many-to-many between `users` and `tenants`) carrying role + branch/warehouse scopes, keep `is_platform_admin` on `users` without requiring tenant membership, and enforce 5-layer isolation (Middleware `TenantContext`, Fail-Closed Scopes, Branch Scoping, Tenant-Aware Jobs/Cache, Scoped Reverb Channels).
- **Rationale:** Cleanly supports multi-tenant SaaS switching and prevents catastrophic cross-tenant data leaks in raw queries or background jobs.
- **Trade-offs & Risks:** Slightly more complex auth middleware and test fixtures; mitigated by automated cross-tenant penetration tests in Phase 09.

## ADR-004: .NET 10 LTS Outbound-Only Local Sync Agent (DPAPI + SQLite Outbox)
- **Status:** Accepted
- **Context:** Most target retail/accounting databases sit inside private Windows LANs behind NAT/firewalls. Opening inbound MySQL port `3306` is unacceptable security-wise.
- **Decision:** Build a standalone `.NET 10 LTS` Windows Worker Service that connects locally to customer MySQL/MariaDB, encrypts credentials via Windows DPAPI, buffers events in a local SQLite outbox, and pushes signed batches over outbound-only HTTPS.
- **Rationale:** Zero open inbound ports, native Windows service lifecycle resilience, strong cryptography/DPAPI support, and offline outage buffering.
- **Trade-offs & Risks:** Adds a second runtime ecosystem (C#/.NET 10 LTS alongside PHP/Laravel) requiring `.NET 10 SDK` on build/dev machines and Windows packaging in Phase 10.

## ADR-005: Connector Capability Contract, Canonical `field_mappings` & Mock POS DB First
- **Status:** Accepted
- **Context:** Real third-party POS/accounting databases use wildly inconsistent table and column names, and undocumented external APIs must not be fabricated.
- **Decision:** Define a declarative Connector Capability Contract + `field_mappings` engine in Laravel, and build a realistic **Mock Local POS MySQL Database** (`mock_pos_db`) in Phase 02 with intentionally non-matching table/column names for E2E testing before integrating real third-party ERPs.
- **Rationale:** Proves schema normalization, idempotency, CDC/polling, and command reconciliation end-to-end without blocking on third-party vendor access.
- **Trade-offs & Risks:** Complex multi-table transforms in future real ERPs may require extending `transform_rule` expressions in `field_mappings`.

## ADR-006: Command Outbox Architecture for Controlled Remote Writes (Zero Arbitrary SQL)
- **Status:** Accepted
- **Context:** Owners need remote operational control (price updates, stock adjustments, threshold changes), but exposing arbitrary remote SQL creates unacceptable security and data-corruption risk.
- **Decision:** Implement a `remote_commands` Command Outbox (`Pending -> Approved -> Dispatched -> Executing -> Succeeded / Failed / Expired / Rejected / Awaiting Reconciliation`) with risk-tiered approvals, cryptographic signatures, TTL expiry, idempotency keys, separate write credentials on the agent, allowlisted typed C# handlers, and post-execution sync reconciliation.
- **Rationale:** Provides enterprise-grade governance, four-eyes safety, and auditability for remote writes.
- **Trade-offs & Risks:** Asynchronous round-trip latency and potential `Awaiting Reconciliation` states require clear operator UI workflows.

## ADR-007: Immutable `stock_movements` Ledger & `on_hand` / `reserved` / `available` Separation
- **Status:** Accepted
- **Context:** A single `products.count` column cannot distinguish physical stock from committed reservations across multiple warehouses and leaves no audit trail.
- **Decision:** Track `inventory_balances` per `(tenant_id, warehouse_id, product_variant_id)` with strict `on_hand`, `reserved`, and `available = on_hand - reserved`, backed by an immutable append-only `stock_movements` ledger.
- **Rationale:** Guarantees multi-warehouse stock accuracy and full movement auditability from Day 1.
- **Trade-offs & Risks:** Higher write volume on high-throughput POS sync; mitigated by batch processing and composite indexing.

## ADR-008: Exact `DECIMAL` Arithmetic & Profit Completeness Guardrail
- **Status:** Accepted
- **Context:** Floating-point math causes penny rounding errors, and displaying Net Profit when COGS or expenses are missing misleads business owners into bad pricing/cash decisions.
- **Decision:** Mandate `DECIMAL(14,2)` / `DECIMAL(14,4)` / `DECIMAL(18,4)` across DB and PHP (`bcmath`/decimal value objects), separate all 9 financial metrics, and enforce `profit_completeness_status`—suppressing Net Profit whenever cost or expense inputs are incomplete.
- **Rationale:** Financial trustworthiness and audit readiness take precedence over always showing a guessed number.
- **Trade-offs & Risks:** Users accustomed to rough estimates may initially see `Unavailable` until they configure/sync COGS and expenses; mitigated by clear diagnostic guidance in UI.

## ADR-009: Honest Freshness States & Post-Commit Real-Time Broadcasts
- **Status:** Accepted
- **Context:** Broadcasting WebSocket events before DB commit causes race conditions where UI fetches stale data; hiding sync delays gives false confidence.
- **Decision:** Dispatch Reverb events strictly via `afterCommit` / `ShouldBroadcastAfterCommit`, and compute explicit per-source freshness states (`Live`, `Delayed`, `Syncing`, `Disconnected`, `Error`).
- **Rationale:** Ensures UI always reflects committed canonical data and communicates edge connectivity honestly.
- **Trade-offs & Risks:** Requires reliable queue/Reverb process monitoring in both dev and production.

## ADR-010: Legacy PHP Read-Only Preservation & Standalone No-XAMPP Local Runtime
- **Status:** Accepted
- **Context:** The repository contains a working legacy procedural PHP 8.2 store dashboard that currently serves as the baseline backup, previously run via XAMPP.
- **Decision:** Keep all legacy PHP, CSS, JS, SQL, and `.env` files strictly untouched as a read-only backup, and run/verify the new Laravel 13 platform exclusively on standalone local services (`php artisan serve`, MySQL 8.4 / MariaDB 11.4, Reverb, Queue, Vite, Playwright)—never XAMPP.
- **Rationale:** Zero regression risk to the legacy backup while ensuring the modern platform runs cleanly in standard CLI/service environments matching production.
- **Trade-offs & Risks:** Local developer machine must have standalone PHP 8.3–8.5, Composer, Node.js, MySQL/MariaDB, and .NET 10 LTS SDK installed and available on `PATH` before Phase 02/05B execution.
