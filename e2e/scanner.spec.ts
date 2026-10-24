import { expect, test } from '@playwright/test';

test.beforeEach(async ({ page }) => {
    await page.route('**/challenge', (route) =>
        route.fulfill({ json: { token: 'browser-check', bits: 0 } }),
    );
    await page.route('**/scans', (route) => {
        const input = route.request().postDataJSON().url as string;
        if (input.includes('localhost')) {
            return route.fulfill({
                status: 422,
                json: {
                    error: 'Enter a public domain name. IP addresses and internal hostnames are not scanned.',
                },
            });
        }
        return route.fulfill({
            json: {
                report: {
                    final_url: 'https://example.com/',
                    status: 200,
                    latency_ms: 150,
                    score: { value: 72, grade: 'C' },
                    checks: [
                        {
                            id: 'https',
                            group: 'Transport',
                            title: 'HTTPS',
                            state: 'pass',
                            detail: 'The page is served over HTTPS.',
                            impact: 'Encryption protects data in transit.',
                        },
                        {
                            id: 'csp',
                            group: 'Browser protections',
                            title: 'Content Security Policy',
                            state: 'fail',
                            detail: 'No policy limits injected scripts.',
                            impact: 'Injected scripts can read or change page data.',
                        },
                    ],
                    php: {
                        version: null,
                        reason: 'PHP version is not disclosed.',
                    },
                    node: {
                        version: null,
                        reason: 'Node.js version is not disclosed.',
                    },
                    fingerprints: [],
                    observed_headers: {
                        'content-security-policy': "default-src 'self'",
                    },
                    limitations: 'Passive checks of public signals only.',
                },
            },
        });
    });
});

test('scanner renders without horizontal overflow and rejects internal targets', async ({
    page,
}, testInfo) => {
    await page.goto('/');
    await expect(
        page.getByRole('heading', { name: /See what your website reveals/i }),
    ).toBeVisible();
    await expect(page.getByLabel('Website to scan')).toBeVisible();
    await expect(
        page.getByRole('img', {
            name: 'Abstract folded white paper shield',
        }),
    ).toBeVisible();
    await expect(
        page.locator('link[rel="icon"][type="image/svg+xml"]'),
    ).toHaveAttribute('href', '/favicon.svg?v=3');
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await expect(page.locator('.sg-hero h1')).toHaveCSS('opacity', '1');
    await page.screenshot({
        path: testInfo.outputPath('home.png'),
        fullPage: true,
    });

    await page.getByLabel('Website to scan').fill('http://localhost');
    await page.getByRole('button', { name: /Scan website/i }).click();
    await expect(page.getByRole('alert')).toContainText('public domain');
});

test('hero transitions respect reduced motion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');
    expect(
        await page.locator('.sg-hero-visual img').evaluate((image) =>
            parseFloat(getComputedStyle(image).transitionDuration),
        ),
    ).toBeLessThan(0.01);
});

test('public scanner follows dark mode and remembers a light override', async ({
    page,
}) => {
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    await expect(page.locator('.sg-page')).toHaveCSS(
        'background-color',
        'rgb(16, 16, 16)',
    );
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(page.locator('.sg-page')).toHaveCSS(
        'background-color',
        'rgb(250, 250, 250)',
    );
    await page.reload();
    await expect(page.locator('.sg-page')).toHaveCSS(
        'background-color',
        'rgb(250, 250, 250)',
    );
});

test('a public scan renders findings with explanations', async ({
    page,
}, testInfo) => {
    test.setTimeout(60_000);
    await page.goto('/');
    await page.getByLabel('Website to scan').fill('example.com');
    await page.getByRole('button', { name: /Scan website/i }).click();
    const report = page.locator('#report');
    await expect(report).toBeVisible({ timeout: 45_000 });
    await expect(
        report.getByText('Why it matters:', { exact: false }).first(),
    ).toBeVisible();
    await report.getByText('Observed security headers').click();
    await expect(report.getByText("default-src 'self'")).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await report.screenshot({ path: testInfo.outputPath('report.png') });
});

test('live public scan works through Herd', async ({ page }) => {
    test.skip(
        process.env.SITEGUARD_LIVE_SCAN !== '1',
        'Enable for a live network scan',
    );
    test.setTimeout(60_000);
    await page.unroute('**/challenge');
    await page.unroute('**/scans');
    await page.goto('/');
    await page.getByLabel('Website to scan').fill('example.com');
    await page.getByRole('button', { name: /Scan website/i }).click();
    await expect(page.locator('#report')).toBeVisible({ timeout: 45_000 });
});
