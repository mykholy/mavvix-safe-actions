const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;

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

        const desktopAudit = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
            .analyze();
        fs.writeFileSync(
            path.join(output, 'accessibility-desktop.json'),
            JSON.stringify({ violations: desktopAudit.violations, incomplete: desktopAudit.incomplete }, null, 2),
        );
        const desktopBlockers = desktopAudit.violations.filter(issue =>
            ['critical', 'serious'].includes(issue.impact)
        );
        if (desktopBlockers.length > 0) {
            throw new Error('Desktop accessibility violations: ' +
                desktopBlockers.map(issue => issue.id + ' (' + issue.impact + ')').join(', '));
        }


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

        const mobileAudit = await new AxeBuilder({ page })
            .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
            .analyze();
        fs.writeFileSync(
            path.join(output, 'accessibility-mobile-dark-ar.json'),
            JSON.stringify({ violations: mobileAudit.violations, incomplete: mobileAudit.incomplete }, null, 2),
        );
        const mobileBlockers = mobileAudit.violations.filter(issue =>
            ['critical', 'serious'].includes(issue.impact)
        );
        if (mobileBlockers.length > 0) {
            throw new Error('Mobile/Arabic accessibility violations: ' +
                mobileBlockers.map(issue => issue.id + ' (' + issue.impact + ')').join(', '));
        }


        const expiryResponse = await page.evaluate(async () => {
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const response = await fetch('/safe-actions-e2e-expire', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            });

            return { status: response.status, data: await response.json() };
        });

        if (expiryResponse.status !== 200 || expiryResponse.data.expired < 1) {
            throw new Error('Could not expire the pending ticket in the isolated test host.');
        }

        await page.getByRole('button', { name: /تأكيد التغييرات/ }).click();
        await page.getByText('لم يتم تنفيذ العملية').waitFor({ timeout: 15000 });

        const expiredState = await (await page.request.get(base + '/safe-actions-e2e-state')).json();

        if (expiredState.rows[1].status !== 'pending' || expiredState.consumed !== 1) {
            throw new Error('Expired confirmation altered records or consumed an invalid ticket.');
        }

        await page.screenshot({ path: path.join(output, 'arabic-expired-ticket.png'), fullPage: true });

        await page.setViewportSize({ width: 1280, height: 850 });
        await page.goto(base + '/safe-actions-e2e', { waitUntil: 'networkidle' });
        const forbiddenRow = page.locator('tbody tr').filter({ hasText: 'Forbidden Row' });
        await forbiddenRow.locator('input[type="checkbox"]').check({ force: true });
        await page.getByRole('button', { name: /Archive selected/i }).click();
        await page.getByText('Unable to preview the selected records').waitFor({ timeout: 15000 });

        const deniedState = await (await page.request.get(base + '/safe-actions-e2e-state')).json();

        if (deniedState.rows[2].status !== 'forbidden' || deniedState.tickets !== expiredState.tickets) {
            throw new Error('Unauthorized preview mutated a row or created a ticket.');
        }

        await page.screenshot({ path: path.join(output, 'unauthorized-selection.png'), fullPage: true });

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
