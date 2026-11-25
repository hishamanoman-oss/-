# Product Vision — Remote Business Dashboard

**Document Status:** Phase 01 Architectural Blueprint (Approved Baseline)  
**Product Name:** Remote Business Dashboard (Remote Business Intelligence & Operations Platform)  
**Primary Localization:** Arabic-First (`RTL`) with full English (`LTR`) support  

---

## 1. Executive Positioning

**Remote Business Dashboard** is a **Business Intelligence, Data Integration, and Controlled Remote Operations Platform** designed for retail, wholesale, and multi-branch businesses whose operational systems (POS, local accounting, or warehouse databases) reside on-premises or in isolated environments.

**What the platform IS:**
- An **executive and operational control plane** that unifies live sales, multi-branch/multi-warehouse inventory, customer insights, and financial analytics into a single real-time web workspace.
- An **integration hub** powered by a zero-inbound-port local sync agent and canonical schema mapping.
- A **controlled remote operations gateway** enabling authorized managers to issue audited, signed, risk-approved operational updates back to source systems without raw SQL or insecure remote desktop/database access.

**What the platform IS NOT:**
- **Not a replacement ERP or POS cashier terminal.** Source POS and accounting systems remain the system of record for point-of-sale checkout, local fiscal receipts, and core accounting entries.
- **Not an open SQL tunnel.** Remote write capabilities are strictly bounded to allowlisted, typed, signed, and reconciled operational commands.

---

## 2. Problem Statement

Store owners and multi-branch operators currently face four structural gaps:

1. **Blind Remote Visibility:** Legacy store tools and on-premises POS databases trap operational data on local machines. Owners rely on screenshots, phone calls, or risky port forwarding/TeamViewer to check daily performance.
2. **Insecure Integration Practices:** Exposing local MySQL/MariaDB ports (`3306`) to the internet or running ad-hoc scripts introduces severe ransomware and data-leakage risk.
3. **Misleading Financial & Stock Metrics:** Simple dashboards frequently mix Gross Sales with Net Sales, omit refunds/discounts/COGS, use floating-point math, or show a single "count" that hides reserved vs. actually available stock across branches.
4. **All-or-Nothing Remote Control:** Businesses need a middle ground between read-only viewing and unrestricted remote database editing—such as approving a price update, adjusting a reorder threshold, or authorizing a controlled stock correction with full auditability.

---

## 3. Target Audiences

| Segment | Profile | Primary Needs |
| :--- | :--- | :--- |
| **Single-Store & Growing Retail Owners** | 1–3 branches using local MySQL/MariaDB POS or legacy store databases | Mobile-friendly Arabic/English live sales tracking, low-stock alerts, trustworthy profit/margin visibility |
| **Multi-Branch & Warehouse Operators** | 3–50+ branches and central/regional warehouses | Real-time separation of `on_hand`, `reserved`, and `available` stock per warehouse, branch comparison, controlled price/stock commands |
| **Accountants & Finance Controllers** | Internal or outsourced financial reviewers | Exact `DECIMAL` breakdown (Gross, Discounts, Refunds, Net Sales, Tax, Paid, Outstanding, COGS, Net Profit), honest completeness indicators, exportable audit trails |
| **Self-Hosted / Data-Sovereign Enterprises** | Organizations with strict on-prem or private-cloud policies | Single-tenant deployment on customer-managed MySQL 8.4 / MariaDB 11.4 with signed license validation and zero external SaaS dependency |
| **SaaS Platform Operators** | Multi-tenant service providers | Shared multi-tenant hosting with strict `tenant_id` isolation, subscription plans/quotas, and centralized operations |

---

## 4. Deployment Dualism: SaaS vs. Self-Hosted Value Proposition

A single Laravel 13 modular monolith codebase serves both commercial models without maintaining forked schemas or separate business logic:

| Dimension | SaaS Mode | Self-Hosted Mode |
| :--- | :--- | :--- |
| **Database Architecture** | Shared relational database with strict `tenant_id` isolation across every tenant table | Single-tenant default (same `tenant_id` schema) on customer-owned MariaDB 11.4 / MySQL 8.4 |
| **Commercial Enforcement** | Subscriptions, plan tiers, branch/user/source quotas | Signed cryptographic license validation (feature flags, expiry, branch/source limits) |
| **Infrastructure Profile** | Redis mandatory for caching, sessions, and queues + horizontal Reverb scaling | Simplified footprint (Database/Redis cache & queue drivers, single-node Reverb) |
| **Value Proposition** | Zero server maintenance, rapid onboarding, multi-tenant access via `tenant_memberships` | Full data sovereignty, air-gap/private-network friendliness, predictable licensing |

---

## 5. Core Architectural & Product Differentiators

1. **Outbound-Only Local Sync Agent (.NET 10 LTS):**
   - Runs as a Windows Worker Service inside the customer network.
   - Zero open inbound firewall or database ports; communicates exclusively via outbound HTTPS.
   - Protects database credentials using Windows DPAPI and buffers events in a local SQLite outbox during internet outages.
2. **Honest Data Freshness & Financial Completeness:**
   - Every branch and data source surfaces an explicit, verifiable state: `Live`, `Delayed`, `Syncing`, `Disconnected`, or `Error`.
   - **Profit Integrity Rule:** Net Profit is never fabricated or displayed when COGS or expense inputs are incomplete—the UI explicitly flags incomplete inputs instead of presenting misleading margins.
3. **Controlled Remote Command Center:**
   - Command Outbox lifecycle (`Pending -> Approved -> Dispatched -> Executing -> Succeeded / Failed / Expired / Rejected / Awaiting Reconciliation`).
   - Risk-tiered approvals, cryptographic signatures, TTL expiry, idempotency keys, separate read-only vs. write database credentials, and post-execution reconciliation.
4. **Multi-Branch & Multi-Warehouse Inventory Ledger from Day 1:**
   - Immutable `stock_movements` ledger backed by exact `DECIMAL` arithmetic and strict separation of `on_hand`, `reserved`, and `available` balances.
5. **Bilingual RTL-First Experience & Legacy Safety:**
   - Built natively for Arabic (`RTL`) and English (`LTR`) with Light/Dark design tokens, while preserving the existing legacy PHP application strictly as a read-only backup.
