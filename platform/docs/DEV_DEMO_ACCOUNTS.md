# Development Demo Accounts (Local / Testing Only)

> **Security Warning:** These accounts are seeded **exclusively** by `DevelopmentDemoSeeder` in `local` and `testing` environments. Never deploy or seed these accounts in production.

**Shared Development Password:** `Demo@Phase03!`

| Role / Scenario | Email | Scope |
| :--- | :--- | :--- |
| **Platform Super Admin** | `admin@platform.local` | `is_platform_admin = true` (access to `/platform/tenants`, no tenant membership required) |
| **Multi-Tenant Owner** | `owner@demo.local` | `tenant_owner` in **both** `Al-Noor Trading` (`al-noor-trading`) and `Horizon Retail` (`horizon-retail`) |
| **Branch Manager (Scoped)** | `branch1@alnoor.local` | `branch_manager` in `Al-Noor Trading` — restricted **only** to `RYD-MAIN` (Branch 1) |
| **Accountant** | `accountant@alnoor.local` | `accountant` in `Al-Noor Trading` (view access to Branch 1 & Branch 2) |
| **Inventory Manager** | `inventory@alnoor.local` | `inventory_manager` in `Al-Noor Trading` (manage access to Branch 1 & Branch 2) |
| **Observer (Read-Only)** | `observer@alnoor.local` | `observer` in `Al-Noor Trading` (view access to Branch 1 only) |
| **Deactivated User** | `disabled@alnoor.local` | `is_active = false` (login & session immediately blocked) |

## Seeded Tenants & Organization Structure
- **Tenant A — `شركة النور التجارية (Al-Noor Trading)` (`al-noor-trading`):**
  - `RYD-MAIN`: Main Branch - Riyadh (`WH-RYD-01`)
  - `RYD-NORTH`: North Branch - Riyadh (`WH-RYD-02`)
- **Tenant B — `آفاق للتجزئة (Horizon Retail)` (`horizon-retail`):**
  - `JED-MAIN`: Jeddah Flagship (`WH-JED-01`)
