<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\Server;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('Dashboard', [
            'stats' => [
                'totalServers' => Server::where('is_active', true)->count(),
                'activelyBlockedIps' => BlockedIp::whereHas('servers', fn ($q) => $q->where('blocked_ip_server.status', 'blocked'))->count(),
                'pendingIps' => BlockedIp::whereHas('servers', fn ($q) => $q->whereIn('blocked_ip_server.status', ['pending', 'blocking']))->count(),
                'failedIps' => BlockedIp::whereHas('servers', fn ($q) => $q->where('blocked_ip_server.status', 'failed'))->count(),
                'totalBlocks' => BlockedIp::count(),
                'recentActivity' => AuditLog::with('user')
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(fn ($log) => [
                        'id' => $log->id,
                        'action' => $log->action,
                        'user' => $log->user?->name,
                        'metadata' => $log->metadata,
                        'ip_address' => $log->ip_address,
                        'created_at' => $log->created_at->diffForHumans(),
                    ]),
            ],
        ]);
    }
}
