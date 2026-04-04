<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Jobs\InstallScriptJob;
use App\Models\Server;
use App\Services\AuditService;
use App\Services\SshService;
use Inertia\Inertia;

class ServerController extends Controller
{
    public function index()
    {
        return Inertia::render('Servers/Index', [
            'servers' => Server::withCount(['activeBlockedIps as blocked_ips_count'])
                ->latest()
                ->get()
                ->map(fn ($server) => [
                    'id' => $server->id,
                    'name' => $server->name,
                    'host' => $server->host,
                    'port' => $server->port,
                    'ssh_user' => $server->ssh_user,
                    'is_active' => $server->is_active,
                    'script_installed' => $server->script_installed,
                    'last_connected_at' => $server->last_connected_at?->diffForHumans(),
                    'blocked_ips_count' => $server->blocked_ips_count,
                ]),
        ]);
    }

    public function create()
    {
        return Inertia::render('Servers/Form');
    }

    public function store(StoreServerRequest $request, AuditService $audit)
    {
        $server = Server::create($request->validated());
        $audit->log('create_server', $server, ['name' => $server->name, 'host' => $server->host]);
        return redirect()->route('servers.edit', $server)->with('success', "Server '{$server->name}' added. Test the connection and check the script status below.");
    }

    public function show(Server $server)
    {
        return redirect()->route('servers.edit', $server);
    }

    public function edit(Server $server)
    {
        return Inertia::render('Servers/Form', [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'port' => $server->port,
                'ssh_user' => $server->ssh_user,
                'is_active' => $server->is_active,
                'script_installed' => $server->script_installed,
                'last_connected_at' => $server->last_connected_at?->diffForHumans(),
                'has_key' => (bool) $server->ssh_private_key,
                'has_public_key' => (bool) $server->ssh_public_key,
            ],
        ]);
    }

    public function update(UpdateServerRequest $request, Server $server, AuditService $audit)
    {
        $data = $request->validated();
        if (empty($data['ssh_private_key'])) {
            unset($data['ssh_private_key']);
        }
        $server->update($data);
        $audit->log('update_server', $server, ['name' => $server->name]);
        return redirect()->route('servers.index')->with('success', "Server '{$server->name}' updated.");
    }

    public function destroy(Server $server, AuditService $audit)
    {
        $name = $server->name;
        $audit->log('delete_server', $server, ['name' => $name, 'host' => $server->host]);
        $server->delete();
        return redirect()->route('servers.index')->with('success', "Server '{$name}' deleted.");
    }

    public function testConnection(Server $server, SshService $sshService)
    {
        $result = $sshService->testConnection($server);
        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? "Connected to {$server->name}: {$result['output']}" : "Connection failed: {$result['output']}"
        );
    }

    public function checkScript(Server $server, SshService $sshService)
    {
        try {
            $installed = $sshService->isScriptInstalled($server);
            $server->update([
                'script_installed' => $installed,
                'last_connected_at' => now(),
            ]);

            return back()->with(
                $installed ? 'success' : 'error',
                $installed ? "Script is installed on {$server->name}." : "Script is NOT installed on {$server->name}. Use 'Install Script' to deploy it."
            );
        } catch (\Throwable $e) {
            return back()->with('error', "Could not check script: {$e->getMessage()}");
        }
    }

    public function installScript(Server $server, AuditService $audit)
    {
        InstallScriptJob::dispatch($server);
        $audit->log('install_script', $server, ['name' => $server->name]);
        return back()->with('success', "Script installation queued for {$server->name}.");
    }

    public function generateKey(Server $server, SshService $sshService, AuditService $audit)
    {
        $result = $sshService->generateKeyPair($server);
        $audit->log('generate_ssh_key', $server, ['name' => $server->name]);

        return response()->json([
            'public_key' => $result['public_key'],
            'command' => $sshService->getAuthorizedKeysCommand($server),
        ]);
    }

    public function generateKeyPreview(SshService $sshService)
    {
        $result = $sshService->generateKeyPairPreview();

        return response()->json([
            'private_key' => $result['private_key'],
            'public_key' => $result['public_key'],
            'command' => $sshService->getAuthorizedKeysCommandFromKey($result['public_key']),
        ]);
    }

    public function publicKey(Server $server)
    {
        if (!$server->ssh_public_key) {
            return response()->json(['public_key' => null, 'command' => null]);
        }

        $sshService = app(SshService::class);

        return response()->json([
            'public_key' => $server->ssh_public_key,
            'command' => $sshService->getAuthorizedKeysCommand($server),
        ]);
    }

    public function sync(Server $server, SshService $sshService)
    {
        try {
            $result = $sshService->listBlockedIps($server);
            return back()->with('success', "Synced {$server->name}: {$result['output']}");
        } catch (\Throwable $e) {
            return back()->with('error', "Sync failed: {$e->getMessage()}");
        }
    }
}
