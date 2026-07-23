<?php

namespace Laravel\Sail\Tests\Feature;

use Laravel\Sail\Tests\TestCase;

class BindIpStubTest extends TestCase
{
    public function test_no_stub_references_the_legacy_sail_ip_publish_var(): void
    {
        $stubDir = __DIR__.'/../../stubs';
        $offenders = [];

        foreach (glob($stubDir.'/*.stub') as $stub) {
            $contents = file_get_contents($stub);
            // The publish var must be SAIL_BIND_IP, never a bare ${SAIL_IP...} interpolation.
            if (preg_match('/\$\{SAIL_IP[:}]/', $contents)) {
                $offenders[] = basename($stub);
            }
        }

        $this->assertSame([], $offenders, 'These stubs still use ${SAIL_IP}: '.implode(', ', $offenders));
    }

    public function test_compose_stub_binds_ports_via_bind_ip(): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/compose.stub');

        $this->assertStringContainsString('${SAIL_BIND_IP:-172.20.0.10}:80:80', $contents);
        $this->assertStringContainsString('${SAIL_BIND_IP:-172.20.0.10}:443:443', $contents);
    }

    public function test_write_project_env_writes_bind_ip(): void
    {
        $base = sys_get_temp_dir().'/sail-bindip-'.uniqid();
        mkdir($base, 0755, true);
        file_put_contents($base.'/.env', "APP_NAME=TestApp\n");
        $this->app->setBasePath($base);

        $command = new class extends \Illuminate\Console\Command {
            use \Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;
            public function option($key = null) { return '8.5'; }
            public function call($command, array $arguments = []) { return 0; }
        };
        $command->setLaravel($this->app);

        $method = new \ReflectionMethod($command, 'writePorjectEnv');
        $method->setAccessible(true);
        $method->invoke($command, 'demo', '172.20.0.10', 'demo.test');

        $env = file_get_contents($base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.10"', $env);
        $this->assertStringContainsString('SAIL_SUBNET="172.20.0.0/24"', $env);

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($base);
    }
}
