# Local Sync Agent (.NET 10 LTS) — Placeholder

This directory is reserved for the standalone **.NET 10 LTS Local Sync Agent** (Windows Worker Service) scheduled for **Phase 05B** and **Phase 07**.

## Planned Capabilities
- **Capture Strategy:** `incremental_polling` (default) and `cdc_binlog` (opt-in advanced capability).
- **Security:** Outbound-only HTTPS (`TLS 1.2+`), zero open inbound ports, Windows DPAPI credential encryption, separate read-only sync credentials vs. scoped write credentials.
- **Resilience:** Local SQLite persistent outbox & watermark tracking.
- **Remote Commands:** `Ed25519` signature verification, TTL expiry enforcement, idempotency checks, and strongly-typed allowlisted command handlers (zero arbitrary SQL).
