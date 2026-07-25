<?php

namespace Laravel\Sail\Tests\Stubs;

use Illuminate\Console\Command;
use Laravel\Sail\Console\Concerns\InteractsWithDocker;

/**
 * Exercises the real buildDockerImages() wiring while stopping short of executing
 * anything, so tests can assert on the command string and the environment the
 * build process would have been given.
 */
class BuildProbeCommand extends Command
{
    use InteractsWithDocker;

    /**
     * @var string
     */
    protected $signature = 'sail-test:probe';

    /**
     * The commands and environment the build would have run with.
     *
     * @var array{commands: array<string>, env: array<string, string>}|null
     */
    public static ?array $captured = null;

    public function handle(): int
    {
        static::$captured = null;

        $this->useRepository = false;
        $this->push = false;
        $this->organization = 'testorg';

        return $this->buildDockerImages('production', ['linux/amd64'], 'none');
    }

    /**
     * The bake command that would have been executed.
     */
    public static function bakeCommand(): string
    {
        foreach (static::$captured['commands'] ?? [] as $command) {
            if (str_contains($command, 'docker buildx bake')) {
                return $command;
            }
        }

        return '';
    }

    /**
     * The environment the build process would have been given.
     *
     * @return array<string, string>
     */
    public static function env(): array
    {
        return static::$captured['env'] ?? [];
    }

    /**
     * {@inheritdoc}
     *
     * Overridden to capture rather than execute. This is the only seam in the test;
     * everything above it is the production code path.
     */
    protected function runCommands($commands, array $env = [])
    {
        static::$captured = ['commands' => $commands, 'env' => $env];

        return 0;
    }
}
