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

    public function test_compose_stub_relies_on_the_image_built_by_sail(): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/compose.stub');

        $this->assertStringNotContainsString('        build:', $contents);
        $this->assertStringContainsString("        image: '\${SAIL_BUILD_ORGANIZATION}/laravel:\${SAIL_BUILD_VERSION}'", $contents);
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

    public function test_write_project_env_writes_build_vars_matching_compose_image(): void
    {
        $base = sys_get_temp_dir().'/sail-buildvars-'.uniqid();
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
        $org = (string) config('sail.build.organization', 'reyemtech');
        $version = (string) config('sail.build.version', '1.0.0');

        // Both vars must be present so compose's ${SAIL_BUILD_ORGANIZATION}/laravel:${SAIL_BUILD_VERSION}
        // renders a valid reference (never a blank "/laravel:") — and matches bin/sail's bake tag.
        $this->assertMatchesRegularExpression('/^SAIL_BUILD_ORGANIZATION="?'.preg_quote($org, '/').'"?$/m', $env);
        $this->assertMatchesRegularExpression('/^SAIL_BUILD_VERSION="?'.preg_quote($version, '/').'"?$/m', $env);

        // The compose stub's literal image line, interpolated with these vars, must
        // yield the exact tag bin/sail bakes: $org/laravel:$version.
        $stub = file_get_contents(__DIR__.'/../../stubs/compose.stub');
        $this->assertStringContainsString('${SAIL_BUILD_ORGANIZATION}/laravel:${SAIL_BUILD_VERSION}', $stub);

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($base);
    }

    private function makeEnsureBindIpCommand()
    {
        $command = new class extends \Illuminate\Console\Command {
            use \Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;
            public function option($key = null) { return '8.5'; }
            public function call($command, array $arguments = []) { return 0; }
        };
        $command->setLaravel($this->app);

        return $command;
    }

    private function callEnsureBindIp($command): array
    {
        $method = new \ReflectionMethod($command, 'ensureBindIp');
        $method->setAccessible(true);

        return $method->invoke($command);
    }

    public function test_ensure_bind_ip_seeds_from_existing_sail_ip(): void
    {
        $base = sys_get_temp_dir().'/sail-ensurebindip-'.uniqid();
        mkdir($base, 0755, true);
        file_put_contents($base.'/.env', "APP_NAME=TestApp\nSAIL_IP=172.20.0.11\n");
        $this->app->setBasePath($base);

        $result = $this->callEnsureBindIp($this->makeEnsureBindIpCommand());

        $this->assertSame(['ip' => '172.20.0.11', 'seeded' => true], $result);
        $env = file_get_contents($base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.11"', $env);

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($base);
    }

    public function test_ensure_bind_ip_does_not_overwrite_existing_bind_ip(): void
    {
        $base = sys_get_temp_dir().'/sail-ensurebindip-'.uniqid();
        mkdir($base, 0755, true);
        file_put_contents($base.'/.env', "APP_NAME=TestApp\nSAIL_IP=172.20.0.11\nSAIL_BIND_IP=172.20.0.99\n");
        $this->app->setBasePath($base);

        $result = $this->callEnsureBindIp($this->makeEnsureBindIpCommand());

        $this->assertSame(['ip' => '172.20.0.99', 'seeded' => false], $result);
        $env = file_get_contents($base.'/.env');
        $this->assertSame(1, substr_count($env, 'SAIL_BIND_IP='));
        $this->assertStringContainsString('SAIL_BIND_IP=172.20.0.99', $env);

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($base);
    }

    public function test_ensure_bind_ip_falls_back_to_config_default(): void
    {
        $base = sys_get_temp_dir().'/sail-ensurebindip-'.uniqid();
        mkdir($base, 0755, true);
        file_put_contents($base.'/.env', "APP_NAME=TestApp\n");
        $this->app->setBasePath($base);

        $result = $this->callEnsureBindIp($this->makeEnsureBindIpCommand());

        $this->assertSame(['ip' => '172.20.0.10', 'seeded' => true], $result);
        $env = file_get_contents($base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.10"', $env);

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($base);
    }
}
