<?php

declare(strict_types=1);

namespace App\Scanning\Scan;

/** One pinned, size-capped HTTP request. Never follows redirects itself. */
final class HttpClient
{
    public const MAX_BODY = 65536;

    public const CERT_ERRNOS = [35, 51, 58, 59, 60, 77, 83];

    /**
     * @param  array{scheme: string, host: string, port: int, path: string, url: string}  $t
     * @return array{error: string, errno: int}|array{status: int, headers: array<string, list<string>>, body: string, ms: int}
     */
    public function fetch(array $t, string $ip, bool $verify): array
    {
        if ($t['url'] === '') {
            throw new \InvalidArgumentException('The target URL is empty.');
        }
        $headers = [];
        $body = '';
        $left = self::MAX_BODY;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $t['url'], CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => 'SiteGuard/2.3 (passive checker)', CURLOPT_HTTPHEADER => ['Accept: text/html,*/*;q=0.5'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => $verify, CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
            CURLOPT_RESOLVE => [sprintf('%s:%d:%s', $t['host'], $t['port'], str_contains($ip, ':') ? "[$ip]" : $ip)],
            CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$headers) {
                $len = strlen($line);
                $line = trim($line);
                if (str_starts_with($line, 'HTTP/')) {
                    $headers = [];
                } elseif (($i = strpos($line, ':')) !== false) {
                    $headers[strtolower(trim(substr($line, 0, $i)))][] = trim(substr($line, $i + 1));
                }

                return $len;
            },
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$body, &$left) {
                $n = strlen($chunk);
                $body .= substr($chunk, 0, max(0, $left));
                $left -= $n;

                return $left < 0 ? 0 : $n;
            },
        ]);
        $ok = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $ms = (int) round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        curl_close($ch);
        if ($ok === false && ! ($left < 0 && $errno === 23)) {
            $msg = match (true) {
                $errno === 6 => 'The domain could not be resolved.',
                $errno === 7 => 'The server refused the connection.',
                $errno === 28 => 'The website took too long to respond.',
                in_array($errno, self::CERT_ERRNOS, true) => 'The TLS handshake failed: '.$err,
                default => 'Could not complete the request: '.$err,
            };

            return ['error' => $msg, 'errno' => $errno];
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $body, 'ms' => $ms];
    }

    /** @param array{scheme: string, host: string, port: int, path: string, url: string} $t */
    public static function absolutize(string $loc, array $t): string
    {
        if (preg_match('~^https?://~i', $loc)) {
            return $loc;
        }
        if (str_starts_with($loc, '//')) {
            return $t['scheme'].':'.$loc;
        }
        $base = preg_replace('~^(https?://[^/]+).*$~i', '$1', $t['url']);
        if (str_starts_with($loc, '/')) {
            return $base.$loc;
        }

        return $base.preg_replace('~[^/]*$~', '', (string) strtok($t['path'], '?')).$loc;
    }
}
