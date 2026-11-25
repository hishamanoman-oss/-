# Product Requirements Document (PRD)

**Document Status:** Phase 01 Architectural Blueprint (Approved Baseline)  
**Target Stack:** Laravel 13 (PHP 8.3–8.5), MariaDB 11.4 LTS / MySQL 8.4 LTS, Blade + Livewire + Alpine.js + Chart.js, Laravel Reverb, Redis (SaaS), .NET 10 LTS Local Sync Agent  

---

## 1. Scope & Guiding Principles

1. **Single Codebase, Dual Deployment:** All features operate identically across **SaaS** (multi-tenant shared DB + subscriptions) and **Self-Hosted** (single-tenant default + signed license).
2. **Multi-Tenant & Multi-Branch from Day 1:** Every business entity belongs to a `tenant_id`; operational records scope cleanly to `branch_id` and `warehouse_id`.
3. **Exact Arithmetic:** All currency values, tax, discounts, COGS, and stock quantities use exact `DECIMAL` types—never floating-point (`float`/`double`).
4. **Real-Time vs. Eventually Consistent Clarity:**
   - **Eventually Consistent:** Local POS commits -> Sync Agent capture (CDC/Binlog or incremental polling) -> HTTPS ingestion -> Canonical persistence.
   - **Real-Time:** Post-DB-commit Laravel events broadcast over **Laravel Reverb** to update connected browser UIs instantaneously once canonical data is committed.
5. **Legacy Preservation:** Existing procedural PHP files in the repository are strictly read-only backups and must not be refactored or broken.

---

## 2. Functional Requirements (12 Core Modules)

### Module 1: Platform Core, Tenancy & Licensing
- **Purpose:** Manage tenant lifecycle, deployment mode (`saas` vs. `self_hosted`), commercial entitlements, and multi-tenant memberships.
- **Requirements:**
  - `tenants` registry with `deployment_mode`, `status`, `timezone`, `default_currency`, `default_locale` (`ar`/`en`).
  - `tenant_memberships` linking `users` <-> `tenants` (many-to-many) so a single user in SaaS can belong to multiple tenants with distinct roles, while Platform Super Admins remain cleanly separated from tenant ownership.
  - **SaaS Mode:** Plan catalog, subscription status (`trialing`, `active`, `past_due`, `suspended`), quota enforcement (max branches, warehouses, data sources, seats, command volume).
  - **Self-Hosted Mode:** Signed license verification (Ed25519/RSA payload) enforcing customer name, expiration, module entitlements, and max branches/sources without requiring external SaaS connectivity once activated.
- **Acceptance Criteria:**
  - A user with memberships in Tenant A and Tenant B can switch active tenants cleanly without cross-tenant session or cache leakage.
  - Expired or over-quota tenants fail gracefully on restricted write/onboarding actions with clear actionable errors.

### Module 2: Identity, RBAC & Security Audit
- **Purpose:** Enforce least-privilege authentication, branch/warehouse scoping, and tamper-evident audit logging.
- **Requirements:**
  - 7 canonical roles: `Platform Super Admin`, `Tenant Owner`, `General Manager`, `Branch Manager`, `Accountant`, `Inventory Manager`, `Observer`.
  - Per-membership optional `branch_scope` and `warehouse_scope` arrays/pivot assignments.
  - Password hashing (`bcrypt`/`argon2id`), optional 2FA/TOTP, active session management, CSRF protection, strict rate limiting.
  - Immutable `audit_logs` recording actor, role, tenant, IP/user-agent, action, entity, before/after state, and timestamp.
- **Acceptance Criteria:**
  - Every controller, Livewire action, API endpoint, and Reverb channel enforces both role permission and branch/warehouse scope.
  - Unauthorized cross-tenant or cross-branch access returns HTTP `403` and logs a security audit event.

