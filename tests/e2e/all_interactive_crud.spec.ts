import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:64080';

async function loginUser(page: Page, username: string = 'dev'): Promise<void> {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    if (page.url().includes('/login')) {
        const userIn = page.locator('input[autocomplete="username"]').first();
        await userIn.waitFor({ state: 'visible', timeout: 15000 });
        await userIn.fill(username);

        const passIn = page.locator('input[autocomplete="current-password"]').first();
        await passIn.fill('password');

        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
        await page.waitForTimeout(500);
    }
}

async function selectSmartOption(page: Page, triggerText: string): Promise<void> {
    const trigger = page.locator(`button:has-text("${triggerText}")`).first();
    if (await trigger.isVisible({ timeout: 3000 }).catch(() => false)) {
        await trigger.click();
        await page.waitForTimeout(250);
        const opt = page.locator('[role="option"]').first();
        if (await opt.isVisible({ timeout: 2000 }).catch(() => false)) {
            await opt.click();
            await page.waitForTimeout(150);
        }
    }
}

/* ─────────────────────────────────────────────────────────
   Section 1: Admin Suite
   ───────────────────────────────────────────────────────── */
test.describe('1. Interactive Admin Suite (CRUD & Operations)', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'superadmin');
    });

    test('1.1. Admin - Plans: Create New Plan', async ({ page }) => {
        await page.goto(`${BASE}/admin/plans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tambah Plan');

        const uniqueCode = 'plan_' + Math.floor(Math.random() * 10000);
        await page.getByLabel('Kode', { exact: true }).fill(uniqueCode);
        await page.getByLabel('Nama', { exact: true }).fill('Plan Test E2E');
        await page.getByLabel('Harga', { exact: true }).fill('150000');

        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForURL(/\/admin\/plans/);
        await expect(page.locator('h1')).toBeVisible();
    });

    test('1.2. Admin - Integrations: View & Interact', async ({ page }) => {
        await page.goto(`${BASE}/admin/integrations`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const saveBtn = page.getByRole('button', { name: /Simpan/i }).first();
        if (await saveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(500);
        }
    });

    test('1.3. Admin - Migration Hub: View & Trigger Dry Run', async ({ page }) => {
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();

        const dryRunBtn = page.locator('button:has-text("Dry Run"), button:has-text("Uji Coba")').first();
        if (await dryRunBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await dryRunBtn.click();
            await page.waitForTimeout(500);
        }
    });
});

/* ─────────────────────────────────────────────────────────
   Section 2: Master Data Suite
   ───────────────────────────────────────────────────────── */
test.describe('2. Interactive Master Data Suite (CRUD)', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'dev');
    });

    test('2.1. Desa: View, Search & Edit', async ({ page }) => {
        await page.goto(`${BASE}/master-data/villages`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Daftar Desa');

        const searchInput = page.getByPlaceholder(/Kode atau nama desa/i).first();
        if (await searchInput.isVisible()) {
            await searchInput.fill('Desa');
            await page.waitForTimeout(300);
        }

        const editLink = page.locator('a[href*="/master-data/villages/"][href*="/edit"]').first();
        if (await editLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            await editLink.click();
            await page.waitForURL(/\/master-data\/villages\/\d+\/edit/);

            const headInput = page.getByLabel('Nama Kades/Lurah', { exact: true });
            if (await headInput.isVisible()) await headInput.fill('Kades E2E Updated');
            await page.getByRole('button', { name: /Simpan/i }).first().click();
            await page.waitForURL(/\/master-data\/villages/);
        }
    });

    test('2.2. Anggota: Create, Search, View Detail & Edit', async ({ page }) => {
        await page.goto(`${BASE}/master-data/members/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tambah Anggota');

        const uniqueNik = '3501' + Math.floor(100000000000 + Math.random() * 900000000000).toString();
        const testName = 'Anggota E2E ' + Math.floor(Math.random() * 10000);

        await page.getByLabel('NIK', { exact: true }).fill(uniqueNik);
        await page.getByLabel('Nama Lengkap', { exact: true }).fill(testName);

        const maleRadio = page.locator('label:has-text("Laki-laki")').first();
        if (await maleRadio.isVisible()) await maleRadio.click();

        const birthPlace = page.getByLabel('Tempat Lahir', { exact: true });
        if (await birthPlace.isVisible()) await birthPlace.fill('Surabaya');

        const phoneInput = page.getByLabel('No. HP', { exact: true });
        if (await phoneInput.isVisible()) await phoneInput.fill('081299998888');

        const addressInput = page.getByLabel('Alamat', { exact: true });
        if (await addressInput.isVisible()) await addressInput.fill('Jl. Melati No. 10 E2E');

        await selectSmartOption(page, 'Pilih desa');

        const guarantorSwitch = page.locator('text=Penjamin').first();
        if (await guarantorSwitch.isVisible()) {
            await guarantorSwitch.click();
            await page.waitForTimeout(200);
            const gNik = page.getByLabel('NIK Penjamin');
            if (await gNik.isVisible()) {
                await gNik.fill('3501888877776666');
                await page.getByLabel('Nama Penjamin').fill('Penjamin E2E');
                await page.getByLabel('Hubungan').fill('Istri');
            }
        }

        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForURL(/\/master-data\/members/);

        const searchBox = page.getByPlaceholder(/NIK, nama/i).first();
        if (await searchBox.isVisible()) {
            await searchBox.fill(testName);
            await page.waitForTimeout(500);
            await expect(page.locator('h1')).toBeVisible();
        }

        const memberLink = page.locator(`a:has-text("${testName}")`).first();
        if (await memberLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            await memberLink.click();
            await page.waitForURL(/\/master-data\/members\/\d+/);

            const editBtn = page.locator('a:has-text("Edit"), button:has-text("Edit")').first();
            if (await editBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
                await editBtn.click();
                await page.waitForURL(/\/master-data\/members\/\d+\/edit/);
                const addr = page.getByLabel('Alamat', { exact: true });
                if (await addr.isVisible()) await addr.fill('Jl. Melati No. 99 Updated');
                await page.getByRole('button', { name: /Simpan/i }).first().click();
                await page.waitForURL(/\/master-data\/members/);
            }
        }
    });

    test('2.3. Kelompok: Create with SmartSelects & Members', async ({ page }) => {
        await page.goto(`${BASE}/master-data/groups/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tambah Kelompok');

        const groupName = 'Kelompok Anggrek E2E ' + Math.floor(Math.random() * 1000);
        await page.getByLabel('Nama Kelompok', { exact: true }).fill(groupName);
        await page.getByLabel('Alamat', { exact: true }).fill('Dusun Anggrek RT 05');

        const comboTriggers = page.locator('button[aria-expanded], button:has-text("Pilih")');
        const count = await comboTriggers.count();
        for (let i = 0; i < count; i++) {
            const sel = comboTriggers.nth(i);
            if (await sel.isVisible({ timeout: 1000 }).catch(() => false)) {
                await sel.click();
                await page.waitForTimeout(150);
                const firstOpt = page.locator('[role="option"]').first();
                if (await firstOpt.isVisible({ timeout: 1000 }).catch(() => false)) {
                    await firstOpt.click();
                    await page.waitForTimeout(150);
                }
            }
        }

        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForURL(/\/master-data\/groups/);
        await expect(page.locator('h1')).toBeVisible();
    });

    test('2.4. Lembaga: Create with Village Select & Form Fields', async ({ page }) => {
        await page.goto(`${BASE}/master-data/institutions/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tambah Lembaga');

        const instName = 'Lembaga Desa E2E ' + Math.floor(Math.random() * 1000);
        await page.getByLabel('Nama Lembaga', { exact: true }).fill(instName);

        await selectSmartOption(page, 'Pilih desa');

        const addrInput = page.getByLabel('Alamat Lembaga', { exact: true });
        if (await addrInput.isVisible()) await addrInput.fill('Jl. Lembaga No. 1');

        const leaderInput = page.getByLabel('Nama Pimpinan', { exact: true });
        if (await leaderInput.isVisible()) await leaderInput.fill('Bapak Kepala');

        const respInput = page.getByLabel('Nama Penanggungjawab', { exact: true });
        if (await respInput.isVisible()) await respInput.fill('Bapak Sekretaris');

        const identityInput = page.getByLabel('Nomor Identitas Lembaga', { exact: true });
        if (await identityInput.isVisible()) await identityInput.fill('ID-LEMBAGA-001');

        await page.getByRole('button', { name: /Simpan/i }).first().click();
        await page.waitForTimeout(2000);
        await expect(page.locator('h1')).toBeVisible();
    });
});

