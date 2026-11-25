# User Roles, Permission Matrix & User Journeys

**Document Status:** Phase 01 Architectural Blueprint (Approved Baseline)  

---

## 1. Role Architecture & Scope Separation

Roles are strictly separated across two boundaries:
1. **Platform Scope (`users.is_platform_admin = true`):** Platform Super Admins operate at the infrastructure/SaaS plane. They do **not** require rows in `tenant_memberships` and have zero implicit access to tenant business records.
2. **Tenant Scope (`tenant_memberships`):** Every business user is linked to one or more tenants via `tenant_memberships` with an assigned `role`, optional `branch_ids` scope, and optional `warehouse_ids` scope.

### Canonical Role Definitions

| Role | Scope | Primary Responsibility |
| :--- | :--- | :--- |
| **Platform Super Admin** | Platform-wide (no `tenant_membership`) | SaaS tenant provisioning, subscription/plan management, global connector registry, platform telemetry, support diagnostics |
| **Tenant Owner** | Full Tenant (`*` branches/warehouses) | Tenant governance, billing/license activation, team & role management, connector & mapping setup, high-risk command approvals, full P&L |
| **General Manager** | All Tenant Branches (or assigned group) | Cross-branch operational oversight, analytics, sales & inventory monitoring, medium/configured command approvals |
| **Branch Manager** | Assigned `branch_ids` & linked `warehouse_ids` | Daily branch performance, branch orders/invoices, branch low-stock monitoring, initiating branch-scoped commands |
| **Accountant** | Tenant-wide or assigned `branch_ids` | Financial reconciliation, tax/discounts/refunds/COGS review, completeness verification, financial exports |
| **Inventory Manager** | Assigned `warehouse_ids` (or all warehouses) | Stock balances (`on_hand`, `reserved`, `available`), movement ledger audits, reorder thresholds, stock adjustment requests |
| **Observer** | Read-only (Tenant or assigned `branch_ids`) | Executive/investor/auditor read-only visibility into allowed KPIs and dashboards |

---

## 2. Granular Permission Matrix

**Legend:** `F` = Full Access | `S` = Scoped (Assigned Branches/Warehouses) | `R` = Read-Only | `A` = Approve Only | `I` = Initiate/Request Only | `-` = Denied

| Capability / Module | Super Admin | Tenant Owner | General Manager | Branch Manager | Accountant | Inventory Manager | Observer |
| :--- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Platform Tenants, Plans & System Health** | F | - | - | - | - | - | - |
| **Tenant Billing / License Management** | R (SaaS) | F | - | - | R | - | - |
| **Tenant Members & Role Assignments** | - | F | R | - | - | - | - |
| **Integration Center (Sources & Mappings)** | R (Health) | F | R | - | - | - | - |
| **Write Credentials Toggle (Enable/Disable)** | - | F | - | - | - | - | - |
| **Executive BI Dashboard (KPIs & Freshness)** | - | F | F | S | S (Fin) | S (Inv) | R / S |
| **Sales, Orders, Invoices & Returns** | - | F | F | S | R / S | - | R / S |
| **Financial Snapshots, COGS & Net Profit** | - | F | F | S (Sales) | F | - | R (Config) |
| **Inventory Balances (`on_hand/reserved/avail`)** | - | F | F | S | R | S / F | R / S |
| **Stock Movements Ledger** | - | R | R | S (Read) | R | S / F (Read) | R / S |
| **Customer Intelligence & VIP Insights** | - | F | F | S | R | - | R (No PII*) |
| **Remote Commands: Initiate Low/Med Risk** | - | F | F | I (Branch) | - | I (Stock) | - |
| **Remote Commands: Approve Low Risk** | - | F | A | A (Branch) | - | A (Whse) | - |
| **Remote Commands: Approve Medium Risk** | - | F | A | - | - | - | - |
| **Remote Commands: Approve High Risk** | - | A (2FA) | - | - | - | - | - |
| **Alerts & Internal Announcements** | Platform | F | F | S | S | S | R / S |
| **Data Exports (CSV / XLSX / PDF)** | - | F | F | S | F | S (Inv) | - |
| **Tenant Audit Logs** | Platform | R | R | - | R (Fin) | - | - |

*\*Observer PII masking (phone/address) is configurable per tenant security policy.*

---

## 3. Concrete User Journeys