### Module 3: Integration Center & Connector Hub
- **Purpose:** Configure external/local data sources, credential separation, capture modes, and canonical field mappings.
- **Requirements:**
  - `data_sources` registry supporting connector types (starting with `mysql_local_agent` targeting Mock Local POS MySQL DB, extensible to MariaDB, REST/Webhook ERPs, etc.).
  - Connector **Capability Contract** declaring: supported capture strategies (`webhook`, `cdc_binlog`, `incremental_polling`), write support, supported command types, and health metrics.
  - `field_mappings` UI & schema allowing tenant admins/integrators to map non-standard source tables and columns (e.g., `pos_tx_hdr.hdr_total` -> `orders.gross_amount`) with type transforms and defaults without code changes.
  - Strict separation of **Read-Only Sync Credentials** vs. **Controlled-Write Command Credentials** (write disabled by default until explicitly enabled by Tenant Owner).
- **Acceptance Criteria:**
  - Mapping edits validate required canonical fields before activation.
  - Mock Local POS DB with intentionally mismatched table/column names ingests cleanly solely via `field_mappings`.

### Module 4: Sync Ingestion, Normalization & Freshness Engine
- **Purpose:** Safely receive, deduplicate, normalize, persist, and monitor incoming data from Local Sync Agents and webhooks.
- **Requirements:**
  - Authenticated ingestion API (`POST /api/v1/ingest/batches`, `POST /api/v1/ingest/heartbeat`, `POST /api/v1/webhooks/{source}`).
  - Mandatory `idempotency_keys` enforcement to prevent duplicate order/movement ingestion on retries.
  - `webhook_events` raw payload staging table with signature verification, processing status, and retry/dead-letter tracking.
  - `sync_runs` tracking batch counts, latency, watermark progression, validation warnings, and errors.
  - Honest **Freshness State Machine** per data source and branch:
    - `Live` (heartbeat & sync lag within configured threshold, e.g., <= 60s)
    - `Syncing` (active batch/catch-up in progress)
    - `Delayed` (heartbeat alive, sync lag exceeds threshold)
    - `Disconnected` (missed consecutive heartbeats)
    - `Error` (repeated ingestion/schema/DB failure requiring attention)
- **Acceptance Criteria:**
  - Re-sending an identical batch with the same `idempotency_key` produces zero duplicate rows and returns the original acknowledgement.
  - Stopping the Local Sync Agent transitions the UI freshness badge from `Live` to `Disconnected` within the configured heartbeat timeout.

### Module 5: Executive BI & Real-Time Operations Dashboard
- **Purpose:** Deliver immediate situational awareness across branches, sales, stock health, and sync freshness.
- **Requirements:**
  - Live KPI cards: Gross Sales, Net Sales, Orders Count, Average Order Value, Outstanding Receivables, Low/Out-of-Stock Alerts.
  - Real-time updates via **Laravel Reverb** triggered exclusively *after* database transaction commit (`afterCommit`), updating Livewire/Alpine widgets without full page reload.
  - Filterable by Tenant Branch(es), Warehouse(s), Date Range, and Currency display.
  - Prominent Freshness & Last-Synced Banner showing exact lag and source status.
- **Acceptance Criteria:**
  - Creating a sale in the Mock Local POS DB propagates through the agent and updates KPIs in an open browser dashboard via Reverb without manual refresh.

### Module 6: Multi-Branch & Multi-Warehouse Inventory Ledger
- **Purpose:** Track physical, reserved, and available stock accurately across warehouses with full movement traceability.
- **Requirements:**
  - `branches` and `warehouses` entities (1 branch may link to 1+ warehouses; central warehouses may serve multiple branches).
  - `products` and `product_variants` (SKU, barcode, category, unit, cost price, selling price, reorder threshold).
  - `inventory_balances` per `(tenant_id, warehouse_id, product_variant_id)` enforcing:
    - `on_hand` (`DECIMAL(18,4)`)
    - `reserved` (`DECIMAL(18,4)`)
    - `available` (`DECIMAL(18,4)`, invariant: `available = on_hand - reserved`)
  - Immutable append-only `stock_movements` ledger (`sale`, `return`, `purchase_receipt`, `transfer_in`, `transfer_out`, `adjustment`, `reservation`, `release`) with `balance_after` and source reference.
  - Low-stock and stockout alert triggers per warehouse threshold.
