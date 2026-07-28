import { defineConfig, devices } from '@playwright/test';

const CHROMIUM_PATH = process.env.CHROMIUM_PATH ?? '/usr/bin/chromium';

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    reporter: 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://new_sidbm-nginx-1',
        headless: true,
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        trace: 'retain-on-failure',
        ignoreHTTPSErrors: true,
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                launchOptions: { executablePath: CHROMIUM_PATH },
            },
        },
    ],
});