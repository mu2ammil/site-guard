import { expect, test } from '@playwright/test';

test('admin activity and settings fit the viewport', async ({
    page,
}, testInfo) => {
    const email = process.env.SITEGUARD_TEST_ADMIN_EMAIL;
    const password = process.env.SITEGUARD_TEST_ADMIN_PASSWORD;
    test.skip(!email || !password, 'Provide a temporary admin test account');
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email!);
    await page.getByLabel('Password', { exact: true }).fill(password!);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/dashboard/, { timeout: 10_000 });
    await page.goto('/admin');
    await expect(
        page.getByRole('heading', { name: 'Scan activity' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Site settings' }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBe(true);
    await page.screenshot({
        path: testInfo.outputPath('admin.png'),
        fullPage: true,
    });
});