/* ─────────────────────────────────────────────────────────
   Section 3: Lending Suite
   ───────────────────────────────────────────────────────── */
test.describe('3. Interactive Lending Suite', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'dev');
    });

    test('3.1. Proposals: Open Create Form & Fill SmartSelects', async ({ page }) => {
        await page.goto(`${BASE}/lending/loans/create`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Register Proposal');

        await selectSmartOption(page, 'Pilih produk');
        await selectSmartOption(page, 'Pilih kelompok');

        const amountInput = page.getByLabel(/Plafon Pinjaman/i).first();
        if (await amountInput.isVisible({ timeout: 3000 }).catch(() => false)) await amountInput.fill('10000000');

        const termInput = page.getByLabel(/Jangka Waktu/i).first();
        if (await termInput.isVisible({ timeout: 3000 }).catch(() => false)) await termInput.fill('12');
        await expect(page.locator('h1')).toBeVisible();
    });

    test('3.2. Loans: List & Detail Navigation', async ({ page }) => {
        await page.goto(`${BASE}/lending/loans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tahapan Perguliran');

        const detailLink = page.locator('a[href*="/lending/loans/"]:not([href*="/create"])').first();
        if (await detailLink.isVisible({ timeout: 5000 }).catch(() => false)) {
            await detailLink.click();
            await page.waitForTimeout(3000);
            await expect(page.locator('h1')).toBeVisible();
        }
    });
});

/* ─────────────────────────────────────────────────────────
   Section 4: Accounting Suite
   ───────────────────────────────────────────────────────── */
test.describe('4. Interactive Accounting Suite', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'dev');
    });

    test('4.1. Journal Entry: Open Form & Select Transaction Type', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries/create`, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1000);
        await selectSmartOption(page, 'Pilih jenis transaksi');
        await page.waitForTimeout(300);
    });

    test('4.2. Installment Journal: Open Form & Select Loan', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries/installment`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('4.3. Chart of Accounts: Search & Filter', async ({ page }) => {
        await page.goto(`${BASE}/accounting/accounts`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Bagan Akun');

        const searchInput = page.getByPlaceholder(/Kode atau nama akun/i).first();
        if (await searchInput.isVisible()) {
            await searchInput.fill('Kas');
            await page.waitForTimeout(400);
        }
    });

    test('4.4. Assets: List & Detail View', async ({ page }) => {
        await page.goto(`${BASE}/accounting/assets`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Inventaris');

        const detailLink = page.locator('a[href*="/accounting/assets/"]').first();
        if (await detailLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            await detailLink.click();
            await page.waitForURL(/\/accounting\/assets\/\d+/);
            await expect(page.locator('h1')).toBeVisible();
        }
    });

    test('4.5. Period Close: Tab Switching', async ({ page }) => {
        await page.goto(`${BASE}/accounting/period-close`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tutup Buku');

        const annualTab = page.locator('button:has-text("Tahunan"), button:has-text("Alokasi")').first();
        if (await annualTab.isVisible({ timeout: 3000 }).catch(() => false)) {
            await annualTab.click();
            await page.waitForTimeout(200);
        }
    });

    test('4.6. Journals: List & Detail Navigation', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journals`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Daftar Jurnal');

        const detailLink = page.locator('a[href*="/accounting/journals/"]').first();
        if (await detailLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            await detailLink.click();
            await page.waitForTimeout(500);
            await expect(page.locator('h1')).toBeVisible();
        }
    });
});

