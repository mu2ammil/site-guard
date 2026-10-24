<?php

declare(strict_types=1);

namespace App\Scanning\Scan;

/** Header and transport hygiene checks plus the 0-100 score. */
final class HeaderChecks
{
    /**
     * @param  array{scheme: string, host: string, port: int, path: string, url: string}  $first
     * @param  array{scheme: string, host: string, port: int, path: string, url: string}  $t
     * @param  array{status: int, headers: array<string, list<string>>, body: string, ms: int}  $res
     * @param  array<string, string>  $h
     * @param  array{verified: bool, protocol: ?string, cipher: ?string, issuer: ?string, subject: ?string, valid_to: ?string, days_left: ?int}|null  $tls
     * @param  list<array{url: string, status: int}>  $chain
     * @return array{list<array<string, mixed>>, array{value: int, grade: string}}
     */
    public function run(array $first, array $t, array $res, array $h, ?array $tls, array $chain): array
    {
        $c = [];
        $impacts = [
            'https' => 'Without encryption, network observers can read or alter responses and submitted data.',
            'redirect' => 'An HTTP entry point lets attackers interfere before visitors reach HTTPS.',
            'hsts' => 'Without HSTS, returning visitors may be downgraded to HTTP.',
            'cert' => 'An invalid certificate prevents browsers from reliably authenticating the site.',
            'proto' => 'Older TLS versions have weaker protections and may expose traffic to known attacks.',
            'csp' => 'A weak or missing policy gives injected scripts more freedom to run and exfiltrate data.',
            'xcto' => 'Browsers may interpret a resource as executable content despite its declared type.',
            'referrer' => 'Full URLs can leak paths and query data to other sites through the Referer header.',
            'frame' => 'Other sites may frame the page and trick users into clicking hidden controls.',
            'perm' => 'Embedded or third-party content may request browser capabilities the site does not need.',
            'cookies' => 'Missing flags can expose cookies to scripts, plain HTTP, or cross-site requests.',
            'disclosure' => 'Version details make it easier to target a known vulnerable software release.',
            'cors' => 'Overly broad cross-origin access can expose API responses to untrusted websites.',
            'coop' => 'Cross-origin pages may retain opener relationships when isolation is required.',
            'corp' => 'Other origins may embed resources that should only be used by your own site.',
            'coep' => 'The page cannot use cross-origin isolation features when required.',
        ];
        $add = function (string $id, string $group, string $title, string $state, string $detail, int $w, ?string $ev = null) use (&$c, $impacts) {
            $c[] = ['id' => $id, 'group' => $group, 'title' => $title, 'state' => $state, 'detail' => $detail, 'weight' => $w, 'evidence' => $ev, 'impact' => $impacts[$id] ?? null];
        };
        $https = $t['scheme'] === 'https';
        $add('https', 'Transport', 'HTTPS', $https ? 'pass' : 'fail', $https ? 'The page is served over HTTPS.' : 'The page is served over plain HTTP, so traffic can be read or altered in transit.', 15);
        if ($first['scheme'] === 'http') {
            $add('redirect', 'Transport', 'HTTP redirects to HTTPS', $https ? 'pass' : 'fail', $https ? 'Plain HTTP requests are upgraded to HTTPS.' : 'Plain HTTP requests are not upgraded to HTTPS.', 10);
        }
        if ($https) {
            $hsts = $h['strict-transport-security'] ?? null;
            $age = $hsts && preg_match('/max-age=(\d+)/i', $hsts, $m) ? (int) $m[1] : 0;
            $add('hsts', 'Transport', 'HSTS', $age >= 15552000 ? 'pass' : ($hsts ? 'warn' : 'fail'),
                $age >= 15552000 ? 'Browsers are told to use HTTPS for at least six months.' : ($hsts ? 'HSTS is set but max-age is under six months.' : 'Browsers are not told to insist on HTTPS.'), 15, $hsts);
            if ($tls) {
                $d = $tls['days_left'];
                $state = ! $tls['verified'] ? 'fail' : (($d !== null && $d < 14) ? 'warn' : 'pass');
                $add('cert', 'Transport', 'TLS certificate', $state,
                    ! $tls['verified'] ? 'The certificate failed validation (untrusted, expired or wrong name).' : ($state === 'warn' ? 'The certificate expires soon.' : 'The certificate is trusted and not close to expiry.'),
                    10, trim(($tls['issuer'] ?? '').($d !== null ? ", $d days left" : ''), ', '));
                $okp = in_array($tls['protocol'], ['TLSv1.3', 'TLSv1.2'], true);
                $add('proto', 'Transport', 'TLS version', $okp ? 'pass' : 'fail', $okp ? 'A modern TLS version was negotiated.' : 'An outdated TLS version was negotiated.', 5, $tls['protocol']);
            }
        }
        $csp = $h['content-security-policy'] ?? null;
        $scriptSources = null;
        if ($csp && preg_match('/(?:^|;)\s*script-src\s+([^;]+)/i', $csp, $sources)) {
            $scriptSources = $sources[1];
        } elseif ($csp && preg_match('/(?:^|;)\s*default-src\s+([^;]+)/i', $csp, $sources)) {
            $scriptSources = $sources[1];
        }
        $weak = $csp && (! $scriptSources || preg_match("/'unsafe-(?:inline|eval)'|(?:^|\s)\*(?:\s|$)|(?:^|\s)https?:/i", $scriptSources));
        $add('csp', 'Browser protections', 'Content Security Policy', $csp ? ($weak ? 'warn' : 'pass') : 'fail',
            $csp ? ($weak ? 'A policy exists, but its script sources are absent or broadly permissive.' : 'A policy restricts where scripts and content can load from.') : 'No policy limits injected scripts.', 15, $csp);
        $add('xcto', 'Browser protections', 'MIME sniffing protection', strtolower($h['x-content-type-options'] ?? '') === 'nosniff' ? 'pass' : 'fail', 'X-Content-Type-Options should be nosniff.', 5, $h['x-content-type-options'] ?? null);
        $referrer = strtolower(trim($h['referrer-policy'] ?? ''));
        $safeReferrer = in_array($referrer, ['no-referrer', 'same-origin', 'strict-origin', 'strict-origin-when-cross-origin'], true);
        $add('referrer', 'Browser protections', 'Referrer policy', $safeReferrer ? 'pass' : 'warn', $safeReferrer ? 'The policy limits referrer details sent to other sites.' : 'Use a policy that limits URL details sent to other sites.', 5, $h['referrer-policy'] ?? null);
        $xfo = strtoupper(trim($h['x-frame-options'] ?? ''));
        $frame = in_array($xfo, ['DENY', 'SAMEORIGIN'], true) || (bool) preg_match('/(?:^|;)\s*frame-ancestors\s+(?!\*)[^;]+/i', $csp ?? '');
        $add('frame', 'Browser protections', 'Clickjacking protection', $frame ? 'pass' : 'fail', 'Needs X-Frame-Options or CSP frame-ancestors.', 10, $h['x-frame-options'] ?? null);
        $add('perm', 'Browser protections', 'Permissions policy', isset($h['permissions-policy']) ? 'pass' : 'warn', 'Limits browser features such as camera and location.', 5, $h['permissions-policy'] ?? null);
        if (isset($h['content-security-policy-report-only'])) {
            $add('csp_report', 'Browser protections', 'CSP report-only', 'info', 'A report-only policy collects violations but does not enforce restrictions.', 0, $h['content-security-policy-report-only']);
        }
        if (isset($h['x-xss-protection'])) {
            $add('legacy_xss', 'Browser protections', 'Legacy XSS filter', 'info', 'X-XSS-Protection is obsolete; use a strong Content Security Policy instead.', 0, $h['x-xss-protection']);
        }

        $acao = trim($h['access-control-allow-origin'] ?? '');
        $cred = strtolower(trim($h['access-control-allow-credentials'] ?? '')) === 'true';
        if ($acao !== '') {
            $corsDetail = $acao === '*'
                ? ($cred ? 'Wildcard origin with credentials is rejected by browsers; review the configuration.' : 'Any origin may read this public response. Review this setting for sensitive APIs.')
                : 'An origin is allowed on this response. A single request cannot show whether the server reflects arbitrary origins.';
            $add('cors', 'Cross-origin controls', 'CORS policy', 'info', $corsDetail, 0, 'Access-Control-Allow-Origin: '.$acao.($cred ? '; credentials=true' : ''));
        } else {
            $add('cors', 'Cross-origin controls', 'CORS policy', 'info', 'No Access-Control-Allow-Origin header is exposed on this response.', 0);
        }
        $coop = $h['cross-origin-opener-policy'] ?? null;
        $corp = $h['cross-origin-resource-policy'] ?? null;
        $coep = $h['cross-origin-embedder-policy'] ?? null;
        $add('coop', 'Cross-origin controls', 'Opener isolation', $coop ? 'pass' : 'info', $coop ? 'Cross-Origin-Opener-Policy is configured.' : 'COOP is not set. Many sites do not need it; use it when stronger browsing-context isolation is required.', 0, $coop);
        $add('corp', 'Cross-origin controls', 'Resource isolation', $corp ? 'pass' : 'info', $corp ? 'Cross-Origin-Resource-Policy is configured.' : 'CORP is not set. It is optional, but useful for restricting who may embed sensitive resources.', 0, $corp);
        $add('coep', 'Cross-origin controls', 'Embedder isolation', $coep ? 'pass' : 'info', $coep ? 'Cross-Origin-Embedder-Policy is configured.' : 'COEP is not set. It is mainly needed for cross-origin-isolated applications.', 0, $coep);

        $cookies = $res['headers']['set-cookie'] ?? [];
        $bad = [];
        foreach ($cookies as $ck) {
            $name = trim(explode('=', $ck, 2)[0]);
            if (($https && ! preg_match('/;\s*secure/i', $ck)) || ! preg_match('/;\s*httponly/i', $ck) || ! preg_match('/;\s*samesite=/i', $ck)) {
                $bad[] = $name;
            }
        }
        $add('cookies', 'Cookies', 'Cookie flags', ! $cookies ? 'info' : ($bad ? 'warn' : 'pass'),
            ! $cookies ? 'This response sets no cookies.' : ($bad ? 'Some cookies lack Secure, HttpOnly or SameSite.' : 'All cookies set Secure, HttpOnly and SameSite.'),
            $cookies ? 10 : 0, $bad ? implode(', ', array_slice($bad, 0, 5)) : null);

        $leak = [];
        if (preg_match('/\d+\.\d+/', $h['server'] ?? '')) {
            $leak[] = 'Server: '.$h['server'];
        }
        if (isset($h['x-powered-by'])) {
            $leak[] = 'X-Powered-By: '.$h['x-powered-by'];
        }
        $add('disclosure', 'Disclosure', 'Software disclosure', $leak ? 'warn' : 'pass',
            $leak ? 'Headers reveal software details that help attackers choose exploits.' : 'No version details found in Server or X-Powered-By.', 5, $leak ? implode('; ', $leak) : null);

        $tot = 0;
        $got = 0.0;
        foreach ($c as $x) {
            $tot += $x['weight'];
            $got += $x['state'] === 'pass' ? $x['weight'] : ($x['state'] === 'warn' ? $x['weight'] / 2 : 0);
        }
        $v = $tot ? (int) round($got / $tot * 100) : 0;

        return [$c, ['value' => $v, 'grade' => $v >= 90 ? 'A' : ($v >= 80 ? 'B' : ($v >= 65 ? 'C' : ($v >= 50 ? 'D' : 'F')))]];
    }
}