### Journey 1: Platform Super Admin — Provisioning & Monitoring a SaaS Tenant
1. Signs into the Platform Control Portal (`/admin`) using MFA.
2. Views SaaS cluster health (active tenants, queue backlog, Reverb connections, aggregate sync errors—without browsing private tenant sales/customer rows).
3. Creates or upgrades a tenant subscription plan (e.g., `Growth - 10 Branches, Command Center Enabled`).
4. Triggers an onboarding invite link for the customer's **Tenant Owner** and audits plan enforcement metrics.

### Journey 2: Tenant Owner — Onboarding Local POS Integration & Approving a High-Risk Command
1. Accepts invitation, creates Tenant workspace (Arabic RTL default, currency `SAR`/`USD`), and downloads the **.NET 10 LTS Local Sync Agent** installer + one-time pairing token.
2. In **Integration Center**, inspects the newly registered `data_source` (`Mock Local POS MySQL`) and configures `field_mappings` between source tables (`pos_tx_hdr`, `wh_stock_bal`) and canonical entities.
3. Monitors initial sync batch; freshness badge transitions from `Syncing` -> `Live`.
4. Receives a real-time Reverb notification that Branch Manager A requested a **High-Risk** price override (`UPDATE_PRODUCT_PRICE`, >15% change).
5. Reviews the before/after diff, risk score, and expiration TTL; confirms with password/2FA to transition command from `Pending` -> `Approved` -> `Dispatched`.
6. Observes completion (`Succeeded`) and reconciliation verification (`Reconciled`).

### Journey 3: General Manager — Multi-Branch Morning Operations Review
1. Opens the live executive dashboard at 09:00 AM; verifies all 5 branches report `Live` freshness (notices Branch 3 reports `Delayed - 4m lag`).
2. Compares branch Gross vs. Net Sales, discounts, and refund spikes in Chart.js visualizations.
3. Sees Branch 2 has high `reserved` stock on a top-selling SKU leaving low `available` inventory; reviews pending transfer/adjustment commands initiated by the Inventory Manager and approves a **Medium-Risk** reorder threshold increase.

### Journey 4: Branch Manager — Managing Assigned Branch & Requesting Price/Stock Action
1. Logs in; `TenantContext` + scope restricts all queries, tables, and Reverb channels strictly to `Branch 2`.
2. Views live incoming POS orders pushed over Reverb after sync commits; prints an Arabic RTL invoice for a VIP client inquiry.
3. Notices an erroneous local shelf price vs. headquarters promotion; submits an `UPDATE_PRODUCT_PRICE` command request for Branch 2 (`Pending` state awaiting General Manager/Owner approval).
4. Checks `stock_movements` for Branch 2's warehouse when a customer disputes item availability.

### Journey 5: Accountant — Month-End Financial Verification & Profit Guardrail Audit
1. Navigates to **Financial Analytics** and selects the monthly period across all branches.
2. Inspects exact `DECIMAL` columns: Gross Sales, Discounts, Refunds, Net Sales, Tax Collected, Paid, and Outstanding Receivables.
3. Notices the **Net Profit** card displays `Unavailable — Incomplete Cost/Expense Inputs` with status `partial_cogs` because 3 newly synced POS items lack `cost_price`.
4. Exports the itemized completeness diagnostic CSV, coordinates with Inventory/POS team to populate unit costs, and once re-synced and confirmed `complete`, validates and exports the final P&L report.

### Journey 6: Inventory Manager — Multi-Warehouse Ledger & Stock Reconciliation
1. Opens **Inventory Workspace** filtered to assigned Warehouses (`WH-Central`, `WH-North`).
2. Checks `on_hand` (120.0000), `reserved` (35.0000), and `available` (85.0000) for SKU `PRD-1042` rather than relying on a single ambiguous count.
3. Drills into the immutable `stock_movements` ledger to trace why `reserved` jumped (3 wholesale pending orders).
4. Initiates an `ADJUST_STOCK` command (damage write-off of `-2.0000` units with mandatory reason code), which routes through approval, executes via the agent's write credentials, and reconciles on the next sync cycle.

### Journey 7: Observer — Read-Only Executive Monitoring
1. Signs in via browser on mobile (Arabic RTL, Dark Mode).
2. Views read-only executive KPI cards, branch leaderboard, and freshness status badges.
3. All command buttons, mapping editors, and sensitive PII exports are hidden in UI and strictly blocked (`403`) at the backend policy layer.
