<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Laravel\Sail\Networking\SailHome;
use Laravel\Sail\Networking\SharedProxyStack;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'sail:proxy')]
class ProxyCommand extends Command
{
    protected $signature = 'sail:proxy {action=up : up, down, or status}';

    protected $description = 'Manage the shared host-level nginx-proxy for LAN mode';

    public function handle(): int
    {
        $home = new SailHome;
        $stack = $home->proxyDir().'/docker-compose.yml';

        return match ($this->argument('action')) {
            'up' => $this->up($home, $stack),
            'down' => $this->down($stack),
            'status' => $this->status($stack),
            default => $this->invalidAction(),
        };
    }

    private function up(SailHome $home, string $stack): int
    {
        $home->ensureDirectories();

        $bindIp = $this->bindIp();
        file_put_contents($stack, (new SharedProxyStack($bindIp, $home->certsDir()))->proxyCompose());

        $docker = $this->dockerBinary();
        $this->runProcess([$docker, 'network', 'create', 'sail-shared']);
        $code = $this->runProcess([$docker, 'compose', '-f', $stack, 'up', '-d']);

        $this->components->info("Shared proxy up on {$bindIp}:80/443.");

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function down(string $stack): int
    {
        if (! is_file($stack)) {
            $this->components->info('Shared proxy is not running.');

            return self::SUCCESS;
        }

        $this->runProcess([$this->dockerBinary(), 'compose', '-f', $stack, 'down']);

        $this->components->info('Shared proxy stopped.');

        return self::SUCCESS;
    }

    private function status(string $stack): int
    {
        $this->components->twoColumnDetail('Stack file', is_file($stack) ? $stack : '(not written)');

        $exists = $this->processSucceeds([$this->dockerBinary(), 'network', 'inspect', 'sail-shared']);
        $this->components->twoColumnDetail('Shared network (sail-shared)', $exists ? 'present' : 'absent');

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('Unknown action. Use: up, down, status.');

        return self::FAILURE;
    }

    private function bindIp(): string
    {
        $envPath = $this->laravel->basePath('.env');
        if (is_file($envPath) && preg_match('/^SAIL_BIND_IP=(.*)$/m', file_get_contents($envPath), $m)) {
            return trim($m[1], " \"'");
        }

        return (string) config('sail.network.bind_ip', '172.20.0.10');
    }

    /**
     * The container CLI to shell out to (docker, or podman via SAIL_DOCKER_BINARY,
     * matching bin/sail's exported default).
     */
    protected function dockerBinary(): string
    {
        return getenv('SAIL_DOCKER_BINARY') ?: 'docker';
    }

    protected function runProcess(array $cmd): int
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }

    protected function processSucceeds(array $cmd): bool
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run(); // quiet — do NOT stream output (avoid dumping docker inspect JSON)

        return $process->getExitCode() === 0;
    }
}
