<?php

declare(strict_types=1);

namespace App\Scanning\Scan;

/** Compares disclosed runtime versions with the fixed releases in data/advisories.json. */
final class Advisor
{
    /** @param array<string, mixed> $adv */
    public function __construct(private array $adv) {}

    /** @return array<string, mixed> */
    public function php(?string $v): array
    {
        $a = $this->adv['php'];
        $base = ['advisory' => $a['name'], 'cves' => $a['cves'], 'source' => $a['source'], 'version' => $v, 'fixed' => null];
        if (! $v) {
            return $base + ['status' => 'unknown', 'reason' => 'The site does not disclose a PHP version, so its patch status cannot be checked from outside. If you run it, check phpinfo() or php -v on the server.'];
        }
        $branch = implode('.', array_slice(explode('.', $v), 0, 2));
        if (isset($a['fixed'][$branch])) {
            $fixed = $a['fixed'][$branch];
            $old = version_compare($v, $fixed, '<');

            return ['fixed' => $fixed, 'status' => $old ? 'potential' : 'apparently_patched',
                'reason' => $old
                    ? "Reported version $v is older than the first fixed release $fixed. A distribution backport could still protect the server, which headers cannot show."
                    : "Reported version $v is at or above $fixed. The header could be stale or spoofed, so this is not proof."] + $base;
        }
        if (version_compare($branch, $a['eol_below'], '<')) {
            return ['status' => 'potential', 'reason' => "PHP $branch is end of life and has no upstream fix. It is exposed unless your vendor ships a backport."] + $base;
        }

        return ['status' => 'unknown', 'reason' => "PHP $branch is newer than the advisory data in this scanner."] + $base;
    }

    /**
     * @param  list<string>  $frameworks
     * @return array<string, mixed>
     */
    public function node(?string $v, array $frameworks): array
    {
        $a = $this->adv['node'];
        $base = ['advisory' => $a['name'], 'cves' => $a['cves'], 'source' => $a['source'], 'version' => $v,
            'fixed' => null, 'frameworks' => $frameworks];
        if (! $v) {
            $why = $frameworks
                ? implode(' and ', $frameworks).' detected, which runs on Node.js, but the runtime version is not disclosed.'
                : 'No Node.js version is disclosed. Many sites hide the runtime or sit behind a proxy.';

            return $base + ['status' => 'unknown', 'reason' => $why.' Run node -v on the server to check.'];
        }
        $major = (string) (int) $v;
        if (isset($a['fixed'][$major])) {
            $fixed = $a['fixed'][$major];
            $old = version_compare($v, $fixed, '<');

            return ['fixed' => $fixed, 'status' => $old ? 'potential' : 'apparently_patched',
                'reason' => $old ? "Reported Node.js $v is older than the fixed release $fixed for its line." : "Reported Node.js $v is at or above $fixed for its line."] + $base;
        }
        if ((int) $major < $a['newest_known']) {
            return ['status' => 'potential', 'reason' => "Node.js $major is end of life and is always affected when a security release ships."] + $base;
        }

        return ['status' => 'unknown', 'reason' => "Node.js $major is newer than the advisory data in this scanner."] + $base;
    }
}
