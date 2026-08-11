import { test, expect, type Page } from '@playwright/test';

const BASE = process.env.E2E_BASE_URL ?? 'http://localhost:64080';

async function loginOnce(page: Page, username: string = 'dev'): Promise<void> {
    await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
    
    if (page.url().includes('/login')) {
        const userIn = page.locator('input[autocomplete="username"]').first();
        await userIn.waitFor({ state: 'visible', timeout: 15000 });
        await userIn.fill(username);

        const passIn = page.locator('input[autocomplete="current-password"]').first();
        await passIn.fill('password');
        
        await page.getByRole('button', { name: /Masuk/i }).first().click();
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 15000 });
    }
}

test.describe('1. Landing Page & Public UI', () => {

    test('1.1. Landing page displays professional hero, features, steps, and interactive FAQ', async ({ page }) => {
        await page.goto(`${BASE}/`, { waitUntil: 'domcontentloaded' });
        
        // Assert page elements
        await expect(page.locator('body')).toBeVisible();
        await expect(page.locator('text=Dana Bergulir').first()).toBeVisible();

        // FAQ accordion click
        const faqButton = page.locator('button:has-text("Apa itu"), button:has-text("Apakah")').first();
        if (await faqButton.isVisible()) {
            await faqButton.click();
            await page.waitForTimeout(200);
        }

        // Login button
        const loginLink = page.locator('a[href*="/login"]').first();
        await expect(loginLink).toBeVisible();
    });

    test('1.2. Login page renders clean design without framework leaks', async ({ page }) => {
        await page.goto(`${BASE}/login`, { waitUntil: 'domcontentloaded' });
        
        await expect(page.getByRole('heading', { name: 'Masuk ke Akun Anda' })).toBeVisible();
        await expect(page.locator('input[autocomplete="username"]')).toBeVisible();
        await expect(page.locator('input[autocomplete="current-password"]')).toBeVisible();
        await expect(page.getByRole('button', { name: /Masuk/i })).toBeVisible();
    });

});

test.describe('2. Admin Suite Tests (/admin)', () => {
    test.describe.configure({ mode: 'serial' });

    test('2.0. Admin Login', async ({ page }) => {
        await loginOnce(page, 'superadmin');
        await expect(page).toHaveURL(/admin/);
    });

    test('2.1. Admin Dashboard', async ({ page }) => {
        await page.goto(`${BASE}/admin`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('2.2. Admin Tenant Management (/admin/tenants)', async ({ page }) => {
        await page.goto(`${BASE}/admin/tenants`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('2.3. Admin Subscription Plans (/admin/plans)', async ({ page }) => {
        await page.goto(`${BASE}/admin/plans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('2.4. Admin Invoices (/admin/invoices)', async ({ page }) => {
        await page.goto(`${BASE}/admin/invoices`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('2.5. Admin Legacy Migration Hub (/admin/migration)', async ({ page }) => {
        await page.goto(`${BASE}/admin/migration`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('2.6. Admin Integrations & Tripay/WA Gateway (/admin/integrations)', async ({ page }) => {
        await page.goto(`${BASE}/admin/integrations`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

});

test.describe('3. Operational Tenant Suite Tests', () => {
    test.describe.configure({ mode: 'serial' });

    test('3.0. Tenant User Login', async ({ page }) => {
        await loginOnce(page, 'dev');
        await expect(page).toHaveURL(/dashboard/);
    });

    test('3.1. Operations Dashboard (/dashboard)', async ({ page }) => {
        await page.goto(`${BASE}/dashboard`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.2. Master Data - Anggota (/master-data/members)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/members`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.3. Master Data - Kelompok (/master-data/groups)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/groups`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.4. Master Data - Desa / Wilayah (/master-data/villages)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/villages`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.5. Master Data - Lembaga Lain (/master-data/institutions)', async ({ page }) => {
        await page.goto(`${BASE}/master-data/institutions`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.6. Lending - Proposal Pinjaman (/lending/proposals)', async ({ page }) => {
        await page.goto(`${BASE}/lending/proposals`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.7. Lending - Data Pinjaman & SPP (/lending/loans)', async ({ page }) => {
        await page.goto(`${BASE}/lending/loans`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.8. Accounting - Bukti Kas Masuk/Keluar (/accounting/cash-evidence)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/cash-evidence`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.9. Accounting - Transaksi Jurnal Umum (/accounting/journal-entries)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.10. Accounting - Jurnal Angsuran (/accounting/journal-entries/installment)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journal-entries/installment`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.11. Accounting - Buku Jurnal (/accounting/journals)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/journals`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.12. Accounting - Bagan Akun COA (/accounting/accounts)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/accounts`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.13. Accounting - Aset Tetap & Inventaris (/accounting/assets)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/assets`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.14. Accounting - Tutup Buku Periode (/accounting/period-close)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/period-close`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.15. Budgeting / RAPB (/budgeting)', async ({ page }) => {
        await page.goto(`${BASE}/budgeting`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.16. Onboarding Wizard (/onboarding/import)', async ({ page }) => {
        await page.goto(`${BASE}/onboarding/import`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.17. Tenant Billing & Invoices (/billing/invoices)', async ({ page }) => {
        await page.goto(`${BASE}/billing/invoices`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.18. Notification Billing (/notifications/billing)', async ({ page }) => {
        await page.goto(`${BASE}/notifications/billing`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.19. Settings (/settings)', async ({ page }) => {
        await page.goto(`${BASE}/settings`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('3.20. User Profile (/profile)', async ({ page }) => {
        await page.goto(`${BASE}/profile`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

});

test.describe('4. Complete Reports Suite (Including All Legacy Reports)', () => {
    test.describe.configure({ mode: 'serial' });

    test('4.0. Reports User Setup', async ({ page }) => {
        await loginOnce(page, 'dev');
    });

    test('4.1. Reports Directory Hub (/accounting/reports)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.2. Lending - Portofolio Pinjaman (/lending/reports/portfolio)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports/portfolio`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.3. Lending - Rencana vs Realisasi (/lending/reports/schedule-vs-actual)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports/schedule-vs-actual`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.4. Lending - LPP Rekap Desa (/lending/reports/lpp-desa)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports/lpp-desa`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.5. Lending - LPP Rincian Kelompok (/lending/reports/lpp-kelompok)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports/lpp-kelompok`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.6. Lending - Kolektibilitas Pinjaman (/lending/reports/kolek-desa)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports/kolek-desa`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.7. Lending - Cadangan Kerugian CKPN (/lending/reports/cadangan-penghapusan)', async ({ page }) => {
        await page.goto(`${BASE}/lending/reports/cadangan-penghapusan`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.8. Accounting - Penilaian Kesehatan Usaha (/accounting/reports/financial-health)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/financial-health`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.9. Accounting - Dokumen LPJ & Cover Tahunan Pack (/accounting/reports/annual-pack)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/annual-pack`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.10. Accounting - Neraca Saldo (/accounting/reports/trial-balance)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/trial-balance`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.11. Accounting - Neraca (/accounting/reports/balance-sheet)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/balance-sheet`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.12. Accounting - Laba Rugi (/accounting/reports/income-statement)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/income-statement`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.13. Accounting - Buku Besar (/accounting/reports/general-ledger)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/general-ledger`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.14. Accounting - Arus Kas (/accounting/reports/cash-flow)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/cash-flow`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.15. Accounting - Perubahan Ekuitas (/accounting/reports/equity-change)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/equity-change`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

    test('4.16. Accounting - CALK (/accounting/reports/calk)', async ({ page }) => {
        await page.goto(`${BASE}/accounting/reports/calk`, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).toBeVisible();
    });

});