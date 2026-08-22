import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { defineConfig, devices } from '@playwright/test';

const browserChannel = process.env.CI
    ? undefined
    : (process.env.PLAYWRIGHT_CHANNEL ?? (process.platform === 'win32' ? 'msedge' : undefined));

export default defineConfig({
    testDir: './tests',
    outputDir: join(tmpdir(), 'atlas-playwright-results'),
    fullyParallel: false,
    workers: 1,
    timeout: 45_000,
    expect: { timeout: 10_000 },
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:8000',
        channel: browserChannel,
        locale: 'fr-FR',
        screenshot: process.env.ATLAS_E2E_SCREENSHOTS === 'true' ? 'on' : 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'mobile',
            use: { ...devices['iPhone 13'], browserName: 'chromium' },
        },
    ],
});
