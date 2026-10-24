# Implementation and scanner behavior

SiteGuard uses Laravel 13, Inertia 3, Vue 3, Tailwind CSS, and Motion. The scanner lives in `app/Scanning`; its advisory data is in `resources/data/advisories.json`. Laravel's rate limiter, cache, notifications, queue, scheduler, and database support the application features. Network checks use PHP cURL and OpenSSL.

| Feature | Implementation |
| --- | --- |
| URL and IP safety, pinned HTTP fetches, redirects, TLS probe | `app/Scanning` |
| PHP and Node.js advisory comparison | `app/Scanning/Scan/Advisor.php`, `resources/data/advisories.json` |
| Header scoring and technology clues | `app/Scanning/Scan` |
| Browser proof of work | `app/Scanning/BotChallenge.php` |
| Per-IP rate limiting | Laravel RateLimiter and route middleware |
| Scan history and activity log | `scans` and `audit_events` tables |
| Contact inbox | `contact_requests` table and queued notification |
| Admin dashboard | `/admin`, limited to admin users |
| Account dashboard | `/dashboard`, with the user's scans and alerts |
| Site owner alerts | DNS-verified sites in `/monitored-sites` and queued email notifications |

## Reports

Reports cover HTTPS, HSTS, certificate and TLS status, Content Security Policy, X-Content-Type-Options, Referrer-Policy, clickjacking protection, Permissions-Policy, cookies, CORS, cross-origin isolation, and software disclosure. CSP report-only and legacy XSS headers appear as information. Only allowlisted security header values are shown; cookie values are excluded.

Public headers are clues. Hidden runtime versions cannot be evaluated, and distribution backports cannot be confirmed remotely. A public scan cannot establish that a server has no vulnerabilities.

## Owner alerts

A signed-in, email-verified user adds a domain under `/monitored-sites`. SiteGuard provides a unique TXT value to publish at `_siteguard.example.com`. After DNS verification, a scan of that exact hostname queues an alert for the owner. Owners can pause alerts or delete the site. Subdomains need separate verification. SiteGuard does not email addresses found on scanned websites.

## Visual assets

The Vue home page uses an illustrative signal graph with a subtle pulse that stops when reduced motion is requested. The shield and radar mark is in `public/brand-mark.png`; related icons are in `public/favicon.png`, `public/favicon.ico`, and `public/apple-touch-icon.png`.
