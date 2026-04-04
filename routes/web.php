<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\WebAuthnLoginController;
use App\Http\Controllers\Auth\WebAuthnRegisterController;
use App\Http\Controllers\BlockedIpController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    // WebAuthn login
    Route::post('/webauthn/login/options', [WebAuthnLoginController::class, 'options'])
        ->middleware('throttle:10,1');
    Route::post('/webauthn/login', [WebAuthnLoginController::class, 'login'])
        ->middleware('throttle:5,1');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // WebAuthn registration (must be authenticated)
    Route::post('/webauthn/register/options', [WebAuthnRegisterController::class, 'options']);
    Route::post('/webauthn/register', [WebAuthnRegisterController::class, 'register']);
    Route::delete('/webauthn/credentials/{credential}', [WebAuthnRegisterController::class, 'destroy'])
        ->name('webauthn.destroy');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Servers
    Route::resource('servers', ServerController::class);
    Route::post('/servers/{server}/test', [ServerController::class, 'testConnection'])->name('servers.test');
    Route::post('/servers/{server}/install-script', [ServerController::class, 'installScript'])->name('servers.install-script');
    Route::post('/servers/{server}/sync', [ServerController::class, 'sync'])->name('servers.sync');

    // Blocked IPs
    Route::resource('blocked-ips', BlockedIpController::class)->parameters(['blocked-ips' => 'blockedIp']);
    Route::post('/blocked-ips/{blockedIp}/unblock', [BlockedIpController::class, 'unblock'])->name('blocked-ips.unblock');
    Route::get('/blocked-ips/{blockedIp}/status', [BlockedIpController::class, 'status'])->name('blocked-ips.status');

    // Audit Log
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');
});
