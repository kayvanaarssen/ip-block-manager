<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBlockedIpRequest;
use App\Models\BlockedIp;
use App\Models\Server;
use App\Services\IpBlockService;
use Inertia\Inertia;

class BlockedIpController extends Controller
{
    public function index()
    {
        return Inertia::render('BlockedIps/Index', [
            'blockedIps' => BlockedIp::with(['blockedByUser', 'servers'])
                ->latest()
                ->paginate(25)
                ->through(fn ($ip) => [
                    'id' => $ip->id,
                    'ip_address' => $ip->ip_address,
                    'reason' => $ip->reason,
                    'blocked_by' => $ip->blockedByUser?->name,
                    'created_at' => $ip->created_at->diffForHumans(),
                    'servers' => $ip->servers->map(fn ($s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'status' => $s->pivot->status,
                        'error_message' => $s->pivot->error_message,
                    ]),
                ]),
            'allServers' => Server::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => request()->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('BlockedIps/Create', [
            'servers' => Server::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'host']),
        ]);
    }

    public function store(StoreBlockedIpRequest $request, IpBlockService $ipBlockService)
    {
        $servers = Server::whereIn('id', $request->server_ids)->get();
        $blockedIp = $ipBlockService->blockOnServers(
            $request->ip_address,
            $servers,
            $request->reason,
        );

        return redirect()->route('blocked-ips.show', $blockedIp)
            ->with('success', "Blocking {$request->ip_address} on {$servers->count()} server(s)...");
    }

    public function show(BlockedIp $blockedIp)
    {
        $attachedServerIds = $blockedIp->servers->pluck('id')->toArray();

        return Inertia::render('BlockedIps/Show', [
            'blockedIp' => [
                'id' => $blockedIp->id,
                'ip_address' => $blockedIp->ip_address,
                'reason' => $blockedIp->reason,
                'blocked_by' => $blockedIp->blockedByUser?->name,
                'created_at' => $blockedIp->created_at->format('Y-m-d H:i:s'),
                'servers' => $blockedIp->servers->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'host' => $s->host,
                    'status' => $s->pivot->status,
                    'error_message' => $s->pivot->error_message,
                    'blocked_at' => $s->pivot->blocked_at,
                    'unblocked_at' => $s->pivot->unblocked_at,
                ]),
            ],
            'allServers' => Server::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function destroy(BlockedIp $blockedIp, IpBlockService $ipBlockService)
    {
        $ipBlockService->unblockFromAllServers($blockedIp);
        return redirect()->route('blocked-ips.index')
            ->with('success', "Unblocking {$blockedIp->ip_address} from all servers...");
    }

    public function unblock(BlockedIp $blockedIp, IpBlockService $ipBlockService)
    {
        $serverIds = request()->validate([
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'exists:servers,id'],
        ])['server_ids'];

        $servers = Server::whereIn('id', $serverIds)->get();
        $ipBlockService->unblockFromServers($blockedIp, $servers);

        return back()->with('success', "Unblocking from {$servers->count()} server(s)...");
    }

    public function status(BlockedIp $blockedIp)
    {
        return response()->json([
            'servers' => $blockedIp->servers->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'status' => $s->pivot->status,
                'error_message' => $s->pivot->error_message,
                'blocked_at' => $s->pivot->blocked_at,
                'unblocked_at' => $s->pivot->unblocked_at,
            ]),
        ]);
    }

    /**
     * Remove the blocked IP entry from the database without triggering SSH unblock.
     * Useful for cleaning up orphaned/failed entries.
     */
    public function forceDelete(BlockedIp $blockedIp)
    {
        $ip = $blockedIp->ip_address;
        $blockedIp->servers()->detach();
        $blockedIp->delete();

        return redirect()->route('blocked-ips.index')
            ->with('success', "Removed {$ip} entry from the database.");
    }

    /**
     * Unblock from a single server (called from the index page).
     */
    public function unblockServer(BlockedIp $blockedIp, Server $server, IpBlockService $ipBlockService)
    {
        $ipBlockService->unblockFromServers($blockedIp, collect([$server]));

        return back()->with('success', "Unblocking {$blockedIp->ip_address} from {$server->name}...");
    }

    /**
     * Re-block on a single server.
     */
    public function blockServer(BlockedIp $blockedIp, Server $server, IpBlockService $ipBlockService)
    {
        $ipBlockService->reblockOnServers($blockedIp, collect([$server]));

        return back()->with('success', "Blocking {$blockedIp->ip_address} on {$server->name}...");
    }

    /**
     * Block on selected servers (or all active servers).
     */
    public function block(BlockedIp $blockedIp, IpBlockService $ipBlockService)
    {
        $serverIds = request()->validate([
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'exists:servers,id'],
        ])['server_ids'];

        $servers = Server::whereIn('id', $serverIds)->get();
        $ipBlockService->reblockOnServers($blockedIp, $servers);

        return back()->with('success', "Blocking {$blockedIp->ip_address} on {$servers->count()} server(s)...");
    }
}
