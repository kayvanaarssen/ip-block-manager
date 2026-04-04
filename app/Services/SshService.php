<?php

namespace App\Services;

use App\Models\Server;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use RuntimeException;

class SshService
{
    private const SCRIPT_PATH = '/root/ip_blocks/blockip.sh';
    private const CONNECT_TIMEOUT = 10;
    private const EXEC_TIMEOUT = 30;

    public function connect(Server $server): SSH2
    {
        $ssh = new SSH2($server->host, $server->port, self::CONNECT_TIMEOUT);

        $key = PublicKeyLoader::load($server->ssh_private_key);

        if (!$ssh->login($server->ssh_user, $key)) {
            throw new RuntimeException("SSH authentication failed for {$server->host}");
        }

        $ssh->setTimeout(self::EXEC_TIMEOUT);

        return $ssh;
    }

    public function execute(Server $server, string $command): array
    {
        $ssh = $this->connect($server);

        $output = $ssh->exec($command);
        $exitCode = $ssh->getExitStatus();

        $ssh->disconnect();

        return [
            'output' => trim($output ?? ''),
            'exit_code' => $exitCode,
            'success' => $exitCode === 0,
        ];
    }

    public function blockIp(Server $server, string $ip): array
    {
        $safeIp = escapeshellarg($ip);
        return $this->execute($server, self::SCRIPT_PATH . " --block {$safeIp}");
    }

    public function unblockIp(Server $server, string $ip): array
    {
        $safeIp = escapeshellarg($ip);
        return $this->execute($server, self::SCRIPT_PATH . " --unblock {$safeIp}");
    }

    public function listBlockedIps(Server $server): array
    {
        return $this->execute($server, self::SCRIPT_PATH . ' --list');
    }

    public function checkStatus(Server $server, string $ip): array
    {
        $safeIp = escapeshellarg($ip);
        return $this->execute($server, self::SCRIPT_PATH . " --status {$safeIp}");
    }

    public function isScriptInstalled(Server $server): bool
    {
        $result = $this->execute($server, 'test -f ' . self::SCRIPT_PATH . ' && echo "EXISTS" || echo "MISSING"');
        return str_contains($result['output'], 'EXISTS');
    }

    public function installScript(Server $server): bool
    {
        $localScript = storage_path('app/blockip.sh');

        if (!file_exists($localScript)) {
            throw new RuntimeException('blockip.sh not found in storage/app/');
        }

        $sftp = new SFTP($server->host, $server->port, self::CONNECT_TIMEOUT);
        $key = PublicKeyLoader::load($server->ssh_private_key);

        if (!$sftp->login($server->ssh_user, $key)) {
            throw new RuntimeException("SFTP authentication failed for {$server->host}");
        }

        // Create directory
        $sftp->mkdir('/root/ip_blocks', 0700, true);

        // Upload script
        $scriptContent = file_get_contents($localScript);
        if (!$sftp->put(self::SCRIPT_PATH, $scriptContent)) {
            throw new RuntimeException("Failed to upload blockip.sh to {$server->host}");
        }

        $sftp->chmod(0755, self::SCRIPT_PATH);
        $sftp->disconnect();

        // Verify
        $result = $this->execute($server, self::SCRIPT_PATH . ' --help');

        $server->update([
            'script_installed' => $result['success'],
            'last_connected_at' => now(),
        ]);

        return $result['success'];
    }

    public function generateKeyPair(Server $server): array
    {
        $rsa = \phpseclib3\Crypt\RSA::createKey(4096);

        $privateKey = $rsa->toString('OpenSSH');
        $publicKey = $rsa->getPublicKey()->toString('OpenSSH', ['comment' => 'IPBlockManager-' . $server->name]);

        $server->update([
            'ssh_private_key' => $privateKey,
            'ssh_public_key' => $publicKey,
        ]);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public function generateKeyPairPreview(): array
    {
        $rsa = \phpseclib3\Crypt\RSA::createKey(4096);

        $privateKey = $rsa->toString('OpenSSH');
        $publicKey = $rsa->getPublicKey()->toString('OpenSSH', ['comment' => 'IPBlockManager']);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    public function getAuthorizedKeysCommand(Server $server): string
    {
        if (!$server->ssh_public_key) {
            return '';
        }

        return $this->getAuthorizedKeysCommandFromKey($server->ssh_public_key);
    }

    public function getAuthorizedKeysCommandFromKey(string $publicKey): string
    {
        return sprintf(
            'mkdir -p ~/.ssh && echo %s >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys',
            escapeshellarg($publicKey)
        );
    }

    public function testConnection(Server $server): array
    {
        try {
            $result = $this->execute($server, 'echo "OK" && hostname && uname -a');
            $server->update(['last_connected_at' => now()]);
            return [
                'success' => true,
                'output' => $result['output'],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'output' => $e->getMessage(),
            ];
        }
    }
}
