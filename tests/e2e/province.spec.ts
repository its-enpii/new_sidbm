import { test, expect } from '@playwright/test';

test.describe('Province Level Suite Tests (/province)', () => {
    test('Unauthenticated user is redirected from /province/dashboard to login', async ({ page }) => {
        await page.goto('/province/dashboard');
        await expect(page).toHaveURL(/\/login/);
    });

    test('Unauthenticated user is redirected from /province/reports/pack to login', async ({ page }) => {
        await page.goto('/province/reports/pack');
        await expect(page).toHaveURL(/\/login/);
    });
});
