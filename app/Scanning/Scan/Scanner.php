<?php

declare(strict_types=1);

namespace App\Scanning\Scan;

use App\Scanning\Net;
use App\Scanning\ScanException;

/** Orchestrates a passive scan: resolve, fetch with safe redirects, probe TLS, assess, score. */
final class Scanner
{
    private const MAX_REDIRECTS = 5;

    private Advisor $advisor;

    private HttpClient $http;

    private TlsProbe $tls;

    private HeaderChecks $checks;

    /** @param array<string, mixed> $adv */
    public function __construct(private array $adv)
    {
        $this->advisor = new Advisor($adv);
        $this->http = new HttpClient;
        $this->tls = new TlsProbe;
        $this->checks = new HeaderChecks;
    }

    /** @return array<string, mixed> */
    public function scan(string $input): array
    {
        $t = Net::parseUrl($input);
        $first = $t;
        $chain = [];
        $res = null;
        $deadline = microtime(true) + 25;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (microtime(true) > $deadline) {
                throw new ScanException('The website took too long to respond.', 504);
            }
            $resolved = Net::resolve($t['host']);
            $ip = $resolved[0];
            $res = $this->http->fetch($t, $ip, true);
            if (isset($res['errno']) && $t['scheme'] === 'https' && in_array($res['errno'], HttpClient::CERT_ERRNOS, true)) {
                $res = $this->http->fetch($t, $ip, false);
            }
            if (isset($res['error'])) {
                throw new ScanException($res['error'], 502);
            }
            $chain[] = ['url' => $t['url'], 'status' => $res['status']];
            $loc = $res['headers']['location'][0] ?? null;
            if ($res['status'] >= 300 && $res['status'] < 400 && $loc && $hop < self::MAX_REDIRECTS) {
                try {
                    $t = Net::parseUrl(HttpClient::absolutize($loc, $t));
                } catch (ScanException $e) {
                    throw new ScanException('The site redirects somewhere that cannot be scanned. '.$e->getMessage());
                }

                continue;
            }
            break;
        }
        $finalIps = Net::resolve($t['host']);
        $tls = $t['scheme'] === 'https' ? $this->tls->probe($t['host'], $finalIps[0]) : null;
        $h = array_map(fn ($v) => implode(', ', $v), $res['headers']);
        $blob = ($h['x-powered-by'] ?? '').' '.($h['server'] ?? '');

        preg_match('/\bPHP\/(\d+\.\d+\.\d+)/i', $blob, $m);
        $php = $this->advisor->php($m[1] ?? null);

        preg_match('/\bNode(?:\.js)?[\/ ]v?(\d+\.\d+\.\d+)/i', $blob, $n);
        $frameworks = [];
        if (stripos($h['x-powered-by'] ?? '', 'express') !== false) {
            $frameworks[] = 'Express';
        }
        if (stripos($h['x-powered-by'] ?? '', 'next.js') !== false || isset($h['x-nextjs-cache'])) {
            $frameworks[] = 'Next.js';
        }
        $node = $this->advisor->node($n[1] ?? null, $frameworks);
        $fingerprints = $this->fingerprints($h, $res['body'], $res['headers']);

        [$checks, $score] = $this->checks->run($first, $t, $res, $h, $tls, $chain);
        $visibleHeaders = array_intersect_key($h, array_flip([
            'strict-transport-security', 'content-security-policy', 'content-security-policy-report-only',
            'x-content-type-options', 'x-frame-options', 'referrer-policy', 'permissions-policy',
            'cross-origin-opener-policy', 'cross-origin-embedder-policy', 'cross-origin-resource-policy',
            'access-control-allow-origin', 'x-xss-protection',
        ]));
        $visibleHeaders = array_map(fn (string $value) => mb_substr($value, 0, 2048), $visibleHeaders);

