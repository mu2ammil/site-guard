<?php

use App\Models\AuditEvent;
use App\Models\ContactRequest;
use App\Models\Scan;
use App\Models\SiteSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    $days = max(1, (int) SiteSetting::read('retention_days', '90'));
    Scan::where('created_at', '<', now()->subDays($days))->delete();
    AuditEvent::where('created_at', '<', now()->subDays($days))->delete();
    ContactRequest::where('created_at', '<', now()->subDays($days))->delete();
})->daily()->name('siteguard-prune-scans')->withoutOverlapping();
