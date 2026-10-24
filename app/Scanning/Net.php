<?php

declare(strict_types=1);

namespace App\Scanning;

/** URL validation and public-address enforcement (SSRF defence). */
final class Net
{
    private const BLOCK = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16',
        '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16',
        '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3',
        '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '100::/64', '2001::/32',
        '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    private const BAD_SUFFIX = ['local', 'internal', 'localhost', 'lan', 'home', 'corp', 'intranet', 'arpa'];

    public static function isPublicIp(string $ip): bool
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        foreach (self::BLOCK as $cidr) {
            if (self::inCidr($bin, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $bin, string $cidr): bool
    {
        [$net, $bits] = explode('/', $cidr);
        $nb = inet_pton($net);
        if ($nb === false || strlen($nb) !== strlen($bin)) {
            return false;
        }
        $bits = (int) $bits;
        $full = intdiv($bits, 8);
        $rem = $bits % 8;
        if ($full > 0 && substr($bin, 0, $full) !== substr($nb, 0, $full)) {
            return false;
        }
        if ($rem > 0) {
            $mask = (0xFF << (8 - $rem)) & 0xFF;

            return (ord($bin[$full]) & $mask) === (ord($nb[$full]) & $mask);
        }

        return true;
    }

    /** @return array{scheme:string,host:string,port:int,path:string,url:string} */
    public static function parseUrl(string $input): array
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > 2048 || preg_match('/[\x00-\x20\x7f]/', $input)) {
            throw new ScanException('Enter a website address, for example example.com.');
        }
        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $input)) {
            $input = 'https://'.$input;
        }
        $p = parse_url($input);
        if (! $p || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            throw new ScanException('That address is not a valid URL.');
        }
        $scheme = strtolower($p['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new ScanException('Only http and https addresses can be scanned.');
        }
        $host = strtolower(rtrim($p['host'], '.'));
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
            $host = (string) (idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host);
        }
        $tld = substr($host, (int) strrpos($host, '.') + 1);
        if (! preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $host)
            || strlen($host) > 253 || in_array($tld, self::BAD_SUFFIX, true)) {
            throw new ScanException('Enter a public domain name. IP addresses and internal hostnames are not scanned.');
        }
        $default = $scheme === 'https' ? 443 : 80;
        $port = (int) ($p['port'] ?? $default);
        if (! in_array($port, [80, 443], true)) {
            throw new ScanException('Only ports 80 and 443 can be scanned.');
        }
        $path = ($p['path'] ?? '/') ?: '/';
        if (isset($p['query'])) {
            $path .= '?'.$p['query'];
        }
        $url = $scheme.'://'.$host.($port === $default ? '' : ':'.$port).$path;

        return compact('scheme', 'host', 'port', 'path', 'url');
    }

    /** @return string[] public IPs, IPv4 first */
    public static function resolve(string $host): array
    {
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            if (! empty($r['ip']) || ! empty($r['ipv6'])) {
                $ips[] = $r['ip'] ?? $r['ipv6'];
            }
        }
        if (! $ips) {
            foreach (@gethostbynamel($host) ?: [] as $ip) {
                $ips[] = $ip;
            }
        }
        $ips = array_values(array_unique($ips));
        if (! $ips) {
            throw new ScanException('The domain does not resolve. Check the spelling and try again.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new ScanException('This domain points to a private or reserved address, so it cannot be scanned.');
            }
        }
        usort($ips, fn ($a, $b) => str_contains($a, ':') <=> str_contains($b, ':'));

        return $ips;
    }
}
