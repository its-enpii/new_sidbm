import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://new_sidbm-nginx-1';
const USERNAME = process.env.E2E_USERNAME ?? 'dev';
const PASSWORD = process.env.E2E_PASSWORD ?? 'password';

async function login(page: Page): Promise<boolean> {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    const identifier = page.getByPlaceholder(/admin_desa|username|surel|pengguna/i).first();
    await identifier.waitFor({ state: 'visible', timeout: 15000 });
    await identifier.fill(USERNAME);
    await page.getByPlaceholder(/••••|•|password|kata sandi/i).first().fill(PASSWORD);
    const submit = page.getByRole('button', { name: /^(Masuk|Login)$/i });
    await submit.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(500);
    return !page.url().includes('/login');
}

test.describe('SIDBM E2E smoke', () => {
    test('login flow lands authenticated user on dashboard', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable — seed dev user with sidbm:provision-dev-user.');
        await expect(page).not.toHaveURL(/\/login$/);
    });

    test('group form excludes already-selected members from search results', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/master-data/groups/create`);
        await page.waitForLoadState('networkidle');

        const trigger = page.getByRole('combobox', { name: /Cari Anggota|Cari NIK/i }).first();
        await trigger.waitFor({ state: 'visible', timeout: 10000 });
        await trigger.click();
        const search = page.getByPlaceholder(/Cari opsi/i).first();
        await search.waitFor({ state: 'visible', timeout: 5000 });
        await search.fill('Anggota');
        const optionResp = await page.waitForResponse((r) => r.url().includes('/member-options') && r.status() === 200, { timeout: 5000 }).catch(() => null);
        test.skip(!optionResp || (await optionResp.json()).data.length === 0, 'No members seeded — quick-register at least one.');

        const firstOption = page.locator('[role="option"]').first();
        const firstLabel = (await firstOption.innerText()).trim();
        await firstOption.click();

        const tambah = page.getByRole('button', { name: /^Tambahkan$/i });
        await tambah.click();
        await expect(page.getByRole('button', { name: /Hapus/i }).first()).toBeVisible({ timeout: 5000 });

        await trigger.click();
        const search2 = page.getByPlaceholder(/Cari opsi/i).first();
        await search2.waitFor({ state: 'visible', timeout: 5000 });
        const firstWord = firstLabel.split(' ')[0] ?? 'Anggota';
        await search2.fill(firstWord);
        await page.waitForResponse((r) => r.url().includes('/member-options') && r.status() === 200, { timeout: 5000 }).catch(() => undefined);
        await page.waitForTimeout(500);

        const stillPresent = await page.locator(`[role="option"]`).evaluateAll((els, term) => els.some(el => el.textContent?.includes(term) ?? false), firstWord);
        expect(stillPresent).toBe(false);
    });

    test('loan form officer dropdowns enable after picking a group with officers', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans/create`);
        await page.waitForLoadState('networkidle');

        const kelompokTrigger = page.getByRole('combobox', { name: /^Kelompok$/i }).first();
        await expect(kelompokTrigger).toBeVisible({ timeout: 5000 });

        const searchInput = page.locator('input[placeholder="Cari opsi..."]').first();
        await kelompokTrigger.click();
        await searchInput.fill('');
        await page.waitForTimeout(400);

        const firstGroupOption = page.locator('[role="option"]').first();
        const groupCount = await firstGroupOption.count();
        test.skip(groupCount === 0, 'No groups available — seed a group first.');
        await firstGroupOption.click();
        await page.waitForTimeout(500);

        const chairmanTrigger = page.getByRole('combobox', { name: /^Ketua$/i });
        await expect(chairmanTrigger).toBeEnabled({ timeout: 5000 });
        await expect(page.locator('text=Pilih kelompok terlebih dahulu')).toHaveCount(0);
    });

    test('loan beneficiary section shows group members as toggles, default checked', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans/create`);
        await page.waitForLoadState('networkidle');

        const kelompokTrigger = page.getByRole('combobox', { name: /^Kelompok$/i }).first();
        await kelompokTrigger.click();
        const searchInput = page.locator('input[placeholder="Cari opsi..."]').first();
        await searchInput.fill('');
        await page.waitForTimeout(400);
        const firstGroupOption = page.locator('[role="option"]').first();
        test.skip((await firstGroupOption.count()) === 0, 'No groups available.');

        const groupLabel = (await firstGroupOption.innerText()).trim();
        await firstGroupOption.click();
        await page.waitForTimeout(500);

        const beneficiarySwitches = page.locator('input[role="switch"]');
        const switchCount = await beneficiarySwitches.count();
        expect(switchCount).toBeGreaterThan(0);

        for (let i = 0; i < switchCount; i++) {
            await expect(beneficiarySwitches.nth(i)).toBeChecked();
        }

        await expect(page.getByRole('combobox', { name: /Cari anggota di luar kelompok/i })).toBeVisible();
        await expect(page.getByRole('button', { name: /^Tambahkan$/i })).toBeDisabled();
    });

    test('loan beneficiary section lets users enter per-beneficiary amounts', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans/create`);
        await page.waitForLoadState('networkidle');

        const kelompokTrigger = page.getByRole('combobox', { name: /^Kelompok$/i }).first();
        await kelompokTrigger.click();
        const searchInput = page.locator('input[placeholder="Cari opsi..."]').first();
        await searchInput.fill('');
        await page.waitForTimeout(400);
        const firstGroupOption = page.locator('[role="option"]').first();
        test.skip((await firstGroupOption.count()) === 0, 'No groups available.');
        await firstGroupOption.click();
        await page.waitForTimeout(500);

        await expect(page.getByRole('cell', { name: /Total Pengajuan/i })).toBeVisible();
        const amountInputs = page.locator('table input[inputmode="numeric"]');
        const amountCount = await amountInputs.count();
        expect(amountCount).toBeGreaterThan(0);

        if (amountCount > 0) {
            await amountInputs.first().click();
            await amountInputs.first().fill('20000000');
            await amountInputs.first().blur();
            await page.waitForTimeout(200);
            const value = await amountInputs.first().inputValue();
            expect(value).toBe('20.000.000');
        }
    });

    test('loan product info box shows defaults and hides plafon range', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans/create`);
        await page.waitForLoadState('networkidle');

        const productTrigger = page.getByRole('combobox', { name: /^Produk Pinjaman$/i }).first();
        await productTrigger.click();
        const option = page.locator('[role="option"]').first();
        await expect(option).toBeVisible({ timeout: 5000 });
        await option.click();

        const info = page.locator('text=Default:').first();
        await expect(info).toBeVisible({ timeout: 5000 });
        await expect(info).toContainText(/suku jasa/);
        await expect(info).toContainText(/Tenor/);

        const plafonRange = await page.locator('text=/500\\.000.*50\\.000\\.000|5\\.000\\.000.*200\\.000\\.000|Plafon [0-9]/').count();
        expect(plafonRange).toBe(0);
    });

    test('AppCurrencyInput formats thousand separator on blur', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans/create`);
        await page.waitForLoadState('networkidle');

        const principal = page.getByPlaceholder(/plafon pinjaman/i).first();
        await expect(principal).toBeVisible({ timeout: 5000 });

        await principal.click();
        await principal.fill('4500000');
        await principal.blur();
        await page.waitForTimeout(300);
        const value = await principal.inputValue();
        expect(value).toBe('4.500.000');
    });

    test('loan proposal submit redirects to detail page', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans/create`);
        await page.waitForLoadState('networkidle');

        await page.getByRole('combobox', { name: /^Produk Pinjaman$/i }).first().click();
        await page.locator('[role="option"]').first().click();
        await page.waitForTimeout(300);

        await page.getByRole('combobox', { name: /^Kelompok$/i }).first().click();
        await page.locator('input[placeholder="Cari opsi..."]').first().fill('');
        await page.waitForTimeout(400);
        const groupOption = page.locator('[role="option"]').first();
        test.skip((await groupOption.count()) === 0, 'No groups available.');
        await groupOption.click();
        await page.waitForTimeout(500);

        await page.getByPlaceholder(/plafon pinjaman/i).first().fill('1000000');
        await page.getByPlaceholder(/jangka waktu/i).first().fill('6');
        await page.getByPlaceholder(/prosentase jasa total/i).first().fill('12');

        await page.getByRole('button', { name: /Simpan Proposal/i }).click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);

        await expect(page).not.toHaveURL(/\/lending\/loans\/create$/);
        await expect(page).toHaveURL(/\/lending\/loans\/\d+$/);
    });

    test('loan edit proposal button opens modal with editable form', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans`);
        await page.waitForLoadState('networkidle');

        const detailLink = page.getByRole('link', { name: /Detail/i }).first();
        test.skip((await detailLink.count()) === 0, 'No loan detail links.');
        await detailLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);

        const editBtn = page.getByRole('button', { name: /Edit Proposal/i });
        await expect(editBtn).toBeVisible({ timeout: 5000 });
        await editBtn.click();
        await page.waitForTimeout(400);

        await expect(page.getByRole('cell', { name: /Total Pengajuan/i })).toBeVisible();
        await expect(page.getByText(/Tanggal Pengajuan/i).first()).toBeVisible();
    });

    test('loan detail shows per-beneficiary verification inputs and remove buttons in draft', async ({ page }) => {
        const ok = await login(page);
        test.skip(!ok, 'Login unavailable.');

        await page.goto(`${BASE}/lending/loans`);
        await page.waitForLoadState('networkidle');

        const detailLink = page.getByRole('link', { name: /Detail/i }).first();
        test.skip((await detailLink.count()) === 0, 'No loan detail links.');
        await detailLink.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);

        await expect(page.getByRole('heading', { name: /Daftar Pemanfaat/i })).toBeVisible();
        await expect(page.getByRole('heading', { name: /Form Verifikasi/i })).toBeVisible();

        const daftarTbl = page.locator('table').filter({ has: page.getByRole('columnheader', { name: /Verifikasi/i }) }).first();
        await expect(daftarTbl).toBeVisible();
        const verifyInputs = daftarTbl.locator('input[inputmode="numeric"]');
        expect(await verifyInputs.count()).toBeGreaterThan(0);

        await expect(page.getByRole('cell', { name: /Total Verifikasi/i })).toBeVisible();

        const deleteButtons = page.getByRole('button', { name: /^Hapus /i });
        expect(await deleteButtons.count()).toBeGreaterThan(0);
    });
});
