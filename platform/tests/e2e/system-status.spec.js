import { test, expect } from '@playwright/test';

test.describe('Phase 02 Standalone System Health & Browser Verification', () => {
    test('GET /api/v1/health reports live rbd_platform, mock_pos_db, and reverb', async ({ request }) => {
        const response = await request.get('/api/v1/health');
        expect(response.status()).toBe(200);

        const body = await response.json();
        expect(body.status).toBe('ok');
        expect(body.service).toBe('remote-business-platform');
        expect(body.checks.rbd_platform.status).toBe('ok');
        expect(body.checks.mock_pos_db.status).toBe('ok');
        expect(body.checks.reverb.status).toBe('ok');
        expect(body.checks.mock_pos_db.counts.tbl_items).toBe(22);
        expect(body.checks.mock_pos_db.counts.tbl_clients).toBe(16);
        expect(body.checks.mock_pos_db.counts.tbl_invoices).toBe(28);
        expect(body.checks.mock_pos_db.counts.tbl_stock_qty).toBe(44);
    });

    test('GET /system-status renders RTL Arabic, live counts, Reverb OK, and toggles to LTR English', async ({ page }) => {
        const consoleErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') {
                consoleErrors.push(msg.text());
            }
        });

        await page.goto('/system-status');

        await expect(page.locator('html')).toHaveAttribute('dir', 'rtl');
        await expect(page.locator('html')).toHaveAttribute('lang', 'ar');

        await expect(page.getByTestId('overall-status-badge')).toBeVisible();
        await expect(page.getByTestId('platform-db-status')).toBeVisible();
        await expect(page.getByTestId('mock-pos-status')).toBeVisible();
        await expect(page.getByTestId('reverb-status')).toBeVisible();

        await expect(page.getByTestId('mock-count-tbl_items')).toHaveText('22');
        await expect(page.getByTestId('mock-count-tbl_clients')).toHaveText('16');
        await expect(page.getByTestId('mock-count-tbl_invoices')).toHaveText('28');
        await expect(page.getByTestId('mock-count-tbl_stock_qty')).toHaveText('44');

        // Trigger Livewire refresh action
        await page.getByTestId('refresh-status-btn').click();
        await expect(page.getByTestId('mock-count-tbl_invoices')).toHaveText('28');

        // Toggle to English (LTR)
        await page.getByTestId('locale-toggle').click();
        await expect(page.locator('html')).toHaveAttribute('dir', 'ltr');
        await expect(page.locator('html')).toHaveAttribute('lang', 'en');
        await expect(page.getByTestId('overall-status-badge')).toContainText('Operational');

        expect(consoleErrors).toEqual([]);
    });
});
