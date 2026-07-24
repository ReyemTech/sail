<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class NetworkLanModeTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-lan-'.uniqid();
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lan_mode_writes_bind_ip_domain_url_and_profile(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\nSAIL_SUBNET=172.20.0.0/24\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.1.50"', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="myproj.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('APP_URL="https://myproj.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE=lan', $env);
        // Subnet must be left untouched (not derived from the LAN IP).
        $this->assertStringContainsString('SAIL_SUBNET=172.20.0.0/24', $env);
        $this->assertStringNotContainsString('192.168.1.0/24', $env);
    }

    public function test_lan_mode_accepts_domain_override(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--domain' => 'dev.example.lan'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_DOMAIN="dev.example.lan"', $env);
        $this->assertStringContainsString('APP_URL="https://dev.example.lan"', $env);
    }

    public function test_lan_mode_no_tls_uses_http_urls(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--no-tls' => true])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('APP_URL="http://myproj.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('VITE_DEV_SERVER_URL="http://myproj.192-168-1-50.nip.io/vite"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_TLS=false', $env);
        $this->assertStringNotContainsString('https://', $env);
    }

    public function test_lan_mode_defaults_to_tls_on(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('APP_URL="https://myproj.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_TLS=true', $env);
    }

    public function test_local_mode_reverts_bind_ip_and_profile(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_IP=172.20.0.11\nSAIL_BIND_IP=192.168.1.50\nSAIL_NETWORK_MODE=lan\nCOMPOSE_PROFILES=lan\n");

        $this->artisan('sail:network', ['--mode' => 'local'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.11"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE=local', $env);
        $this->assertMatchesRegularExpression('/^COMPOSE_PROFILES=\s*$/m', $env);
        $this->assertStringNotContainsString('COMPOSE_PROFILES=lan', $env);
    }
}
