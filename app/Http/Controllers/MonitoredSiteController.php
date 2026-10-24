<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\MonitoredSite;
use App\Scanning\Net;
use App\Scanning\ScanException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MonitoredSiteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('MonitoredSites', [
            'sites' => MonitoredSite::where('user_id', $request->user()->id)->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['domain' => ['required', 'string', 'max:253']]);
        try {
            $domain = Net::parseUrl($data['domain'])['host'];
        } catch (ScanException $e) {
            return back()->withErrors(['domain' => $e->getMessage()]);
        }
        MonitoredSite::firstOrCreate(
            ['user_id' => $request->user()->id, 'domain' => $domain],
            ['verification_token' => 'siteguard-verify='.Str::random(48)],
        );
        AuditEvent::record($request, 'site_added', ['domain' => $domain]);

        return back();
    }

    public function verify(Request $request, MonitoredSite $site): RedirectResponse
    {
        abort_unless($site->user_id === $request->user()->id, 403);
        if (! $site->hasValidDnsRecord()) {
            return back()->withErrors(['verification' => 'TXT record not found yet. DNS changes may take time to propagate.']);
        }
        $site->update(['verified_at' => now()]);
        AuditEvent::record($request, 'site_verified', ['domain' => $site->domain]);

        return back();
    }

    public function update(Request $request, MonitoredSite $site): RedirectResponse
    {
        abort_unless($site->user_id === $request->user()->id, 403);
        $site->update($request->validate(['alerts_enabled' => ['required', 'boolean']]));
        AuditEvent::record($request, 'site_alerts_updated', ['domain' => $site->domain, 'enabled' => $site->alerts_enabled]);

        return back();
    }

    public function destroy(Request $request, MonitoredSite $site): RedirectResponse
    {
        abort_unless($site->user_id === $request->user()->id, 403);
        $site->delete();
        AuditEvent::record($request, 'site_removed', ['domain' => $site->domain]);

        return back();
    }
}
