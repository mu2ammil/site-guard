<?php

namespace App\Http\Controllers;

use App\Models\MonitoredSite;
use App\Models\Scan;
use App\Models\SiteSetting;
use App\Scanning\BotChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Welcome', [
            'site' => [
                'name' => SiteSetting::read('site_name', 'SiteGuard'),
                'tagline' => SiteSetting::read('site_tagline', 'See what your website reveals.'),
            ],
        ]);
    }

    public function privacy(): Response
    {
        return Inertia::render('Privacy', [
            'retentionDays' => SiteSetting::read('retention_days', '90'),
            'contactEmail' => SiteSetting::read('contact_email', (string) config('siteguard.contact_email')),
        ]);
    }

    public function challenge(BotChallenge $challenge): JsonResponse
    {
        return response()->json($challenge->issue());
    }

    public function dashboard(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'scans' => Scan::query()->where('user_id', $user->id)->latest()->limit(12)->get(['id', 'target', 'host', 'grade', 'status', 'created_at']),
            'sites' => MonitoredSite::query()->where('user_id', $user->id)->latest()->get(['id', 'domain', 'verified_at', 'alerts_enabled']),
            'isAdmin' => (bool) $user->is_admin,
        ]);
    }
}
