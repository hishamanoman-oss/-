import { test, expect } from '@playwright/test';

test.describe('Phase 03 Enterprise UI, Multi-Tenancy & Branch Persistence E2E', () => {
    test('login, RTL/LTR, Dark/Light mode, tenant switching, branch creation persistence & mobile drawer', async ({ page }) => {
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
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');
        await expect(page.getByTestId('sync-status-pill')).toBeVisible();
        await expect(page.getByTestId('kpi-branches-count')).toContainText('2');

        // 2. Toggle Dark Mode and verify persistence across reload
        await page.getByTestId('theme-toggle').click();
        await expect(page.locator('html')).toHaveClass(/dark/);
        await page.reload();
        await expect(page.locator('html')).toHaveClass(/dark/);

        // Switch back to Light Mode
        await page.getByTestId('theme-toggle').click();
        await expect(page.locator('html')).not.toHaveClass(/dark/);

        // 3. Toggle English LTR and back to Arabic RTL
        await page.getByTestId('locale-toggle').click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');

        await page.getByTestId('locale-toggle').click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');

        // 4. Switch Active Tenant from Tenant A to Tenant B and verify live DB branch list updates
        const tenantSwitcher = page.getByTestId('tenant-switcher');
        const tenantBValue = await tenantSwitcher.locator('option', { hasText: 'Horizon Retail' }).getAttribute('value');
        await tenantSwitcher.selectOption(tenantBValue);
        await expect(page).toHaveURL(/\/dashboard/);
        await expect(page.getByTestId('kpi-branches-count')).toContainText('1');
        await expect(page.locator('body')).toContainText('JED-MAIN');

        // Switch back to Tenant A
        const tenantAValue = await tenantSwitcher.locator('option', { hasText: 'Al-Noor Trading' }).getAttribute('value');
        await tenantSwitcher.selectOption(tenantAValue);
        await expect(page.locator('body')).toContainText('RYD-MAIN');

        // 5. Navigate to /organization/branches and create a real new branch, verify persistence after reload
        await page.goto('/organization/branches');
        const uniqueCode = `E2E-${Date.now().toString().slice(-4)}`;
        await page.getByTestId('add-branch-btn').click();
        await page.getByTestId('branch-code-input').fill(uniqueCode);
        await page.getByTestId('branch-name-input').fill(`فرع الاختبار الحي ${uniqueCode}`);
        await page.getByTestId('branch-city-input').fill('الخبر');
        await page.getByTestId('branch-save-btn').click();

        await expect(page.getByTestId(`branch-row-${uniqueCode}`)).toBeVisible();
        await page.reload();
        await expect(page.getByTestId(`branch-row-${uniqueCode}`)).toBeVisible();

        // 6. Test mobile viewport (390x844): slide-over navigation drawer & zero horizontal overflow
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto('/dashboard');
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(overflow).toBe(false);

        await page.getByTestId('mobile-menu-btn').click();
        await expect(page.getByTestId('app-sidebar')).toBeVisible();

        expect(consoleErrors).toEqual([]);
        expect(pageErrors).toEqual([]);
    });
});
