<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ContactRequest;
use App\Models\SiteSetting;
use App\Notifications\HelpRequested;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'site' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:1000'],
            'website' => ['nullable', 'max:0'],
        ]);
        $contact = ContactRequest::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'site' => $data['site'] ?? null,
            'message' => $data['message'],
            'ip_address' => (string) $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
        AuditEvent::record($request, 'contact_requested', ['contact_id' => $contact->id, 'site' => $contact->site]);
        if ($to = SiteSetting::read('contact_email', (string) config('siteguard.contact_email'))) {
            try {
                Notification::route('mail', $to)->notify(new HelpRequested(
                    $contact->id, $contact->name, $contact->email, $contact->site ?? '', $contact->message,
                ));
            } catch (\Throwable $e) {
                Log::warning('Contact notification could not be queued', ['contact_id' => $contact->id, 'exception' => $e]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
