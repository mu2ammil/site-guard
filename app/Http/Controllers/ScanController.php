<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MonitoredSite;
use App\Models\Scan;
use App\Models\SiteSetting;
use App\Notifications\SiteScanned;
use App\Scanning\BotChallenge;
use App\Scanning\Net;
use App\Scanning\Scan\Scanner;
use App\Scanning\ScanException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class ScanController extends Controller
{
    public function store(Request $request, BotChallenge $challenge): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'token' => ['required', 'string'],
            'nonce' => ['required', 'string'],
            'website' => ['nullable', 'max:0'],
        ]);
        if (! $challenge->verify($data['token'], $data['nonce'])) {
            AuditEvent::record($request, 'scan_blocked', ['reason' => 'bot_check']);

            return response()->json(['error' => 'Browser check failed. Reload and try again.'], 403);
        }
        $input = $data['url'];
        if (SiteSetting::read('scan_enabled', '1') !== '1') {
            AuditEvent::record($request, 'scan_blocked', ['reason' => 'disabled']);

            return response()->json(['error' => 'Scans are temporarily paused.'], 503);
        }
        $rateKey = 'scan:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, (int) SiteSetting::read('scan_limit', '10'))) {
            AuditEvent::record($request, 'scan_blocked', ['reason' => 'rate_limit']);

            return response()->json(['error' => 'Scan limit reached. Try again later.'], 429);
        }
        RateLimiter::hit($rateKey, 600);
        $scan = Scan::create([
            'user_id' => $request->user()?->id,
            'target' => $input,
            'host' => strtolower((string) (parse_url(str_contains($input, '://') ? $input : 'https://'.$input, PHP_URL_HOST) ?: 'invalid')),
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'status' => 'started',
        ]);
        AuditEvent::record($request, 'scan_started', ['scan_id' => $scan->id, 'host' => $scan->host]);

        try {
            Net::parseUrl($input);
            $data = File::json(resource_path('data/advisories.json'), JSON_THROW_ON_ERROR);
            $report = (new Scanner($data))->scan($input);
            $scan->update([
                'host' => parse_url($report['target'], PHP_URL_HOST),
                'status' => 'completed',
                'http_status' => $report['status'],
                'grade' => $report['score']['grade'],
                'report' => $report,
            ]);
            AuditEvent::record($request, 'scan_completed', ['scan_id' => $scan->id, 'host' => $scan->host, 'grade' => $scan->grade]);
            try {
                MonitoredSite::query()
                    ->with('user')
                    ->where('domain', $scan->host)
                    ->whereNotNull('verified_at')
                    ->where('alerts_enabled', true)
                    ->get()
                    ->each(function (MonitoredSite $site) use ($scan): void {
                        if (! $site->hasValidDnsRecord()) {
                            $site->update(['verified_at' => null]);

                            return;
                        }
                        if ($site->user && $site->user_id !== $scan->user_id) {
                            $site->user->notify(new SiteScanned($scan->host, $scan->target, $scan->grade, $scan->id));
                        }
                    });
            } catch (\Throwable $e) {
                Log::warning('Scan alert could not be queued', ['scan_id' => $scan->id, 'exception' => $e]);
            }

            return response()->json(['id' => $scan->id, 'report' => $report]);
        } catch (ScanException $e) {
            $scan->update(['status' => 'failed', 'error' => $e->getMessage()]);
            AuditEvent::record($request, 'scan_failed', ['scan_id' => $scan->id, 'reason' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], $e->status);
        } catch (\Throwable $e) {
            Log::error('Scan failed', ['scan_id' => $scan->id, 'exception' => $e]);
            $scan->update(['status' => 'failed', 'error' => 'Internal scan error']);
            AuditEvent::record($request, 'scan_failed', ['scan_id' => $scan->id, 'reason' => 'internal_error']);

            return response()->json(['error' => 'The scan could not be completed.'], 500);
        }
    }
}
