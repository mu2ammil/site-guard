<?php

namespace App\Scanning;

use Illuminate\Support\Facades\Cache;

class BotChallenge
{
    /** @return array{token: string, bits: int} */
    public function issue(): array
    {
        $bits = (int) config('siteguard.challenge_bits', 16);
        $payload = rtrim(strtr(base64_encode(json_encode([
            'nonce' => bin2hex(random_bytes(12)),
            'expires' => now()->addMinutes(5)->timestamp,
            'bits' => $bits,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        return ['token' => $payload.'.'.hash_hmac('sha256', $payload, config('app.key')), 'bits' => $bits];
    }

    public function verify(string $token, string $answer): bool
    {
        if (! preg_match('/^[A-Za-z0-9_-]{20,200}\.[a-f0-9]{64}$/', $token) || ! preg_match('/^\d{1,12}$/', $answer)) {
            return false;
        }
        [$payload, $signature] = explode('.', $token, 2);
        if (! hash_equals(hash_hmac('sha256', $payload, config('app.key')), $signature)) {
            return false;
        }
        $challenge = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        $bits = (int) config('siteguard.challenge_bits', 16);
        if (! is_array($challenge) || ($challenge['expires'] ?? 0) < time() || ($challenge['bits'] ?? null) !== $bits) {
            return false;
        }
        $hash = hash('sha256', $token.':'.$answer, true);
        $fullBytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if (substr($hash, 0, $fullBytes) !== str_repeat("\0", $fullBytes) || ($remainder && (ord($hash[$fullBytes]) >> (8 - $remainder)) !== 0)) {
            return false;
        }

        return Cache::add('siteguard:challenge:'.hash('sha256', $token), true, 300);
    }
}
