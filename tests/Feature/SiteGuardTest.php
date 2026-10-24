<?php

use App\Models\MonitoredSite;
use App\Models\Scan;
use App\Models\SiteSetting;
use App\Models\User;
use App\Scanning\BotChallenge;
use App\Scanning\Net;
use App\Scanning\Scan\HeaderChecks;

test('the scanner rejects private and internal targets and records the attempt', function () {
    config()->set('siteguard.challenge_bits', 0);
    $challenge = app(BotChallenge::class)->issue();
    $this->postJson(route('scans.store'), ['url' => 'http://localhost', 'token' => $challenge['token'], 'nonce' => '0'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Enter a public domain name. IP addresses and internal hostnames are not scanned.');

    expect(Scan::count())->toBe(1)
        ->and(Scan::first()->status)->toBe('failed');
});

test('a browser challenge cannot be reused', function () {
    config()->set('siteguard.challenge_bits', 0);
    $challenge = app(BotChallenge::class)->issue();
    $service = app(BotChallenge::class);

    expect($service->verify($challenge['token'], '0'))->toBeTrue()
        ->and($service->verify($challenge['token'], '0'))->toBeFalse();
});

test('reserved network ranges are never considered public', function () {
    expect(Net::isPublicIp('127.0.0.1'))->toBeFalse()
        ->and(Net::isPublicIp('169.254.169.254'))->toBeFalse()
        ->and(Net::isPublicIp('::1'))->toBeFalse()
        ->and(Net::isPublicIp('8.8.8.8'))->toBeTrue();
});

test('only admins can open scan activity and site settings', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
    $this->actingAs($user)->put(route('admin.settings.update'), [
        'scan_enabled' => true, 'scan_limit' => 10, 'retention_days' => 90,
    ])->assertForbidden();

    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin)->get(route('admin.index'))->assertOk();
});

test('admin settings can pause public scans', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_admin' => true])->save();
    $this->actingAs($admin)->put(route('admin.settings.update'), [
        'scan_enabled' => false,
        'scan_limit' => 10,
        'retention_days' => 90,
        'site_name' => 'SiteGuard',
        'site_tagline' => 'Website security scan',
        'contact_email' => '',
    ])->assertRedirect();

    expect(SiteSetting::read('scan_enabled', '1'))->toBe('');
    config()->set('siteguard.challenge_bits', 0);
    $challenge = app(BotChallenge::class)->issue();
    $this->postJson(route('scans.store'), [
        'url' => 'example.com', 'token' => $challenge['token'], 'nonce' => '0',
    ])->assertStatus(503);
});

test('a user cannot manage another owners monitored domain', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $site = MonitoredSite::create([
        'user_id' => $owner->id,
        'domain' => 'example.com',
        'verification_token' => 'siteguard-verify=test',
    ]);

    $this->actingAs($visitor)->put(route('monitored-sites.update', $site), ['alerts_enabled' => false])->assertForbidden();
});

test('broad CSP and invalid frame headers are not treated as protections', function () {
    $target = Net::parseUrl('https://example.com');
    $headers = ['content-security-policy' => 'default-src *', 'x-frame-options' => 'ALLOWALL', 'access-control-allow-origin' => '*'];
    [$checks] = (new HeaderChecks)->run($target, $target, ['status' => 200, 'headers' => [], 'body' => '', 'ms' => 1], $headers, null, []);
    $states = collect($checks)->pluck('state', 'id');

    expect($states['csp'])->toBe('warn')
        ->and($states['frame'])->toBe('fail')
        ->and($states['cors'])->toBe('info');
});
