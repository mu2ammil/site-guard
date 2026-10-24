# Production deployment

SiteGuard scans public websites and stores scan metadata, contact requests, and owner alert settings. Configure its database, mail transport, workers, network access, and privacy notice before accepting public traffic.

## Application

- Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the public HTTPS origin, and use a strong `APP_KEY`.
- Configure a production database and mail transport. Use a shared cache such as Redis when running multiple workers or app servers.
- Run `php artisan migrate --force`, `npm run build`, and `php artisan optimize` during deployment.
- Serve only `public/`. Terminate HTTPS at a trusted proxy, configure Laravel's trusted proxies for your platform, and ensure session cookies are secure.
- Run `php artisan queue:work --tries=3` as a supervised process and monitor failed jobs. Run `php artisan schedule:run` every minute through cron or your platform scheduler. The scheduled cleanup removes scans, audit events, and contact requests after the retention period configured in Admin.
- Set `SITEGUARD_CONTACT_EMAIL` or save a contact email in Admin settings to receive help requests. `MAIL_MAILER=log` records messages without delivering them.

## Scanner and data

- Block private, loopback, link-local, and cloud metadata networks at the scanner host's egress firewall. SiteGuard checks DNS answers and pins validated public IPs at every redirect; the firewall adds defense in depth.
- Protect and back up the database. Scan logs include requester IP addresses, user agents, and target URLs; contact requests include personal details. Publish and maintain a privacy notice suited to your deployment.
- Review `resources/data/advisories.json` as PHP and Node.js security releases change. Public headers offer clues; distribution backports cannot be confirmed remotely.

## nginx version disclosure

nginx adds its own `Server` header after Laravel responds. Include [`deploy/nginx/siteguard-http.conf`](../deploy/nginx/siteguard-http.conf) from the nginx `http {}` block, or add `server_tokens off;` there directly. Then validate and reload nginx:

```sh
sudo nginx -t
sudo systemctl reload nginx
```

This hides the nginx version but leaves the generic `Server: nginx` product name. Verify the public HTTPS response after deployment. Managed hosts may require an equivalent platform setting.
