import { test, expect } from '@playwright/test';

test.describe('Phase 06 BI Dashboard, Products, CRM, Financials & RBAC E2E', () => {
    test('owner explores BI dashboard, drill-downs, products, CRM, financials, printable invoice & branch/role scoping', async ({ page }) => {
        test.setTimeout(120000);

        const consoleErrors = [];
        const pageErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });
        page.on('pageerror', (err) => pageErrors.push(err.message));

        // 1. Login as Owner (Tenant A)
        await page.goto('/login');
        await page.getByTestId('login-email').fill('owner@demo.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await page.waitForLoadState('networkidle');

        // Verify Real-Time BI Dashboard KPIs, Attention Needed, Top Branches & Period Switcher
        await expect(page.getByTestId('kpi-net-sales')).toBeVisible();
        await expect(page.getByTestId('kpi-orders-count')).toBeVisible();
        await expect(page.getByTestId('kpi-low-stock')).toBeVisible();
        await expect(page.getByTestId('attention-needed-panel')).toBeVisible();
        await expect(page.locator('body')).toContainText('RYD-MAIN');

        await page.getByTestId('dashboard-period-select').selectOption('7d');
        await page.waitForLoadState('networkidle');
        await page.getByTestId('dashboard-period-select').selectOption('30d');
        await page.waitForLoadState('networkidle');

        // 2. Drill-down from Dashboard Low-Stock KPI into /inventory?stockFilter=low_stock
        await page.getByTestId('drill-inventory-alerts').click();
        await expect(page).toHaveURL(/\/inventory/);
        await page.waitForLoadState('networkidle');

        await expect(page.getByTestId('inventory-valuation-card')).toBeVisible();
        await page.getByTestId('stock-filter-slow').click();
        await page.getByTestId('stock-filter-low').click();
        await page.getByTestId('stock-filter-all').click();

        // 3. Navigate to /products, verify Cost column visible for Owner and open detail drawer
        await page.getByTestId('nav-products').click();
        await expect(page).toHaveURL(/\/products/);
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('cost-price-header')).toBeVisible();

        await page.getByTestId('products-search').fill('POS-PRN-001');
        await expect(page.getByTestId('product-row-POS-PRN-001')).toBeVisible();
        await page.getByTestId('view-product-POS-PRN-001').click();
        await expect(page.getByTestId('drawer-product-cost')).toBeVisible();
        await page.locator('[aria-label="Close"]').click();

        // 4. Navigate to /customers, verify CRM LTV & open customer profile drawer
        await page.getByTestId('nav-customers').click();
        await expect(page).toHaveURL(/\/customers/);
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('customer-row-CUST-AN-001')).toBeVisible();
        await page.getByTestId('view-customer-CUST-AN-001').click();
        await expect(page.locator('body')).toContainText('ORD-2026-000001');
        await page.locator('[aria-label="Close"]').click();

        // 5. Navigate to /financials, verify 9-metric breakdown, branch comparison & printable invoice
        await page.getByTestId('nav-financials').click();
        await expect(page).toHaveURL(/\/financials/);
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('financials-profit-guard')).toBeVisible();
        await expect(page.getByTestId('financials-metrics-grid')).toBeVisible();
        await expect(page.getByTestId('financial-branch-row-RYD-MAIN')).toBeVisible();

        await page.locator('[data-testid^="invoice-link-"]').first().click();
        await expect(page).toHaveURL(/\/orders\/\d+\/invoice/);
        await expect(page.getByTestId('invoice-order-number')).toBeVisible();
        await expect(page.getByTestId('print-invoice-btn')).toBeVisible();

        // Logout Owner
        await page.getByTestId('logout-btn').click();
        await expect(page).toHaveURL(/\/login/);

        // 6. Login as Branch 1 Manager -> scoped strictly to RYD-MAIN, blocked from RYD-NORTH & /integrations
        await page.getByTestId('login-email').fill('branch1@alnoor.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await page.waitForLoadState('networkidle');

        await expect(page.locator('body')).toContainText('RYD-MAIN');
        await expect(page.locator('body')).not.toContainText('RYD-NORTH');
        const integrationsRes = await page.request.get('/integrations');
        expect(integrationsRes.status()).toBe(403);

        await page.getByTestId('logout-btn').click();
        await expect(page).toHaveURL(/\/login/);

        // 7. Login as Inventory Manager -> blocked 403 on /financials and /customers, allowed on /products
        await page.getByTestId('login-email').fill('inventory@alnoor.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await page.waitForLoadState('networkidle');

        const finRes = await page.request.get('/financials');
        expect(finRes.status()).toBe(403);
        const custRes = await page.request.get('/customers');
        expect(custRes.status()).toBe(403);

        await page.goto('/products');
        await expect(page.getByTestId('cost-price-header')).toBeVisible();

        await page.getByTestId('logout-btn').click();
        await expect(page).toHaveURL(/\/login/);

        // 8. Login as Observer -> cost price column strictly hidden on /products
        await page.getByTestId('login-email').fill('observer@alnoor.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await page.goto('/products');
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('cost-price-header')).toHaveCount(0);

        expect(consoleErrors, `Console errors: ${consoleErrors.join('; ')}`).toEqual([]);
        expect(pageErrors, `Page errors: ${pageErrors.join('; ')}`).toEqual([]);
    });
});
