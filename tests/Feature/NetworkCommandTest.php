<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class NetworkCommandTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-network-'.uniqid();
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_status_reports_local_mode(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\n");

        $this->artisan('sail:network', ['--status' => true])
            ->expectsOutputToContain('local')
            ->assertSuccessful();
    }

    public function test_status_reports_lan_domain_and_forward_ports(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_NETWORK_MODE=lan\nSAIL_DOMAIN=alpha.192-168-1-50.nip.io\nFORWARD_DB_PORT=3316\n");

        $this->artisan('sail:network', ['--status' => true])
            ->expectsOutputToContain('lan')
            ->expectsOutputToContain('alpha.192-168-1-50.nip.io')
            ->assertSuccessful();
    }

    public function test_seeds_bind_ip_when_absent(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\n");

        $this->artisan('sail:network')->assertSuccessful();

        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.10"', File::get($this->base.'/.env'));
    }

    public function test_does_not_duplicate_existing_bind_ip(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_BIND_IP=172.20.0.11\n");

        $this->artisan('sail:network')->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertSame(1, substr_count($env, 'SAIL_BIND_IP='));
        $this->assertStringContainsString('SAIL_BIND_IP=172.20.0.11', $env);
    }

    public function test_seeds_bind_ip_from_existing_sail_ip(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_IP=172.20.0.11\n");

        $this->artisan('sail:network')->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.11"', $env);
        $this->assertStringNotContainsString('SAIL_BIND_IP="172.20.0.10"', $env);
    }
}
