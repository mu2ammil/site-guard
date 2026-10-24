import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    use: {
        baseURL: 'https://site-scan.test',
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], channel: 'chromium' },
        },
        {
            name: 'mobile',
            use: {
                ...devices['iPhone 13'],
                browserName: 'chromium',
                channel: 'chromium',
            },
        },
    ],
    reporter: 'list',
});