        return [
            'target' => $first['url'], 'final_url' => $t['url'], 'status' => $res['status'],
            'latency_ms' => $res['ms'], 'scanned_at' => gmdate('c'), 'advisories_updated' => $this->adv['updated'],
            'redirects' => $chain, 'php' => $php, 'node' => $node, 'tls' => $tls,
            'fingerprints' => $fingerprints,
            'network' => [
                'ipv4_count' => count(array_filter($finalIps, fn ($ip) => ! str_contains($ip, ':'))),
                'ipv6_count' => count(array_filter($finalIps, fn ($ip) => str_contains($ip, ':'))),
                'content_type' => $h['content-type'] ?? null,
                'server' => $h['server'] ?? null,
            ],
            'checks' => $checks, 'score' => $score,
            'observed_headers' => $visibleHeaders,
            'limitations' => 'Passive checks of public response headers and the TLS handshake. Runtime versions are only visible when a site discloses them, and distribution backports are not detectable. Not verifiable never means safe.',
        ];
    }

    /**
     * Passive technology clues from the response already fetched. Never treated as proof.
     *
     * @param  array<string, string>  $h
     * @param  array<string, list<string>>  $rawHeaders
     * @return list<array{category: string, name: string, evidence: string, confidence: string}>
     */
    private function fingerprints(array $h, string $body, array $rawHeaders): array
    {
        $out = [];
        $add = function (string $category, string $name, string $evidence, string $confidence = 'medium') use (&$out) {
            foreach ($out as $x) {
                if ($x['name'] === $name) {
                    return;
                }
            }
            $out[] = compact('category', 'name', 'evidence', 'confidence');
        };
        $server = $h['server'] ?? '';
        $powered = $h['x-powered-by'] ?? '';
        foreach ([
            ['Web server', 'nginx', '/\bnginx\b/i', $server],
            ['Web server', 'Apache', '/\bapache\b/i', $server],
            ['Web server', 'Microsoft IIS', '/\bmicrosoft-iis\b/i', $server],
            ['Framework', 'Express', '/\bexpress\b/i', $powered],
            ['Framework', 'Next.js', '/\bnext\.?js\b/i', $powered],
            ['Runtime', 'PHP', '/\bphp(?:\/|$)/i', $powered],
            ['Runtime', 'ASP.NET', '/\basp\.net\b/i', $powered],
        ] as [$cat, $name, $rx, $value]) {
            if ($value && preg_match($rx, $value)) {
                $add($cat, $name, $value, 'high');
            }
        }
        if (isset($h['x-nextjs-cache']) || isset($h['x-nextjs-prerender'])) {
            $add('Framework', 'Next.js', 'Next.js response header', 'high');
        }
        if (isset($h['cf-ray']) || stripos($server, 'cloudflare') !== false) {
            $add('Edge / CDN', 'Cloudflare', 'Cloudflare response headers', 'high');
        }
        if (isset($h['x-vercel-id'])) {
            $add('Hosting', 'Vercel', 'X-Vercel-Id header', 'high');
        }
        if (isset($h['x-nf-request-id'])) {
            $add('Hosting', 'Netlify', 'X-Nf-Request-Id header', 'high');
        }
        if (isset($h['x-served-by']) && stripos($h['via'] ?? '', 'varnish') !== false) {
            $add('Edge / CDN', 'Fastly / Varnish', 'Via / X-Served-By headers', 'medium');
        }
        if (preg_match('~<meta[^>]+name=["\']generator["\'][^>]+content=["\']([^"\']+)~i', $body, $m)) {
            $add('Application', trim($m[1]), 'HTML generator meta tag', 'high');
        }
        if (stripos($body, '/wp-content/') !== false || stripos($body, '/wp-includes/') !== false) {
            $add('CMS', 'WordPress', 'WordPress asset paths in HTML', 'medium');
        }
        foreach (($rawHeaders['set-cookie'] ?? []) as $cookie) {
            if (stripos($cookie, 'laravel_session=') !== false) {
                $add('Framework', 'Laravel', 'laravel_session cookie', 'high');
            }
            if (stripos($cookie, 'csrftoken=') !== false) {
                $add('Framework', 'Django (possible)', 'csrftoken cookie', 'low');
            }
        }

        return array_slice($out, 0, 10);
    }
}
