<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MonitoredSiteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/challenge', [PageController::class, 'challenge'])->middleware('throttle:30,10')->name('challenge');
Route::post('/scans', [ScanController::class, 'store'])->name('scans.store');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:3,60')->name('contact.store');
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::put('/settings', [AdminController::class, 'update'])->name('settings.update');
    Route::get('/scans/{scan}', [AdminController::class, 'show'])->name('scans.show');
});
Route::middleware(['auth', 'verified'])->prefix('monitored-sites')->name('monitored-sites.')->group(function () {
    Route::get('/', [MonitoredSiteController::class, 'index'])->name('index');
    Route::post('/', [MonitoredSiteController::class, 'store'])->name('store');
    Route::post('/{site}/verify', [MonitoredSiteController::class, 'verify'])->middleware('throttle:5,1')->name('verify');
    Route::put('/{site}', [MonitoredSiteController::class, 'update'])->name('update');
    Route::delete('/{site}', [MonitoredSiteController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
});

require __DIR__.'/settings.php';
