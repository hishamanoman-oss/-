import { test, expect } from '@playwright/test';

async function assertCanvasRendered(page, testId) {
    const canvas = page.getByTestId(testId);
    await expect(canvas).toBeVisible({ timeout: 15000 });
    const box = await canvas.boundingBox();
    expect(box, `Canvas ${testId} should have a non-zero bounding box`).not.toBeNull();
    expect(box.width).toBeGreaterThan(100);
    expect(box.height).toBeGreaterThan(80);
}

test.describe('Phase 08 Chart.js Analytics, Alerts Center, Reports & Mobile UX Polish E2E', () => {
    test('dashboard Chart.js renders across RTL/LTR & Light/Dark, /reports exports CSV/XLSX/PDF, /alerts bell updates, and 390x844 mobile has zero horizontal overflow', async ({ page }) => {
        test.setTimeout(120000);

        const consoleErrors = [];
        const pageErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });
        page.on('pageerror', (err) => pageErrors.push(err.message));

        // 1. Login as Owner -> /dashboard (Arabic RTL, Light mode)
        await page.goto('/login');
        await page.getByTestId('login-email').fill('owner@demo.local');
        await page.getByTestId('login-password').fill('Demo@Phase03!');
        await page.getByTestId('login-submit').click();
        await expect(page).toHaveURL(/\/dashboard/);
        await page.waitForLoadState('networkidle');

        await assertCanvasRendered(page, 'chart-sales-trend');
        await assertCanvasRendered(page, 'chart-inventory-health');
        await assertCanvasRendered(page, 'chart-branch-comparison');
        await expect(page.getByTestId('dashboard-targets-card')).toBeVisible();

        // 2. Toggle Dark Mode & re-verify Chart.js canvases remain rendered cleanly
        await page.getByTestId('theme-toggle').click();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveClass(/dark/);
        await assertCanvasRendered(page, 'chart-sales-trend');

        // 3. Toggle Locale to English LTR & verify Chart.js renders cleanly in LTR
        await page.getByTestId('locale-toggle').click();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await assertCanvasRendered(page, 'chart-sales-trend');

        // Switch back to Light Mode + Arabic RTL
        await page.getByTestId('theme-toggle').click();
        await page.waitForLoadState('networkidle');
        await page.getByTestId('locale-toggle').click();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');

        // 4. Open /reports -> verify Chart.js preview, create Performance Target, create Scheduled Report & verify CSV/XLSX/PDF downloads
        await page.getByTestId('nav-reports').click();
        await expect(page).toHaveURL(/\/reports/);
        await page.waitForLoadState('networkidle');
        await assertCanvasRendered(page, 'report-preview-chart');

        // Verify live downloadable endpoints directly via Playwright APIRequestContext
        const csvHref = await page.getByTestId('export-csv-btn').getAttribute('href');
        const xlsxHref = await page.getByTestId('export-xlsx-btn').getAttribute('href');
        const pdfHref = await page.getByTestId('export-pdf-btn').getAttribute('href');

        const csvRes = await page.request.get(csvHref);
        expect(csvRes.status()).toBe(200);
        expect(csvRes.headers()['content-type']).toContain('text/csv');
        const csvBuf = await csvRes.body();
        expect(csvBuf.slice(0, 3).equals(Buffer.from([0xEF, 0xBB, 0xBF]))).toBeTruthy();

        const xlsxRes = await page.request.get(xlsxHref);
        expect(xlsxRes.status()).toBe(200);
        expect(xlsxRes.headers()['content-type']).toContain('spreadsheetml.sheet');
        const xlsxBuf = await xlsxRes.body();
        expect(xlsxBuf.slice(0, 2).toString()).toBe('PK');

        const pdfRes = await page.request.get(pdfHref);
        expect(pdfRes.status()).toBe(200);
        expect(pdfRes.headers()['content-type']).toContain('application/pdf');
        const pdfBuf = await pdfRes.body();
        expect(pdfBuf.slice(0, 5).toString()).toBe('%PDF-');

        // Create Performance Target
        await page.getByTestId('tab-reports-targets').click();
        await expect(page.getByTestId('tab-panel-targets')).toBeVisible();
        await page.getByTestId('target-metric-select').selectOption('orders_count');
        await page.getByTestId('target-value-input').fill('25');
        await page.getByTestId('create-target-btn').click();
        await expect(page.locator('[data-testid^="target-card-"]', { hasText: 'orders_count' }).first()).toBeVisible({ timeout: 15000 });

        // Create Scheduled Report
        await page.getByTestId('tab-reports-scheduled').click();
        await expect(page.getByTestId('tab-panel-scheduled')).toBeVisible();
        await page.getByTestId('sched-name-input').fill('Phase 08 E2E Weekly Report');
        await page.getByTestId('create-scheduled-btn').click();
        await expect(page.locator('[data-testid^="scheduled-row-"]', { hasText: 'Phase 08 E2E Weekly Report' }).first()).toBeVisible({ timeout: 15000 });

        // 5. Open /alerts -> click Evaluate Rules Now -> verify Header Bell unread count updates -> mark read
        await page.getByTestId('nav-alerts').click();
        await expect(page).toHaveURL(/\/alerts/);
        await page.waitForLoadState('networkidle');

        await page.getByTestId('evaluate-rules-btn').click();
        await expect(page.getByTestId('header-bell-badge')).toBeVisible({ timeout: 15000 });

        const firstMarkRead = page.locator('[data-testid^="mark-read-btn-"]').first();
        await expect(firstMarkRead).toBeVisible({ timeout: 15000 });
        await firstMarkRead.click();

        // 6. Mobile (390x844) Sweep across major routes in both RTL & LTR -> zero horizontal overflow
        await page.setViewportSize({ width: 390, height: 844 });
        const mobileRoutes = ['/dashboard', '/orders', '/inventory', '/commands', '/reports', '/alerts'];

        for (const route of mobileRoutes) {
            await page.goto(route);
            await page.waitForLoadState('networkidle');
            const overflowRtl = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            expect(overflowRtl, `Horizontal overflow detected on ${route} (RTL mobile)`).toBe(false);
        }

        // Toggle to English LTR on mobile & verify /dashboard and /reports have zero horizontal overflow
        await page.goto('/dashboard');
        await page.getByTestId('locale-toggle').click();
        await page.waitForLoadState('networkidle');
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');

        for (const route of ['/dashboard', '/reports', '/alerts']) {
            await page.goto(route);
            await page.waitForLoadState('networkidle');
            const overflowLtr = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
            expect(overflowLtr, `Horizontal overflow detected on ${route} (LTR mobile)`).toBe(false);
        }

        // Restore Arabic RTL
        await page.getByTestId('locale-toggle').click();
        await page.waitForLoadState('networkidle');

        expect(consoleErrors, `Console errors: ${consoleErrors.join('; ')}`).toEqual([]);
        expect(pageErrors, `Page errors: ${pageErrors.join('; ')}`).toEqual([]);
    });
});
