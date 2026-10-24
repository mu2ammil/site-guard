<?php

declare(strict_types=1);

namespace App\Scanning\Scan;

/** TLS handshake inspection: protocol, cipher, issuer and expiry. */
final class TlsProbe
{
    /** @return array{verified: bool, protocol: ?string, cipher: ?string, issuer: ?string, subject: ?string, valid_to: ?string, days_left: ?int}|null */
    public function probe(string $host, string $ip): ?array
    {
        if (! function_exists('openssl_x509_parse')) {
            return null;
        }
        foreach ([true, false] as $verify) {
            $ctx = stream_context_create(['ssl' => ['peer_name' => $host, 'SNI_enabled' => true, 'SNI_server_name' => $host,
                'verify_peer' => $verify, 'verify_peer_name' => $verify, 'capture_peer_cert' => true]]);
            $fp = @stream_socket_client('ssl://'.(str_contains($ip, ':') ? "[$ip]" : $ip).':443', $en, $es, 6, STREAM_CLIENT_CONNECT, $ctx);
            if (! $fp) {
                continue;
            }
            $crypto = stream_get_meta_data($fp)['crypto'] ?? [];
            $pc = stream_context_get_params($fp)['options']['ssl']['peer_certificate'] ?? null;
            fclose($fp);
            $c = $pc ? openssl_x509_parse($pc) : null;
            $to = $c['validTo_time_t'] ?? null;

            return ['verified' => $verify, 'protocol' => $crypto['protocol'] ?? null, 'cipher' => $crypto['cipher_name'] ?? null,
                'issuer' => $c['issuer']['O'] ?? $c['issuer']['CN'] ?? null, 'subject' => $c['subject']['CN'] ?? null,
                'valid_to' => $to ? gmdate('c', $to) : null, 'days_left' => $to ? (int) floor(($to - time()) / 86400) : null];
        }

        return null;
    }
}
