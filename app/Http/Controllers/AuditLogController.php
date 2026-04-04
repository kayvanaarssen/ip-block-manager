<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index()
    {
        return Inertia::render('AuditLog/Index', [
            'logs' => AuditLog::with('user')
                ->latest()
                ->paginate(50)
                ->through(fn ($log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name,
                    'target_type' => $log->target_type ? class_basename($log->target_type) : null,
                    'target_id' => $log->target_id,
                    'metadata' => $log->metadata,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ]),
        ]);
    }
}
