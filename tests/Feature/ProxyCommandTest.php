<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class ProxyCommandTest extends TestCase
{
    private string $base;
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-proxy-'.uniqid();
        $this->home = $this->base.'/home';
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_BIND_IP=192.168.1.50\n");
        putenv('SAIL_HOME='.$this->home);
    }

    protected function tearDown(): void
    {
        putenv('SAIL_HOME');
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_up_writes_stack_and_issues_network_and_compose_commands(): void
    {
        // Test double logs each shelled command to a file (deterministic, no real Docker).
        $log = $this->base.'/cmds.log';
        $this->app->bind(\Laravel\Sail\Console\ProxyCommand::class, function () use ($log) {
            return new class($log) extends \Laravel\Sail\Console\ProxyCommand {
                private string $log;
                public function __construct(string $log) { $this->log = $log; parent::__construct(); }
                protected function runProcess(array $cmd): int { file_put_contents($this->log, implode(' ', $cmd)."\n", FILE_APPEND); return 0; }
            };
        });

        $this->artisan('sail:proxy', ['action' => 'up'])->assertSuccessful();

        $stack = $this->home.'/proxy/docker-compose.yml';
        $this->assertFileExists($stack);
        $this->assertStringContainsString('192.168.1.50:80:80', File::get($stack));

        $cmds = File::get($log);
        $this->assertStringContainsString('network create sail-shared', $cmds);
        $this->assertStringContainsString('compose -f '.$stack.' up -d', $cmds);
    }

    public function test_status_reports_network_present(): void
    {
        $this->app->bind(\Laravel\Sail\Console\ProxyCommand::class, function () {
            return new class extends \Laravel\Sail\Console\ProxyCommand {
                protected function processSucceeds(array $cmd): bool { return true; }
            };
        });

        $this->artisan('sail:proxy', ['action' => 'status'])
            ->expectsOutputToContain('present')
            ->assertSuccessful();
    }

    public function test_status_reports_network_absent(): void
    {
        $this->app->bind(\Laravel\Sail\Console\ProxyCommand::class, function () {
            return new class extends \Laravel\Sail\Console\ProxyCommand {
                protected function processSucceeds(array $cmd): bool { return false; }
            };
        });

        $this->artisan('sail:proxy', ['action' => 'status'])
            ->expectsOutputToContain('absent')
            ->assertSuccessful();
    }
}