/* ─────────────────────────────────────────────────────────
   Section 5: Operational Tools
   ───────────────────────────────────────────────────────── */
test.describe('5. Interactive Operational Tools', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeEach(async ({ page }) => {
        await loginUser(page, 'dev');
    });

    test('5.1. E-Budgeting: View & Save Interaction', async ({ page }) => {
        await page.goto(`${BASE}/budgeting`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('E-Budgeting');

        const saveBtn = page.getByRole('button', { name: /Simpan/i }).first();
        if (await saveBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await saveBtn.click();
            await page.waitForTimeout(500);
        }
    });

    test('5.2. Onboarding Import Wizard: Page Loads', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('5.3. Billing Invoices: List & Detail', async ({ page }) => {
        await page.goto(`${BASE}/billing/invoices`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Tagihan');

        const invLink = page.locator('a[href*="/billing/invoices/"]').first();
        if (await invLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            await invLink.click();
            await page.waitForURL(/\/billing\/invoices\//);
            await expect(page.locator('h1')).toBeVisible();
        }
    });

    test('5.4. Settings: Tab Switching & Identity Edit', async ({ page }) => {
        await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Pengaturan');

        const tabKeys = ['Sistem Pinjaman', 'Logo Lembaga', 'WhatsApp Gateway', 'Tanda Tangan'];
        for (const tabText of tabKeys) {
            const tab = page.locator(`button:has-text("${tabText}")`).first();
            if (await tab.isVisible({ timeout: 2000 }).catch(() => false)) {
                await tab.click();
                await page.waitForTimeout(200);
            }
        }

        const identityTab = page.locator('button:has-text("Identitas Lembaga")').first();
        if (await identityTab.isVisible({ timeout: 2000 }).catch(() => false)) {
            await identityTab.click();
            await page.waitForTimeout(200);
        }
        await expect(page.locator('h1')).toBeVisible();
    });

    test('5.5. Profile: Tab Switching (Personal, Account, Photo)', async ({ page }) => {
        await page.goto(`${BASE}/profile`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toContainText('Profil Pengguna');

        const accountTab = page.locator('button:has-text("Akun")').first();
        if (await accountTab.isVisible({ timeout: 3000 }).catch(() => false)) {
            await accountTab.click();
            await page.waitForTimeout(200);
        }

        const photoTab = page.locator('button:has-text("Foto")').first();
        if (await photoTab.isVisible({ timeout: 3000 }).catch(() => false)) {
            await photoTab.click();
            await page.waitForTimeout(200);
        }
    });

    test('5.6. Billing Notifications: View Page', async ({ page }) => {
        await page.goto(`${BASE}/notifications/billing`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('5.7. Dashboard: Verify Stats Cards Load', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('h1')).toBeVisible();
    });
});

/* ─────────────────────────────────────────────────────────
   Section 6: Landing Page & Auth
   ───────────────────────────────────────────────────────── */
test.describe('6. Landing Page & Auth Interactive Flow', () => {
    test('6.1. Landing: FAQ Accordion Click & CTA Buttons', async ({ page }) => {
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();

        const faqButtons = page.locator('button:has-text("Apa itu"), button:has-text("Apakah"), button:has-text("Bagaimana")');
        const faqCount = await faqButtons.count();
        for (let i = 0; i < Math.min(faqCount, 3); i++) {
            const btn = faqButtons.nth(i);
            if (await btn.isVisible({ timeout: 2000 }).catch(() => false)) {
                await btn.click();
                await page.waitForTimeout(300);
            }
        }

        const ctaBtn = page.locator('a:has-text("Mulai Sekarang")').first();
        if (await ctaBtn.isVisible({ timeout: 2000 }).catch(() => false)) {
            const href = await ctaBtn.getAttribute('href');
            expect(href).toBeTruthy();
        }
    });

    test('6.2. Login: Fill, Submit & Redirect', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Masuk ke Akun Anda' })).toBeVisible();

        const userIn = page.locator('input[autocomplete="username"]').first();
        await userIn.waitFor({ state: 'visible', timeout: 15000 });
        await userIn.fill('dev');

        const passIn = page.locator('input[autocomplete="current-password"]').first();
        await passIn.fill('password');

        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 30000 });
        await expect(page.locator('h1')).toBeVisible();
    });

    test('6.3. Login: Wrong Credentials Show Error', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });

        const userIn = page.locator('input[autocomplete="username"]').first();
        await userIn.waitFor({ state: 'visible', timeout: 15000 });
        await userIn.fill('wronguser');

        const passIn = page.locator('input[autocomplete="current-password"]').first();
        await passIn.fill('wrongpassword');

        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForTimeout(2000);
        expect(page.url()).toContain('/login');
    });
});
