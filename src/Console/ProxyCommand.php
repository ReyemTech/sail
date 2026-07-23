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
            'status' => $this->status($home, $stack),
            default => $this->invalidAction(),
        };
    }

    private function up(SailHome $home, string $stack): int
    {
        $home->ensureDirectories();

        $bindIp = $this->bindIp();
        file_put_contents($stack, (new SharedProxyStack($bindIp, $home->certsDir()))->proxyCompose());

        $this->runProcess(['docker', 'network', 'create', 'sail-shared']);
        $code = $this->runProcess(['docker', 'compose', '-f', $stack, 'up', '-d']);

        $this->components->info("Shared proxy up on {$bindIp}:80/443.");

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function down(string $stack): int
    {
        if (is_file($stack)) {
            $this->runProcess(['docker', 'compose', '-f', $stack, 'down']);
        }

        $this->components->info('Shared proxy stopped.');

        return self::SUCCESS;
    }

    private function status(SailHome $home, string $stack): int
    {
        $this->components->twoColumnDetail('Stack file', is_file($stack) ? $stack : '(not written)');
        $this->components->twoColumnDetail('Shared network', 'sail-shared');

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

    protected function runProcess(array $cmd): int
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }
}
