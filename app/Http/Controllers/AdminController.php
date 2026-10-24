<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ContactRequest;
use App\Models\Scan;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);
        $filters = $request->validate([
            'host' => ['nullable', 'string', 'max:253'],
            'ip' => ['nullable', 'ip'],
            'status' => ['nullable', Rule::in(['started', 'completed', 'failed'])],
        ]);
        $query = Scan::query()
            ->when($filters['host'] ?? null, fn ($query, $host) => $query->where('host', 'like', '%'.$host.'%'))
            ->when($filters['ip'] ?? null, fn ($query, $ip) => $query->where('ip_address', $ip))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        return Inertia::render('admin/Index', [
            'scans' => $query->latest()->paginate(30, ['*'], 'scans_page')->withQueryString()->through(fn (Scan $scan) => $scan->only(['id', 'created_at', 'target', 'host', 'ip_address', 'user_agent', 'user_id', 'status', 'http_status', 'grade', 'error'])),
            'filters' => $filters,
            'events' => AuditEvent::query()->latest('created_at')->paginate(30, ['*'], 'events_page')->withQueryString(),
            'contacts' => ContactRequest::query()->latest()->paginate(15, ['*'], 'contacts_page')->withQueryString(),
            'counts' => [
                'total' => Scan::count(),
                'today' => Scan::where('created_at', '>=', now()->startOfDay())->count(),
                'failed' => Scan::where('status', 'failed')->count(),
                'blocked' => AuditEvent::where('type', 'scan_blocked')->where('created_at', '>=', now()->subDay())->count(),
                'failed_logins' => AuditEvent::where('type', 'login_failed')->where('created_at', '>=', now()->subDay())->count(),
                'visitors' => Scan::where('created_at', '>=', now()->subDay())->distinct()->count('ip_address'),
            ],
            'activeIps' => AuditEvent::query()
                ->select('ip_address')
                ->selectRaw('COUNT(*) as events')
                ->selectRaw("SUM(CASE WHEN type IN ('scan_blocked', 'login_failed') THEN 1 ELSE 0 END) as flagged")
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('ip_address')
                ->orderByDesc('flagged')
                ->orderByDesc('events')
                ->limit(10)
                ->get(),
            'settings' => [
                'scan_enabled' => SiteSetting::read('scan_enabled', '1'),
                'scan_limit' => SiteSetting::read('scan_limit', '10'),
                'retention_days' => SiteSetting::read('retention_days', '90'),
                'site_name' => SiteSetting::read('site_name', 'SiteGuard'),
                'site_tagline' => SiteSetting::read('site_tagline', 'See what your website reveals.'),
                'contact_email' => SiteSetting::read('contact_email', (string) config('siteguard.contact_email')),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate([
            'scan_enabled' => ['required', 'boolean'],
            'scan_limit' => ['required', 'integer', 'between:1,100'],
            'retention_days' => ['required', 'integer', 'between:1,3650'],
            'site_name' => ['required', 'string', 'max:60'],
            'site_tagline' => ['required', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:120'],
        ]);
        foreach ($data as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        AuditEvent::record($request, 'settings_updated', ['keys' => array_keys($data)]);

        return back()->with('success', 'Settings saved.');
    }

    public function show(Request $request, Scan $scan): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('admin/Show', ['scan' => $scan]);
    }
}
