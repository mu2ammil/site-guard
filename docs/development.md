# Development and tests

Install dependencies and initialize the database as described in the [root README](../README.md). Run `npm run dev` while changing Vue or CSS files; `npm run build` produces assets for a normal local or production web server. The project's `composer run dev` command starts its Laravel development workflow.

## Checks

```sh
php artisan test tests/Feature/SiteGuardTest.php --compact
npm run types:check
npm run check
npm run build
npx playwright test
```

Playwright checks desktop and mobile layout, scan interactions, report explanations, and horizontal overflow using fixed responses so reruns do not consume scan quota. Its configured base URL is `https://site-scan.test`, so use Herd with that hostname for browser checks. Set `SITEGUARD_LIVE_SCAN=1` to include a live `example.com` scan; this needs outbound network access and available scan quota. Set `SITEGUARD_TEST_ADMIN_EMAIL` and `SITEGUARD_TEST_ADMIN_PASSWORD` for the optional admin layout check.

Laravel Boost guidance is installed for development. The local `.codex/config.toml` may need its PHP command path adjusted for your machine.

## Dependencies and references

The Laravel starter kit supplies Fortify, Inertia, Vue, Tailwind CSS, and Boost. Motion handles modest UI animation, and Playwright runs browser checks. No additional PHP package is required for scanner networking.

- [Laravel 13 documentation](https://laravel.com/docs/13.x)
- [Inertia documentation](https://inertiajs.com/)
- [Motion documentation](https://motion.dev/docs/quick-start)
- [Playwright documentation](https://playwright.dev/docs/intro)
