<?php

use App\Models\MonitoredSite;
use App\Models\Scan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Scan::create(['user_id' => $user->id, 'target' => 'https://mine.example', 'host' => 'mine.example', 'ip_address' => '8.8.8.8', 'status' => 'completed']);
    Scan::create(['user_id' => $other->id, 'target' => 'https://other.example', 'host' => 'other.example', 'ip_address' => '8.8.8.8', 'status' => 'completed']);
    MonitoredSite::create(['user_id' => $user->id, 'domain' => 'mine.example', 'verification_token' => 'example-token']);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('scans', 1)
        ->where('scans.0.host', 'mine.example')
        ->has('sites', 1)
        ->where('sites.0.domain', 'mine.example')
        ->where('isAdmin', false));
});
