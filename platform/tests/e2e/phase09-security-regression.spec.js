import { test, expect } from '@playwright/test';

async function loginAs(page, email) {
    await page.goto('/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill('Demo@Phase03!');
    await page.getByTestId('login-submit').click();
    await expect(page).toHaveURL(/\/dashboard/);
}

async function logout(page) {
    await page.getByTestId('logout-btn').click();
    await expect(page).toHaveURL(/\/login/);
}

test.describe('Phase 09 Security Hardening, CSP, Live XSS Defense & Multi-Role Boundaries E2E', () => {
    test('HTTP security headers present, zero CSP/console errors, live XSS payload escaped, and multi-role UI boundaries enforced', async ({ page }) => {
        test.setTimeout(120000);

        const consoleErrors = [];
        const pageErrors = [];
        const cspViolations = [];

        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });
        page.on('pageerror', (err) => pageErrors.push(err.message));

        await page.addInitScript(() => {
            window.__cspViolations = [];
            document.addEventListener('securitypolicyviolation', (e) => {
                window.__cspViolations.push({
                    violatedDirective: e.violatedDirective,
                    blockedURI: e.blockedURI,
                });
            });
        });

        // 1. Verify Security Headers on /login and /api/v1/health
        const loginResponse = await page.goto('/login');
        expect(loginResponse.status()).toBe(200);
        const loginHeaders = loginResponse.headers();
        expect(loginHeaders['x-frame-options']).toBe('DENY');
        expect(loginHeaders['x-content-type-options']).toBe('nosniff');
        expect(loginHeaders['referrer-policy']).toBe('strict-origin-when-cross-origin');
        expect(loginHeaders['permissions-policy']).toContain('camera=()');
        expect(loginHeaders['content-security-policy']).toContain("default-src 'self'");
        expect(loginHeaders['content-security-policy']).toContain("frame-ancestors 'none'");

        const apiRes = await page.request.get('/api/v1/health');
        expect(apiRes.status()).toBe(200);
        expect(apiRes.headers()['x-frame-options']).toBe('DENY');
        expect(apiRes.headers()['x-content-type-options']).toBe('nosniff');

        // 2. Owner Live XSS Test on /organization/branches
        await loginAs(page, 'owner@demo.local');
        await page.goto('/organization/branches');
        await page.waitForLoadState('networkidle');

        const xssPayload = '<script>window.__xssFired=true</script>';
        await page.getByTestId('add-branch-btn').click();
        await page.getByTestId('branch-code-input').fill('SEC-XSS-1');
        await page.getByTestId('branch-name-input').fill(`Branch ${xssPayload}`);
        await page.getByTestId('branch-city-input').fill('Riyadh');
        await page.getByTestId('branch-save-btn').click();

        await expect(page.getByTestId('branch-row-SEC-XSS-1')).toBeVisible({ timeout: 15000 });
        await page.reload();
        await page.waitForLoadState('networkidle');
        await expect(page.getByTestId('branch-row-SEC-XSS-1')).toContainText(xssPayload);

        const xssExecuted = await page.evaluate(() => Boolean(window.__xssFired));
        expect(xssExecuted).toBe(false);

        await logout(page);

        // 3. Inventory Manager Boundary Check
        await loginAs(page, 'inventory@alnoor.local');
        await page.goto('/inventory');
        await expect(page).toHaveURL(/\/inventory/);

        for (const forbidden of ['/financials', '/reports', '/orders', '/customers', '/platform/tenants']) {
            const res = await page.request.get(forbidden);
            expect(res.status(), `Inventory manager should get 403 on ${forbidden}`).toBe(403);
        }

        await page.goto('/dashboard');
        await logout(page);

        // 4. Observer Boundary Check (strictly read-only: NO rule evaluation/creation, NO /commands)
        await loginAs(page, 'observer@alnoor.local');
        await page.goto('/alerts');
        await expect(page).toHaveURL(/\/alerts/);
        await expect(page.getByTestId('evaluate-rules-btn')).toHaveCount(0);
        await expect(page.getByTestId('tab-alerts-rules')).toHaveCount(0);

        await page.goto('/reports');
        await expect(page).toHaveURL(/\/reports/);
        await page.getByTestId('tab-reports-targets').click();
        await expect(page.getByTestId('create-target-btn')).toHaveCount(0);

        const cmdRes = await page.request.get('/commands');
        expect(cmdRes.status()).toBe(403);

        await page.goto('/dashboard');
        await logout(page);

        // 5. Branch 1 Manager Cross-Branch Report Download Blocked (403)
        await loginAs(page, 'branch1@alnoor.local');
        await page.goto('/reports');
        await expect(page).toHaveURL(/\/reports/);
        const branch2Res = await page.request.get('/reports/download?type=sales_summary&format=csv&branch_id=2');
        expect(branch2Res.status()).toBe(403);

        // Check zero CSP violations, console errors, and page errors
        await page.goto('/dashboard');
        await page.waitForLoadState('networkidle');
        const detectedCsp = await page.evaluate(() => window.__cspViolations || []);
        cspViolations.push(...detectedCsp);

        expect(cspViolations, `CSP violations detected: ${JSON.stringify(cspViolations)}`).toEqual([]);
        expect(consoleErrors, `Console errors: ${consoleErrors.join('; ')}`).toEqual([]);
        expect(pageErrors, `Page errors: ${pageErrors.join('; ')}`).toEqual([]);
    });
});
