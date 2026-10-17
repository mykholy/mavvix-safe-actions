const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

(async () => {
    const base = 'http://127.0.0.1:8181';
    const output = process.env.SAFE_ACTIONS_SCREENSHOTS || path.join(process.cwd(), 'artifacts');
    fs.mkdirSync(output, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1280, height: 850 } });
    const failures = [];

    page.on('pageerror', error => failures.push(error.message));

    try {
        const response = await page.goto(base + '/safe-actions-e2e', { waitUntil: 'networkidle' });

        if (!response || response.status() !== 200) {
            throw new Error('Demo page failed to load: ' + (response && response.status()));
        }

        await page.getByText('Pending One', { exact: true }).waitFor({ timeout: 15000 });

        const row = page.locator('tbody tr').filter({ hasText: 'Pending One' });
        await row.locator('input[type="checkbox"]').check({ force: true });
        await page.getByRole('button', { name: /Archive selected/i }).click();

        await page.getByText('Records to change').waitFor({ timeout: 15000 });
        await page.screenshot({ path: path.join(output, 'desktop-confirmation.png'), fullPage: true });

        await page.getByRole('button', { name: /Cancel/i }).last().click();
        const afterCancel = await (await page.request.get(base + '/safe-actions-e2e-state')).json();

        if (afterCancel.rows[0].status !== 'pending' || afterCancel.consumed !== 0) {
            throw new Error('Cancelling the modal changed a row or consumed a ticket.');
        }

        await page.getByRole('button', { name: /Archive selected/i }).click();
        await page.getByRole('button', { name: /Confirm changes/i }).click();
        await page.waitForTimeout(500);

        const afterConfirm = await (await page.request.get(base + '/safe-actions-e2e-state')).json();

        if (afterConfirm.rows[0].status !== 'archived' || afterConfirm.rows[3].status !== 'pending' || afterConfirm.consumed !== 1) {
            throw new Error('Confirmation did not archive the exact scoped row once.');
        }

        await page.screenshot({ path: path.join(output, 'desktop-after-confirmation.png'), fullPage: true });

        await page.goto(base + '/safe-actions-e2e?lang=ar', { waitUntil: 'networkidle' });
        const secondRow = page.locator('tbody tr').filter({ hasText: 'Pending Two' });
        await secondRow.locator('input[type="checkbox"]').check({ force: true });
        await page.getByRole('button', { name: /Archive selected/i }).click();
        await page.getByText('تأكيد التغييرات').waitFor({ timeout: 15000 });
        await page.screenshot({ path: path.join(output, 'arabic-confirmation.png'), fullPage: true });

        await page.setViewportSize({ width: 390, height: 844 });
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.screenshot({ path: path.join(output, 'mobile-dark-confirmation.png'), fullPage: true });

        if (failures.length) {
            throw new Error('Browser JavaScript errors: ' + JSON.stringify(failures));
        }

        console.log('PASS: real browser renders Filament table, previews, cancels, confirms scoped records and renders Arabic/mobile views.');
    } catch (error) {
        await page.screenshot({ path: path.join(output, 'failure.png'), fullPage: true }).catch(() => {});
        console.error(error);
        process.exitCode = 1;
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
