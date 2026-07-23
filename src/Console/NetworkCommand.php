<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:network')]
class NetworkCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sail:network {--status : Only report the current networking state}';

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

        if (env('SAIL_BIND_IP') === null && ! str_contains(file_get_contents($envPath), 'SAIL_BIND_IP=')) {
            $envContent = file_get_contents($envPath);
            if (mb_substr($envContent, -1) !== PHP_EOL) {
                $envContent .= PHP_EOL;
            }
            file_put_contents($envPath, $envContent . "SAIL_BIND_IP={$bindIp}" . PHP_EOL);

            $this->components->info("Seeded SAIL_BIND_IP={$bindIp} into .env.");
        } else {
            $this->components->info('Networking already configured; nothing to do.');
        }

        return self::SUCCESS;
    }
}
