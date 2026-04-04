<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BlockedIpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\PasskeyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
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

    // Server bulk actions (must be before resource route)
    Route::post('/servers/bulk/update-script', [ServerController::class, 'bulkUpdateScript'])->name('servers.bulk-update-script');
    Route::post('/servers/bulk/test-connection', [ServerController::class, 'bulkTestConnection'])->name('servers.bulk-test-connection');
    Route::post('/servers/bulk/delete', [ServerController::class, 'bulkDelete'])->name('servers.bulk-delete');
    Route::post('/servers/generate-key-preview', [ServerController::class, 'generateKeyPreview'])->name('servers.generate-key-preview');

    // Servers
    Route::resource('servers', ServerController::class);
    Route::post('/servers/{server}/test', [ServerController::class, 'testConnection'])->name('servers.test');
    Route::post('/servers/{server}/check-script', [ServerController::class, 'checkScript'])->name('servers.check-script');
    Route::post('/servers/{server}/install-script', [ServerController::class, 'installScript'])->name('servers.install-script');
    Route::post('/servers/{server}/sync', [ServerController::class, 'sync'])->name('servers.sync');
    Route::post('/servers/{server}/update-script', [ServerController::class, 'updateScript'])->name('servers.update-script');
    Route::post('/servers/{server}/generate-key', [ServerController::class, 'generateKey'])->name('servers.generate-key');
    Route::get('/servers/{server}/public-key', [ServerController::class, 'publicKey'])->name('servers.public-key');

    // Blocked IPs
    Route::resource('blocked-ips', BlockedIpController::class)->parameters(['blocked-ips' => 'blockedIp']);
    Route::post('/blocked-ips/{blockedIp}/unblock', [BlockedIpController::class, 'unblock'])->name('blocked-ips.unblock');
    Route::post('/blocked-ips/{blockedIp}/unblock-server/{server}', [BlockedIpController::class, 'unblockServer'])->name('blocked-ips.unblock-server');
    Route::post('/blocked-ips/{blockedIp}/block', [BlockedIpController::class, 'block'])->name('blocked-ips.block');
    Route::post('/blocked-ips/{blockedIp}/block-server/{server}', [BlockedIpController::class, 'blockServer'])->name('blocked-ips.block-server');
    Route::delete('/blocked-ips/{blockedIp}/force-delete', [BlockedIpController::class, 'forceDelete'])->name('blocked-ips.force-delete');
    Route::get('/blocked-ips/{blockedIp}/status', [BlockedIpController::class, 'status'])->name('blocked-ips.status');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::post('/profile/telegram-token', [ProfileController::class, 'generateTelegramToken'])->name('profile.telegram-token');
    Route::delete('/profile/telegram', [ProfileController::class, 'unlinkTelegram'])->name('profile.unlink-telegram');

    // Users management
    Route::resource('users', UserController::class)->except(['show']);

    // Settings
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.remove-logo');

    // Audit Log
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
});
