<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:network')]
class NetworkCommand extends Command
{
    use InteractsWithDockerComposeServices;

    /**
     * @var string
     */
    protected $signature = 'sail:network
                {--status : Only report the current networking state}
                {--mode= : Set networking mode: local or lan}
                {--ip= : LAN IP to bind to (lan mode; auto-detected if omitted)}
                {--domain= : Override the domain (lan mode)}';

    /**
     * @var string
     */
    protected $description = 'Report and (re)allocate this machine\'s Sail networking configuration';

    public function handle(): int
    {
        $mode = (string) config('sail.network.mode', 'local');
        $bindIp = (string) config('sail.network.bind_ip', '172.20.0.10');

        if ($this->option('status')) {
            $this->components->twoColumnDetail('Mode', $mode);
            $this->components->twoColumnDetail('Bind IP', env('SAIL_BIND_IP', $bindIp));
            $this->components->twoColumnDetail('Domain', env('SAIL_DOMAIN', config('sail.domain')));

            return self::SUCCESS;
        }

        $envPath = $this->laravel->basePath('.env');

        if (! is_file($envPath)) {
            $this->components->error('No .env file found. Run "sail:install" first.');

            return self::FAILURE;
        }

        $mode = $this->option('mode');

        if ($mode === 'lan') {
            $values = $this->applyLanConfig($this->option('ip'), $this->option('domain'));
            $this->components->info("LAN mode configured: {$values['SAIL_DOMAIN']} -> {$values['SAIL_BIND_IP']}");

            return self::SUCCESS;
        }

        if ($mode === 'local') {
            $contents = file_get_contents($envPath);
            $bindIp = preg_match('/^SAIL_IP=(.*)$/m', $contents, $m)
                ? trim($m[1], " \"'")
                : (string) config('sail.network.bind_ip', '172.20.0.10');

            $writer = new \MirazMac\DotEnv\Writer($envPath);
            $writer->set('SAIL_BIND_IP', $bindIp);
            $writer->set('SAIL_NETWORK_MODE', 'local');
            $writer->set('COMPOSE_PROFILES', '');
            $writer->set('SAIL_FILES', '');
            $writer->write();

            (new \Laravel\Sail\Networking\HostRegistry((new \Laravel\Sail\Networking\SailHome)->registryPath()))
                ->release($this->resolveProjectName());

            $this->components->info("Local mode restored: SAIL_BIND_IP={$bindIp}");

            return self::SUCCESS;
        }

        $result = $this->ensureBindIp();

        if ($result['seeded']) {
            $this->components->info("Seeded SAIL_BIND_IP={$result['ip']} into .env.");
        } else {
            $this->components->info('Networking already configured; nothing to do.');
        }

        return self::SUCCESS;
    }
}
