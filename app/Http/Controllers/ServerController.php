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
                    'script_version' => $server->script_version,
                    'needs_update' => $server->script_installed && $server->script_version !== SshService::CURRENT_SCRIPT_VERSION,
                    'last_connected_at' => $server->last_connected_at?->diffForHumans(),
                    'blocked_ips_count' => $server->blocked_ips_count,
                ]),
            'currentScriptVersion' => SshService::CURRENT_SCRIPT_VERSION,
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
                'script_version' => $server->script_version,
                'needs_update' => $server->script_installed && $server->script_version !== SshService::CURRENT_SCRIPT_VERSION,
                'last_connected_at' => $server->last_connected_at?->diffForHumans(),
                'has_key' => (bool) $server->ssh_private_key,
                'has_public_key' => (bool) $server->ssh_public_key,
            ],
            'currentScriptVersion' => SshService::CURRENT_SCRIPT_VERSION,
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
            $version = $installed ? $sshService->getScriptVersion($server) : null;

            $server->update([
                'script_installed' => $installed,
                'script_version' => $version,
                'last_connected_at' => now(),
            ]);

            if (!$installed) {
                return back()->with('error', "Script is NOT installed on {$server->name}. Use 'Install Script' to deploy it.");
            }

            $needsUpdate = $version !== SshService::CURRENT_SCRIPT_VERSION;
            $msg = "Script v{$version} is installed on {$server->name}.";
            if ($needsUpdate) {
                $msg .= " Update available (v" . SshService::CURRENT_SCRIPT_VERSION . ").";
            }

            return back()->with($needsUpdate ? 'error' : 'success', $msg);
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

    public function updateScript(Server $server, SshService $sshService, AuditService $audit)
    {
        try {
            $result = $sshService->updateAndMigrate($server);
            $audit->log('update_script', $server, [
                'name' => $server->name,
                'version' => SshService::CURRENT_SCRIPT_VERSION,
            ]);

            return back()->with(
                $result['success'] ? 'success' : 'error',
                $result['success']
                    ? "Script updated and nginx migrated to 444 on {$server->name}."
                    : "Script updated but migration had issues: {$result['output']}"
            );
        } catch (\Throwable $e) {
            return back()->with('error', "Update failed on {$server->name}: {$e->getMessage()}");
        }
    }

    // Bulk actions
    public function bulkUpdateScript(SshService $sshService, AuditService $audit)
    {
        $validated = request()->validate([
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'exists:servers,id'],
        ]);

        $servers = Server::whereIn('id', $validated['server_ids'])->get();
        $results = ['success' => 0, 'failed' => 0];

        foreach ($servers as $server) {
            try {
                $sshService->updateAndMigrate($server);
                $audit->log('update_script', $server, ['name' => $server->name, 'version' => SshService::CURRENT_SCRIPT_VERSION]);
                $results['success']++;
            } catch (\Throwable $e) {
                $results['failed']++;
            }
        }

        $msg = "Updated {$results['success']} server(s).";
        if ($results['failed']) {
            $msg .= " {$results['failed']} failed.";
        }

        return back()->with($results['failed'] ? 'error' : 'success', $msg);
    }

    public function bulkTestConnection(SshService $sshService)
    {
        $validated = request()->validate([
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'exists:servers,id'],
        ]);

        $servers = Server::whereIn('id', $validated['server_ids'])->get();
        $results = ['success' => 0, 'failed' => 0];

        foreach ($servers as $server) {
            $result = $sshService->testConnection($server);
            $result['success'] ? $results['success']++ : $results['failed']++;
        }

        $msg = "{$results['success']} connected.";
        if ($results['failed']) {
            $msg .= " {$results['failed']} failed.";
        }

        return back()->with($results['failed'] ? 'error' : 'success', $msg);
    }

    public function bulkDelete(AuditService $audit)
    {
        $validated = request()->validate([
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'exists:servers,id'],
        ]);

        $servers = Server::whereIn('id', $validated['server_ids'])->get();
        $count = $servers->count();

        foreach ($servers as $server) {
            $audit->log('delete_server', $server, ['name' => $server->name, 'host' => $server->host]);
            $server->delete();
        }

        return back()->with('success', "Deleted {$count} server(s).");
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