- **Acceptance Criteria:**
  - No direct overwrite of `inventory_balances` occurs without a corresponding `stock_movements` record (or reconciliation adjustment entry).
  - Branch Managers only view warehouses linked to their authorized branches.

### Module 7: Sales, Orders, Invoices & Returns
- **Purpose:** Provide complete visibility into orders, line items, payments, returns/refunds, and printable Arabic/English invoices.
- **Requirements:**
  - `orders` with strict financial field separation: `gross_amount`, `discount_amount`, `refund_amount`, `net_amount`, `tax_amount`, `paid_amount`, `outstanding_amount`, `cogs_amount`.
  - `order_items` capturing SKU, quantity (`DECIMAL`), unit price, line discount, tax, and unit cost snapshot.
  - `payments` and `order_returns` linked to orders with status tracking (`paid`, `partial`, `unpaid`, `refunded`, `voided`).
  - Server-side search, filtering (branch, status, payment state, date range, VIP), and pagination.
  - Official printable Arabic (`RTL`) & English (`LTR`) invoice view with tenant branding.
- **Acceptance Criteria:**
  - `net_amount` consistently equals `gross_amount - discount_amount - refund_amount` (tax tracked explicitly per tax-inclusive/exclusive flag) using exact `DECIMAL` math.

### Module 8: Financial Analytics, COGS & Profit Guardrails
- **Purpose:** Provide trustworthy financial performance summaries without misleading profit approximations.
- **Requirements:**
  - Aggregated `financial_snapshots` (daily/monthly per tenant & branch) storing all 9 financial components separately: Gross Sales, Discounts, Refunds, Net Sales, Tax, Paid, Outstanding, COGS, and Net Profit.
  - **Completeness Guardrail (`profit_completeness_status`):**
    - `complete`: All sold items in period have valid COGS and expense inputs are confirmed.
    - `partial_cogs`: One or more sold items lack cost price (`unit_cost IS NULL`).
    - `missing_expenses`: Operating expenses for the period are not recorded/synced.
    - `incomplete`: Both COGS and expenses are incomplete.
  - **Strict Display Rule:** Whenever `profit_completeness_status != 'complete'`, the platform MUST NOT display a synthetic Net Profit figure; instead it renders `Net Profit Unavailable (Incomplete Cost/Expense Inputs)` alongside an itemized completeness diagnostic.
- **Acceptance Criteria:**
  - An order containing a product without `cost_price` immediately marks the period snapshot as incomplete and suppresses the Net Profit number across dashboard cards and exports.

### Module 9: Customer Intelligence (CRM & VIP Insights)
- **Purpose:** Track customer purchasing behavior, VIP status, credit balances, and retention across branches.
- **Requirements:**
  - `customers` master with deduplication keys (source external ID, normalized phone, email), VIP flag/tier, credit limit, and `outstanding_balance`.
  - Branch affinity, lifetime value (`Net Sales`), order frequency, and last purchase date.
  - Watchlists / VIP segments for targeted follow-up.
- **Acceptance Criteria:**
  - Customer total spend and outstanding receivables match `orders` and `payments` sums with zero rounding drift.

