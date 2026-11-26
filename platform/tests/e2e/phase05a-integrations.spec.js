import { test, expect } from '@playwright/test';

test.describe('Phase 05A Integration Center, Live Sync & Reconciliation E2E', () => {
    test('tests connection, discovers schema, runs live mock_pos_db sync, verifies header pill and tenant isolation', async ({ page }) => {
        const consoleErrors = [];
        const pageErrors = [];

        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });
        page.on('pageerror', (err) => {
            pageErrors.push(err.message);
        });

        // 1. Login as multi-tenant owner (Arabic default locale, Tenant A: Al-Noor Trading)
        await page.goto('/login');
        await page.getByTestId('login-email').fill('owner@demo.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);

        // Initial header sync pill should show Disconnected before any sync/agent heartbeat
        await expect(page.getByTestId('sync-status-pill')).toContainText(/غير متصل|Disconnected/);

        // 2. Navigate to Integration Center
        await page.getByTestId('nav-integrations').click();
        await expect(page).toHaveURL(/\/integrations/);
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).toContainText('Mock Local POS (MySQL)');

        // 3. Test Connection (read-only connector -> mock_pos_db)
        await page.locator('[data-testid^="test-conn-btn-"]').first().click();
        await expect(page.getByTestId('connection-test-banner')).toBeVisible();
        await expect(page.getByTestId('connection-test-banner')).toContainText('mock_pos_db');

        // 4. Discover Schema (INFORMATION_SCHEMA read-only)
        await page.locator('[data-testid^="discover-schema-btn-"]').first().click();
        await expect(page.getByTestId('discovered-schema-panel')).toBeVisible();
        await expect(page.getByTestId('discovered-schema-panel')).toContainText('tbl_branches');
        await expect(page.getByTestId('discovered-schema-panel')).toContainText('tbl_items');
        await expect(page.getByTestId('discovered-schema-panel')).toContainText('tbl_invoices');

        // 5. Return to Sources tab and execute Run Sync Now
        await page.getByTestId('tab-sources').click();
        await page.locator('[data-testid^="run-sync-btn-"]').first().click();

        // Source freshness badge updates to Live
        await expect(page.locator('[data-testid^="source-freshness-"]').first()).toContainText(/متصل مباشر|Live/, { timeout: 20000 });

        // 6. Inspect Sync Runs & run Zero-Drift Reconciliation
        await page.getByTestId('tab-runs').click();
        await expect(page.locator('[data-testid^="sync-run-row-"]').first()).toContainText('succeeded');
        await expect(page.locator('[data-testid^="sync-run-row-"]').first()).toContainText('116');

        await page.getByTestId('run-reconcile-btn').click();
        await expect(page.getByTestId('reconciliation-panel')).toBeVisible();
        await expect(page.getByTestId('reconciliation-panel')).toContainText('In Sync (0 Drift)');

        // 7. Enrollment Code generation and immediate Revocation
        await page.getByTestId('tab-enrollment').click();
        await page.getByTestId('new-device-name-input').fill('Playwright POS Agent');
        await page.getByTestId('generate-enrollment-btn').click();
        await expect(page.getByTestId('enrollment-code-banner')).toBeVisible();
        await expect(page.getByTestId('generated-enrollment-code')).toContainText('RBD-');

        await page.locator('[data-testid^="revoke-instance-btn-"]').first().click();
        await expect(page.locator('[data-testid^="instance-row-"]').first()).toContainText('revoked');

        // 8. Reload page and verify Top Header Sync Status Pill dynamically shows Live!
        await page.reload();
        await expect(page.getByTestId('sync-status-pill')).toContainText(/متصل مباشر|Live/);

        // 9. Verify imported mock_pos_db product and order appear in Tenant A pages
        await page.goto('/inventory');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('inventory-search').fill('POS-PRN-001');
        await expect(page.locator('body')).toContainText('POS-PRN-001');

        await page.goto('/orders');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('orders-search').fill('POS-2026-0001');
        await expect(page.locator('body')).toContainText('POS-2026-0001');

        // 10. Switch to Tenant B and verify strict isolation (no Tenant A DataSource or imported POS orders)
        const secondTenantOption = await page.getByTestId('tenant-switcher').locator('option', { hasText: 'Horizon Retail' }).getAttribute('value');
        await page.getByTestId('tenant-switcher').selectOption(secondTenantOption);
        await expect(page).toHaveURL(/\/dashboard/);

        await page.goto('/integrations');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).not.toContainText('Mock Local POS (MySQL)');

        await page.goto('/orders');
        await page.waitForLoadState('networkidle');
        await expect(page.locator('body')).not.toContainText('POS-2026-0001');

        expect(consoleErrors, `Console errors detected: ${consoleErrors.join('; ')}`).toEqual([]);
        expect(pageErrors, `Page errors detected: ${pageErrors.join('; ')}`).toEqual([]);
    });
});
