import { test, expect } from '@playwright/test';
import { execFile, execFileSync, spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

function readEnvValue(envPath, key) {
    const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
    for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || trimmed.startsWith('#')) continue;
        const idx = trimmed.indexOf('=');
        if (idx === -1) continue;
        if (trimmed.slice(0, idx).trim() === key) {
            return trimmed.slice(idx + 1).trim().replace(/^["']|["']$/g, '');
        }
    }
    return '';
}

function queryMariaDb(sql) {
    const mariadbExe = 'C:\\rbd-runtime\\mariadb\\bin\\mariadb.exe';
    return execFileSync(mariadbExe, ['-h', '127.0.0.1', '-P', '3307', '-u', 'root', '-N', '-B', '-e', sql], {
        encoding: 'utf8',
    }).trim();
}

test.describe('Phase 07 Remote Command Center Live E2E (.NET 10 Agent + Ed25519 + Reverb + mock_pos_db)', () => {
    test('owner issues high-risk price update, approves it, .NET 10 agent verifies Ed25519 & updates mock_pos_db, Reverb updates /commands live', async ({ page }) => {
        test.setTimeout(120000);

        const consoleErrors = [];
        const pageErrors = [];
        page.on('console', (msg) => {
            if (msg.type() === 'error') consoleErrors.push(msg.text());
        });
        page.on('pageerror', (err) => pageErrors.push(err.message));

        const repoRoot = path.resolve(process.cwd(), '..');
        const agentExe = path.join(repoRoot, 'agent', 'src', 'RemoteSyncAgent', 'bin', 'Debug', 'net10.0-windows', 'RemoteSyncAgent.exe');
        expect(fs.existsSync(agentExe)).toBeTruthy();

        const envPath = path.join(process.cwd(), '.env');
        const readPassword = readEnvValue(envPath, 'MOCK_POS_DB_PASSWORD');
        const writeUser = readEnvValue(envPath, 'MOCK_POS_WRITE_USERNAME');
        const writePassword = readEnvValue(envPath, 'MOCK_POS_WRITE_PASSWORD');
        expect(readPassword).not.toBe('');
        expect(writeUser).toBe('mock_pos_writer');
        expect(writePassword).not.toBe('');

        const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'rbd-e2e-p07-agent-'));
        let agentProc = null;
        const agentLogs = [];

        try {
            // Ensure POS-PRN-001 (item_code = 1) starts at baseline 480.0000
            queryMariaDb('UPDATE mock_pos_db.tbl_items SET unit_price = 480.0000, updated_at = NOW() WHERE item_code = 1;');

            // 1. Login as tenant owner
            await page.goto('/login');
            await page.getByTestId('login-email').fill('owner@demo.local');
            await page.getByTestId('login-password').fill('Demo@Phase03!');
            await page.getByTestId('login-submit').click();
            await expect(page).toHaveURL(/\/dashboard/);

            // 2. Generate a fresh enrollment code in /integrations
            await page.getByTestId('nav-integrations').click();
            await expect(page).toHaveURL(/\/integrations/);
            await page.waitForLoadState('networkidle');

            await page.getByTestId('tab-enrollment').click();
            await page.getByTestId('new-device-name-input').fill('.NET 10 Phase07 Write Agent');
            await page.getByTestId('generate-enrollment-btn').click();
            await expect(page.getByTestId('generated-enrollment-code')).toContainText('RBD-');
            const enrollCode = (await page.getByTestId('generated-enrollment-code').innerText()).trim();

            // 3. Enroll .NET 10 agent with SEPARATE read-only and write-only MySQL credentials
            const mysqlReadConn = `Server=127.0.0.1;Port=3307;Database=mock_pos_db;Uid=mock_pos_reader;Pwd=${readPassword};`;
            const mysqlWriteConn = `Server=127.0.0.1;Port=3307;Database=mock_pos_db;Uid=${writeUser};Pwd=${writePassword};`;

            await execFileAsync(agentExe, [
                'enroll',
                '--api-url', 'http://127.0.0.1:8000',
                '--code', enrollCode,
                '--device-id', 'WIN10-E2E-P07-WRITE01',
                '--device-name', '.NET 10 Phase07 Write Agent',
                '--mysql-conn', mysqlReadConn,
                '--mysql-write-conn', mysqlWriteConn,
                '--data-dir', tempDir,
            ]);

            const dpapiFile = path.join(tempDir, 'agent.secrets.dpapi');
            expect(fs.existsSync(dpapiFile)).toBeTruthy();
            const dpapiContent = fs.readFileSync(dpapiFile, 'utf8');
            expect(dpapiContent).not.toContain(readPassword);
            expect(dpapiContent).not.toContain(writePassword);

            // 4. Start live agent and wait until initial baseline sync maps POS-PRN-001 cleanly
            agentProc = spawn(agentExe, ['run', '--data-dir', tempDir, '--poll-seconds', '2'], {
                stdio: ['ignore', 'pipe', 'pipe'],
            });
            agentProc.stdout.on('data', (d) => agentLogs.push(d.toString()));
            agentProc.stderr.on('data', (d) => agentLogs.push(d.toString()));

            await expect(page.getByTestId('sync-status-pill')).toContainText(/متصل مباشر|Live/, { timeout: 25000 });

            // 5. Navigate to /commands via sidebar link
            await page.getByTestId('nav-commands').click();
            await expect(page).toHaveURL(/\/commands/);
            await page.waitForLoadState('networkidle');
            await page.waitForTimeout(1000); // Allow Echo private channel subscription to complete

            // 6. Open Create Command Drawer and submit high-risk price update (+25%: 480.00 -> 600.00)
            await page.getByTestId('open-new-command-btn').click();
            await page.getByTestId('cmd-type-select').selectOption('product.price.update');

            const targetSelect = page.getByTestId('cmd-target-select');
            await expect(targetSelect).toContainText('POS-PRN-001', { timeout: 15000 });
            const printerOption = targetSelect.locator('option', { hasText: 'POS-PRN-001' }).first();
            const printerVal = await printerOption.getAttribute('value');
            expect(printerVal).toBeTruthy();
            await targetSelect.selectOption(printerVal);

            await page.getByTestId('cmd-new-price-input').fill('600.00');
            await page.getByTestId('cmd-reason-input').fill('Phase 07 live E2E Ed25519 price update');

            await expect(page.getByTestId('cmd-risk-preview')).toContainText('HIGH');
            await expect(page.getByTestId('cmd-risk-preview')).toContainText('480.00');

            await page.getByTestId('cmd-submit-btn').click();

            // 7. Locate newly created command row (pending status) and extract its command ID
            const firstApproveBtn = page.locator('[data-testid^="approve-command-btn-"]').first();
            await expect(firstApproveBtn).toBeVisible({ timeout: 15000 });
            const testIdAttr = await firstApproveBtn.getAttribute('data-testid');
            const cmdId = testIdAttr.replace('approve-command-btn-', '');

            await expect(page.getByTestId(`command-status-${cmdId}`)).toContainText('pending');

            // 8. Approve the high-risk command and DO NOT reload the page!
            await firstApproveBtn.click();

            // 9. Wait for .NET 10 agent to pull signed envelope, verify Ed25519, update mock_pos_db, ack & Reverb update UI to succeeded
            await expect(page.getByTestId(`command-status-${cmdId}`)).toContainText('succeeded', { timeout: 25000 });

            // 10. Open detail drawer to inspect result & attempts, then close it
            await page.getByTestId(`view-command-btn-${cmdId}`).click();
            await expect(page.getByText('600.0000').first()).toBeVisible();
            await expect(page.getByText(/Attempt #1: succeeded/)).toBeVisible();
            await page.getByRole('dialog').getByRole('button', { name: 'Close' }).click();
            await expect(page.getByRole('dialog')).toBeHidden();

            // 11. Verify mock_pos_db.tbl_items directly via mariadb.exe
            const dbPrice = queryMariaDb('SELECT unit_price FROM mock_pos_db.tbl_items WHERE item_code = 1;');
            expect(dbPrice).toBe('600.00');

            // 12. Navigate to /products and confirm reconciled canonical catalog shows 600.00
            await page.getByTestId('nav-products').click();
            await expect(page).toHaveURL(/\/products/);
            await page.waitForLoadState('networkidle');
            await page.getByTestId('products-search').fill('POS-PRN-001');
            await expect(page.locator('table')).toContainText('POS-PRN-001', { timeout: 15000 });
            await expect(page.locator('table')).toContainText('600.00');

            expect(consoleErrors, `Console errors: ${consoleErrors.join('; ')}`).toEqual([]);
            expect(pageErrors, `Page errors: ${pageErrors.join('; ')}`).toEqual([]);
        } catch (err) {
            console.error('--- Phase 07 Agent Logs ---\n' + agentLogs.join(''));
            throw err;
        } finally {
            if (agentProc && !agentProc.killed) {
                agentProc.kill('SIGKILL');
            }
            fs.rmSync(tempDir, { recursive: true, force: true });
            try {
                queryMariaDb('UPDATE mock_pos_db.tbl_items SET unit_price = 480.0000, updated_at = NOW() WHERE item_code = 1;');
            } catch {
                // ignore cleanup failure
            }
        }
    });
});