### Module 10: Remote Command Center (Controlled Write Operations)
- **Purpose:** Enable safe, audited, risk-governed remote write actions from the platform to local source databases via the Local Sync Agent.
- **Requirements:**
  - **Zero Arbitrary SQL:** Only predefined, schema-validated command types (`UPDATE_PRODUCT_PRICE`, `ADJUST_STOCK`, `SET_REORDER_THRESHOLD`, `UPDATE_ORDER_STATUS`, `SYNC_NOW`).
  - **Command Outbox Lifecycle:** `Pending -> Approved -> Dispatched -> Executing -> Succeeded / Failed / Expired / Rejected / Awaiting Reconciliation`.
  - **Risk Tiers & Approvals:**
    - `Low` (e.g., `SYNC_NOW`, minor threshold tweak within policy): Auto-approved or single authorized manager.
    - `Medium` (e.g., price update within threshold, bounded stock adjustment): Requires Branch/General Manager approval (cannot self-approve if four-eyes policy enabled).
    - `High` (e.g., large price/stock adjustments, order status overrides): Requires Tenant Owner approval + re-authentication/OTP.
  - **Security Controls:** Cryptographic command signature, strict `expires_at` TTL, unique `idempotency_key`, and separate write-scoped DB credentials inside the Local Sync Agent.
  - **Reconciliation:** After `Executing -> Succeeded` is reported, the platform verifies via subsequent sync snapshot that the target value in the source DB matches the commanded state; mismatches or ambiguous timeouts enter `Awaiting Reconciliation`.
- **Acceptance Criteria:**
  - Tampering with command payload or signature causes the Local Sync Agent to reject execution and report a security error.
  - Expired commands (`now > expires_at`) transition to `Expired` and are never executed.

### Module 11: Alerts, Notifications & Internal Announcements
- **Purpose:** Keep teams informed of operational anomalies, sync disruptions, and pending approvals.
- **Requirements:**
  - Configurable alert rules: low stock (`available <= reorder_threshold`), sync `Disconnected`/`Error`, high-value order/refund, pending command approval, incomplete COGS warning.
  - Multi-channel delivery: In-app real-time notification center (via Reverb), email digest/critical alerts, optional webhook.
  - Internal tenant announcements/posts (`announcements`) scoped by tenant or specific branches.
- **Acceptance Criteria:**
  - A stock drop below `reorder_threshold` triggers one deduplicated active alert until acknowledged or restocked.

### Module 12: Reporting, Exports & Localization/Theming Workspace
- **Purpose:** Deliver polished, role-tailored analytical workspaces with full Arabic/English and Light/Dark support.
- **Requirements:**
  - Lightweight Design System with CSS tokens for `Light` and `Dark` modes, `RTL` (Arabic) and `LTR` (English) layouts, accessible contrast, and offline-capable Chart.js + fonts/icons.
  - Exportable reports (CSV, XLSX, Print/PDF) gated by role permissions and logged in `audit_logs`.
  - Responsive mobile-first tables, drawer navigation, and role-based default dashboards.
- **Acceptance Criteria:**
  - Every workspace passes WCAG AA contrast checks in both Light and Dark themes and renders cleanly in Arabic RTL and English LTR on desktop and mobile viewports.

---

## 3. Explicit Non-Goals (Out of Scope)

- Replacing local POS billing hardware, receipt printers, or offline cashier checkout screens.
- Executing arbitrary user-typed SQL queries against customer databases.
- Requiring customers to open inbound firewall ports (`3306`, RDP, SSH) to their local network.
- Supporting `float` / `double` arithmetic for currency or inventory quantities.
- Modifying, refactoring, or removing the legacy procedural PHP application files.
- Fabricating Net Profit figures when COGS or expense records are missing.

---

## 4. Global Non-Functional Requirements

- **Security:** TLS 1.2+ in transit, DPAPI for agent secrets at rest, parameterized queries only, strict defense-in-depth `tenant_id` isolation.
- **Performance:** Dashboard initial load < 1.5s p95; ingestion batch (500 records) < 2.0s p95; Reverb event delivery < 500ms post-commit locally.
- **Resilience:** Agent SQLite outbox survives network disconnects and service restarts with zero data loss and ordered replay.
- **Testability:** Full local verification without XAMPP using standalone PHP, MySQL/MariaDB, Reverb, Queue, Vite, Mock POS DB, and Playwright E2E tests.
