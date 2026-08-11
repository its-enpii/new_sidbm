import { defineConfig, devices } from '@playwright/test';
import fs from 'fs';

const CHROMIUM_PATH = process.env.CHROMIUM_PATH ?? '/usr/bin/chromium';
const hasCustomChromium = fs.existsSync(CHROMIUM_PATH);

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    timeout: 60000,
    reporter: 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:64080',
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
                ...(hasCustomChromium ? { launchOptions: { executablePath: CHROMIUM_PATH } } : {}),
            },
        },
    ],
});
