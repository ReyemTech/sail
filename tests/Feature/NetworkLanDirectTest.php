<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class NetworkLanDirectTest extends TestCase
{
    private string $base;
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-landirect-'.uniqid();
        $this->home = $this->base.'/home';
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
        putenv('SAIL_HOME='.$this->home);
        File::put($this->base.'/compose.yaml', "services:\n  laravel: {}\n  mysql: {}\n  redis: {}\n");
    }

    protected function tearDown(): void
    {
        putenv('SAIL_HOME');
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lan_direct_writes_bind_ip_mode_domain_url_without_sail_files(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_SUBNET=172.20.0.0/24\n");

        $this->artisan('sail:network', ['--mode' => 'lan-direct', '--ip' => '192.168.1.60'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.1.60"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE=lan-direct', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="alpha.192-168-1-60.nip.io"', $env);
        $this->assertStringContainsString('APP_URL="https://alpha.192-168-1-60.nip.io"', $env);
        // lan-direct uses a per-project proxy on standard ports: NO shared override,
        // NO forced port offsets, subnet untouched.
        $this->assertMatchesRegularExpression('/^SAIL_FILES=\s*("")?\s*$/m', $env);
        $this->assertStringNotContainsString('/overrides/alpha.yml', $env);
        $this->assertStringNotContainsString('FORWARD_DB_PORT', $env);
        $this->assertStringContainsString('SAIL_SUBNET=172.20.0.0/24', $env);
        // No per-project override file is generated.
        $this->assertFileDoesNotExist($this->home.'/overrides/alpha.yml');
    }

    public function test_lan_direct_requires_an_ip(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        // No --ip and the project isn't already lan-direct: must fail loudly.
        $this->artisan('sail:network', ['--mode' => 'lan-direct'])->assertFailed();

        $env = File::get($this->base.'/.env');
        $this->assertStringNotContainsString('SAIL_NETWORK_MODE=lan-direct', $env);
    }

    public function test_lan_direct_reuses_stored_bind_ip_on_rerun(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=lan-direct\nSAIL_BIND_IP=192.168.1.60\n");

        // Bare re-run, no --ip: reuse the stored lan-direct bind IP.
        $this->artisan('sail:network', ['--mode' => 'lan-direct'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.1.60"', $env);
        $this->assertStringContainsString('alpha.192-168-1-60.nip.io', $env);
    }

    public function test_lan_direct_mdns_writes_local_domain(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        $this->artisan('sail:network', ['--mode' => 'lan-direct', '--ip' => '192.168.1.60', '--resolver' => 'mdns'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_DOMAIN="alpha.local"', $env);
        $this->assertStringContainsString('SAIL_RESOLVER=mdns', $env);
        $this->assertStringNotContainsString('nip.io', $env);
    }

    public function test_lan_direct_no_tls_uses_http_urls(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        $this->artisan('sail:network', ['--mode' => 'lan-direct', '--ip' => '192.168.1.60', '--no-tls' => true])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('APP_URL="http://alpha.192-168-1-60.nip.io"', $env);
        $this->assertStringContainsString('VITE_DEV_SERVER_URL="http://alpha.192-168-1-60.nip.io/vite"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_TLS=false', $env);
        $this->assertStringNotContainsString('https://', $env);
    }

    public function test_lan_direct_clears_leftover_shared_lan_state(): void
    {
        // Project was in shared 'lan' mode: override + profile + offset ports present.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_FILES=compose.yaml:/x/alpha.yml\nCOMPOSE_PROFILES=lan\nFORWARD_DB_PORT=3316\n");

        $this->artisan('sail:network', ['--mode' => 'lan-direct', '--ip' => '192.168.1.60'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertMatchesRegularExpression('/^SAIL_FILES=\s*("")?\s*$/m', $env);
        $this->assertMatchesRegularExpression('/^COMPOSE_PROFILES=\s*("")?\s*$/m', $env);
        $this->assertStringNotContainsString('COMPOSE_PROFILES=lan', $env);
    }

    public function test_local_mode_reverts_from_lan_direct(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_IP=172.20.0.11\nSAIL_BIND_IP=192.168.1.60\nSAIL_NETWORK_MODE=lan-direct\n");

        $this->artisan('sail:network', ['--mode' => 'local'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.11"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE=local', $env);
    }
}
