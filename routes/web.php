<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlockedIpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Passkey authentication routes (provided by spatie/laravel-passkeys)
Route::passkeys();

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Passkey management
    Route::get('/passkeys/register-options', [PasskeyController::class, 'registerOptions'])->name('passkeys.register-options');
    Route::post('/passkeys', [PasskeyController::class, 'store'])->name('passkeys.store');
    Route::delete('/passkeys/{passkey}', [PasskeyController::class, 'destroy'])->name('passkeys.destroy');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Servers
    Route::resource('servers', ServerController::class);
    Route::post('/servers/generate-key-preview', [ServerController::class, 'generateKeyPreview'])->name('servers.generate-key-preview');
    Route::post('/servers/{server}/test', [ServerController::class, 'testConnection'])->name('servers.test');
    Route::post('/servers/{server}/check-script', [ServerController::class, 'checkScript'])->name('servers.check-script');
    Route::post('/servers/{server}/install-script', [ServerController::class, 'installScript'])->name('servers.install-script');
    Route::post('/servers/{server}/sync', [ServerController::class, 'sync'])->name('servers.sync');
    Route::post('/servers/{server}/generate-key', [ServerController::class, 'generateKey'])->name('servers.generate-key');
    Route::get('/servers/{server}/public-key', [ServerController::class, 'publicKey'])->name('servers.public-key');

    // Blocked IPs
    Route::resource('blocked-ips', BlockedIpController::class)->parameters(['blocked-ips' => 'blockedIp']);
    Route::post('/blocked-ips/{blockedIp}/unblock', [BlockedIpController::class, 'unblock'])->name('blocked-ips.unblock');
    Route::get('/blocked-ips/{blockedIp}/status', [BlockedIpController::class, 'status'])->name('blocked-ips.status');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');

    // Users management
    Route::resource('users', UserController::class)->except(['show']);

    // Audit Log
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
});
