import { test, expect } from '@playwright/test';

test.describe('Phase 04 Inventory Ledger, Orders & Financial Guard E2E', () => {
    test('stock adjustment persists, order snapshot drawer renders, profit guard active, and tenant switch isolates data', async ({ page }) => {
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

        // 1. Login as multi-tenant owner
        await page.goto('/login');
        await page.getByTestId('login-email').fill('owner@demo.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);

        // 2. Navigate to /inventory and verify On Hand / Reserved / Available
        await page.getByTestId('nav-inventory').click();
        await expect(page).toHaveURL(/\/inventory/);
        await page.waitForLoadState('networkidle');

        await expect(page.getByTestId('on-hand-AN-PRN-80-WH-RYD-01')).toHaveText('40.00');
        await expect(page.getByTestId('reserved-AN-PRN-80-WH-RYD-01')).toHaveText('3.00');
        await expect(page.getByTestId('available-AN-PRN-80-WH-RYD-01')).toHaveText('37.00');

        // 3. Execute real Stock Adjustment (+5) and verify persistence after reload
        await page.getByTestId('adjust-btn-AN-PRN-80-WH-RYD-01').click();
        await page.getByTestId('adjust-delta-input').fill('5');
        await page.getByTestId('adjust-notes-input').fill('E2E Cycle Count +5');
        await page.getByTestId('adjust-save-btn').click();

        await expect(page.getByTestId('on-hand-AN-PRN-80-WH-RYD-01')).toHaveText('45.00');
        await expect(page.getByTestId('available-AN-PRN-80-WH-RYD-01')).toHaveText('42.00');

        await page.reload();
        await expect(page.getByTestId('on-hand-AN-PRN-80-WH-RYD-01')).toHaveText('45.00');
        await expect(page.getByTestId('available-AN-PRN-80-WH-RYD-01')).toHaveText('42.00');

        // Verify immutable movement recorded in drawer
        await page.getByTestId('movements-btn-AN-PRN-80-WH-RYD-01').click();
        await expect(page.locator('body')).toContainText('E2E Cycle Count +5');

        // 4. Navigate to /orders, verify Profit Completeness Guard and open order snapshot drawer
        await page.goto('/orders');
        await expect(page.getByTestId('profit-completeness-guard')).toBeVisible();
        await expect(page.getByTestId('order-row-ORD-2026-000001')).toBeVisible();

        await page.getByTestId('view-order-ORD-2026-000001').click();
        await expect(page.getByTestId('order-item-snapshot-AN-PRN-80')).toBeVisible();

        // 5. Switch to Tenant B (Horizon Retail) and verify strict inventory & orders isolation
        const tenantSwitcher = page.getByTestId('tenant-switcher');
        const tenantBValue = await tenantSwitcher.locator('option', { hasText: 'Horizon Retail' }).getAttribute('value');
        await tenantSwitcher.selectOption(tenantBValue);
        await expect(page).toHaveURL(/\/dashboard/);

        await page.goto('/inventory');
        await expect(page.getByTestId('inventory-row-HZ-POS-PRO-WH-JED-01')).toBeVisible();
        await expect(page.locator('body')).not.toContainText('AN-PRN-80');

        await page.goto('/orders');
        await expect(page.locator('body')).toContainText('Red Sea Hypermarket');
        await expect(page.locator('body')).not.toContainText('شركة التقدم التجارية');

        expect(consoleErrors).toEqual([]);
        expect(pageErrors).toEqual([]);
    });
});
