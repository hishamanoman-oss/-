import { test, expect } from '@playwright/test';
import { execFile, execFileSync, spawn } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
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

function runMariaDbSql(sql) {
    const mariadbExe = 'C:\\rbd-runtime\\mariadb\\bin\\mariadb.exe';
    execFileSync(mariadbExe, ['-h', '127.0.0.1', '-P', '3307', '-u', 'root', '-e', sql], {
        stdio: 'pipe',
    });
}

test.describe('Phase 05B .NET 10 Sync Agent + Reverb Real-Time Browser Verification', () => {
    test('live mock_pos_db invoice appears automatically on open /orders page via Reverb and survives offline outage restart', async ({ page }) => {
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
        const mockPosPassword = readEnvValue(envPath, 'MOCK_POS_DB_PASSWORD');
        expect(mockPosPassword).not.toBe('');

        const tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'rbd-e2e-agent-'));
        let agentProc = null;
        const agentLogs = [];

        // Controllable local HTTP proxy to simulate API outage cleanly without stopping port 8000 for Playwright
        let proxyOnline = true;
        const proxyServer = http.createServer((req, res) => {
            if (!proxyOnline) {
                res.statusCode = 503;
                res.setHeader('Content-Type', 'application/json');
                res.end(JSON.stringify({ message: 'Simulated API Outage' }));
                return;
            }

            const chunks = [];
            req.on('data', (c) => chunks.push(c));
            req.on('end', () => {
                const body = Buffer.concat(chunks);
                const forwardReq = http.request({
                    hostname: '127.0.0.1',
                    port: 8000,
                    path: req.url,
                    method: req.method,
                    headers: { ...req.headers, host: '127.0.0.1:8000' },
                }, (forwardRes) => {
                    res.writeHead(forwardRes.statusCode || 500, forwardRes.headers);
                    forwardRes.pipe(res);
                });
                forwardReq.on('error', (err) => {
                    res.statusCode = 502;
                    res.end(err.message);
                });
                if (body.length > 0) forwardReq.write(body);
                forwardReq.end();
            });
        });

        await new Promise((resolve) => proxyServer.listen(0, '127.0.0.1', resolve));
        const proxyPort = proxyServer.address().port;
        const proxyUrl = `http://127.0.0.1:${proxyPort}`;

        const startAgent = () => {
            const proc = spawn(agentExe, ['run', '--data-dir', tempDir, '--poll-seconds', '2'], {
                stdio: ['ignore', 'pipe', 'pipe'],
            });
            proc.stdout.on('data', (d) => agentLogs.push(d.toString()));
            proc.stderr.on('data', (d) => agentLogs.push(d.toString()));
            return proc;
        };

        try {
            // Clean up any leftover test rows from prior interrupted runs
            runMariaDbSql(`
                DELETE FROM mock_pos_db.tbl_invoice_lines WHERE inv_id IN (
                    SELECT inv_id FROM mock_pos_db.tbl_invoices WHERE inv_number IN ('POS-LIVE-E2E-9001', 'POS-LIVE-OFFLINE-9002')
                );
                DELETE FROM mock_pos_db.tbl_invoices WHERE inv_number IN ('POS-LIVE-E2E-9001', 'POS-LIVE-OFFLINE-9002');
            `);

            // 1. Login and generate a fresh one-time enrollment code in /integrations
            await page.goto('/login');
            await page.getByTestId('login-email').fill('owner@demo.local');
            await page.getByTestId('login-password').fill('Demo@Phase03!');
            await page.getByTestId('login-submit').click();
            await expect(page).toHaveURL(/\/dashboard/);

            await page.getByTestId('nav-integrations').click();
            await expect(page).toHaveURL(/\/integrations/);
            await page.waitForLoadState('networkidle');

            await page.getByTestId('tab-enrollment').click();
            await page.getByTestId('new-device-name-input').fill('.NET 10 Live E2E Agent');
            await page.getByTestId('generate-enrollment-btn').click();
            await expect(page.getByTestId('generated-enrollment-code')).toContainText('RBD-');
            const enrollCode = (await page.getByTestId('generated-enrollment-code').innerText()).trim();

            // 2. Enroll RemoteSyncAgent via CLI asynchronously so Node's proxy event loop stays responsive
            const mysqlConn = `Server=127.0.0.1;Port=3307;Database=mock_pos_db;Uid=mock_pos_reader;Pwd=${mockPosPassword};`;
            await execFileAsync(agentExe, [
                'enroll',
                '--api-url', proxyUrl,
                '--code', enrollCode,
                '--device-id', 'WIN10-E2E-LIVE01',
                '--device-name', '.NET 10 Live E2E Agent',
                '--mysql-conn', mysqlConn,
                '--data-dir', tempDir,
            ]);

            const dpapiFile = path.join(tempDir, 'agent.secrets.dpapi');
            expect(fs.existsSync(dpapiFile)).toBeTruthy();
            expect(fs.readFileSync(dpapiFile, 'utf8')).not.toContain(mockPosPassword);

            // 3. Open /orders and keep it open WITHOUT manual reload!
            await page.goto('/orders');
            await page.waitForLoadState('networkidle');

            // Give Echo a brief moment to complete private channel auth over Reverb
            await page.waitForTimeout(1000);

            // 4. Start the live .NET 10 Sync Agent; wait for initial baseline sync & Live pill via Reverb
            agentProc = startAgent();
            await expect(page.getByTestId('sync-status-pill')).toContainText(/متصل مباشر|Live/, { timeout: 25000 });
            await expect(page.getByTestId('order-row-POS-2026-0028')).toBeVisible({ timeout: 20000 });

            // 5. Insert a brand-new live invoice POS-LIVE-E2E-9001 directly into mock_pos_db while /orders is open!
            runMariaDbSql(`
                START TRANSACTION;
                INSERT INTO mock_pos_db.tbl_invoices
                    (inv_number, br_id, client_no, inv_status, gross_amt, disc_amt, tax_amt, net_amt, paid_amt, currency_code, created_at, updated_at)
                VALUES
                    ('POS-LIVE-E2E-9001', 1, 1, 'completed', 450.00, 0.00, 67.50, 517.50, 517.50, 'SAR', NOW(), NOW());
                SET @new_inv_id = LAST_INSERT_ID();
                INSERT INTO mock_pos_db.tbl_invoice_lines
                    (inv_id, item_code, qty, unit_price, line_total)
                VALUES
                    (@new_inv_id, 1, 1.00, 450.00, 450.00);
                COMMIT;
            `);

            // 6. Verify it automatically appears in open /orders WITHOUT manual reload!
            await expect(page.getByTestId('order-row-POS-LIVE-E2E-9001')).toBeVisible({ timeout: 25000 });
            await expect(page.getByTestId('order-row-POS-LIVE-E2E-9001')).toHaveCount(1);

            // 7. Offline-outage recovery test: turn proxy offline, insert POS-LIVE-OFFLINE-9002, restart agent, reconnect
            proxyOnline = false;
            runMariaDbSql(`
                START TRANSACTION;
                INSERT INTO mock_pos_db.tbl_invoices
                    (inv_number, br_id, client_no, inv_status, gross_amt, disc_amt, tax_amt, net_amt, paid_amt, currency_code, created_at, updated_at)
                VALUES
                    ('POS-LIVE-OFFLINE-9002', 1, 1, 'completed', 300.00, 0.00, 45.00, 345.00, 345.00, 'SAR', DATE_ADD(NOW(), INTERVAL 2 SECOND), DATE_ADD(NOW(), INTERVAL 2 SECOND));
                SET @off_inv_id = LAST_INSERT_ID();
                INSERT INTO mock_pos_db.tbl_invoice_lines
                    (inv_id, item_code, qty, unit_price, line_total)
                VALUES
                    (@off_inv_id, 1, 1.00, 300.00, 300.00);
                COMMIT;
            `);

            // Wait so agent captures POS-LIVE-OFFLINE-9002 into SQLite outbox and logs 503 backoff
            await page.waitForTimeout(4000);
            await expect(page.getByTestId('order-row-POS-LIVE-OFFLINE-9002')).toHaveCount(0);

            // Restart agent process across outage recovery and bring API online
            if (agentProc) {
                agentProc.kill();
                agentProc = null;
            }
            proxyOnline = true;
            agentProc = startAgent();

            // Verify queued SQLite outbox flushes cleanly after restart & renders automatically with zero duplicates
            await expect(page.getByTestId('order-row-POS-LIVE-OFFLINE-9002')).toBeVisible({ timeout: 25000 });
            await expect(page.getByTestId('order-row-POS-LIVE-OFFLINE-9002')).toHaveCount(1);
            await expect(page.getByTestId('order-row-POS-LIVE-E2E-9001')).toHaveCount(1);

            expect(agentLogs.join('\n')).not.toContain(mockPosPassword);
            expect(consoleErrors, `Console errors: ${consoleErrors.join('; ')}`).toEqual([]);
            expect(pageErrors, `Page errors: ${pageErrors.join('; ')}`).toEqual([]);
        } finally {
            if (agentProc) {
                agentProc.kill();
            }
            proxyServer.close();
            try {
                runMariaDbSql(`
                    DELETE FROM mock_pos_db.tbl_invoice_lines WHERE inv_id IN (
                        SELECT inv_id FROM mock_pos_db.tbl_invoices WHERE inv_number IN ('POS-LIVE-E2E-9001', 'POS-LIVE-OFFLINE-9002')
                    );
                    DELETE FROM mock_pos_db.tbl_invoices WHERE inv_number IN ('POS-LIVE-E2E-9001', 'POS-LIVE-OFFLINE-9002');
                `);
            } catch {
                // Best effort cleanup
            }
            fs.rmSync(tempDir, { recursive: true, force: true });
        }
    });
});
