<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Networking\AvahiDetector;
use Laravel\Sail\Tests\TestCase;

class NetworkLanSharedTest extends TestCase
{
    private string $base;
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-lanshared-'.uniqid();
        $this->home = $this->base.'/home';
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
        putenv('SAIL_HOME='.$this->home);
        // Minimal compose so present-service detection finds mysql + redis.
        // Named compose.yaml (the fork's first-detected default) rather than
        // docker-compose.yml, so SAIL_FILES must derive the base name dynamically.
        File::put($this->base.'/compose.yaml', "services:\n  laravel: {}\n  mysql: {}\n  redis: {}\n");
        // Keep the mDNS advisory host-independent: these tests assert domain /
        // sidecar wiring, not avahi detection (that lives in AvahiDetectorTest and
        // NetworkMdnsAdvisoryTest). Pretend avahi is available so no warn/prompt fires.
        $this->app->instance(AvahiDetector::class, new AvahiDetector(fn ($cmd) => str_contains($cmd, 'command -v') ? "/usr/sbin/avahi-daemon\n" : "active\n"));
    }

    protected function tearDown(): void
    {
        putenv('SAIL_HOME');
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lan_writes_override_sail_files_and_slot0_ports(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $override = $this->home.'/overrides/alpha.yml';
        $this->assertFileExists($override);
        $this->assertStringContainsString('SAIL_FILES="compose.yaml:'.$override.'"', $env);
        // Slot 0 → base ports.
        $this->assertStringContainsString('FORWARD_DB_PORT=3306', $env);
        $this->assertStringContainsString('FORWARD_REDIS_PORT=6379', $env);
        // Services absent from the fixture (valkey, mailpit) get no forward var.
        $this->assertStringNotContainsString('FORWARD_VALKEY_PORT', $env);
        $this->assertStringNotContainsString('FORWARD_MAILPIT_DASHBOARD_PORT', $env);
    }

    public function test_second_project_gets_offset_ports(): void
    {
        // alpha already registered (slot 0) via a prior run in the shared SAIL_HOME registry.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");
        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])->assertSuccessful();

        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=beta\n");
        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('FORWARD_DB_PORT=3316', $env);   // 3306 + slot1*10
        $this->assertStringContainsString('FORWARD_REDIS_PORT=6389', $env); // 6379 + slot1*10
    }

    public function test_lan_mdns_writes_local_domain_and_avahi_sidecar(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_DOMAIN="alpha.local"', $env);
        $this->assertStringContainsString('APP_URL="https://alpha.local"', $env);
        $this->assertStringContainsString('SAIL_RESOLVER=mdns', $env);
        $this->assertStringNotContainsString('nip.io', $env);

        $override = File::get($this->home.'/overrides/alpha.yml');
        $this->assertStringContainsString('avahi-publish:', $override);
        $this->assertStringContainsString('avahi-publish -a ${SAIL_DOMAIN} ${SAIL_BIND_IP}', $override);
    }

    public function test_switching_resolver_to_mdns_rebuilds_the_domain(): void
    {
        // Already in lan mode with a stored nip.io domain (a prior run). Opting
        // into mdns must REBUILD the domain to <project>.local, not preserve the
        // stale nip.io domain (the heal/re-run preserve logic must not win here).
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=lan\nSAIL_RESOLVER=nip\nSAIL_BIND_IP=192.168.1.50\nSAIL_DOMAIN=alpha.192-168-1-50.nip.io\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--resolver' => 'mdns'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_RESOLVER=mdns', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="alpha.local"', $env);
        $this->assertStringContainsString('APP_URL="https://alpha.local"', $env);
        $this->assertStringNotContainsString('nip.io', $env);
    }

    public function test_heal_without_explicit_resolver_preserves_stored_mdns(): void
    {
        // The bin/sail heal path runs `sail:network --mode=lan` with NO --resolver.
        // A heal must never silently flip a stored mdns project back to nip.io.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=lan\nSAIL_RESOLVER=mdns\nSAIL_BIND_IP=192.168.1.50\nSAIL_DOMAIN=alpha.local\n");

        $this->artisan('sail:network', ['--mode' => 'lan'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_RESOLVER=mdns', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="alpha.local"', $env);
        $this->assertStringNotContainsString('nip.io', $env);
    }

    public function test_lan_default_resolver_is_nip_without_avahi_sidecar(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        // No --resolver => nip.io default (config's mdns default must NOT leak in).
        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_DOMAIN="alpha.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('SAIL_RESOLVER=nip', $env);
        $this->assertStringNotContainsString('.local"', $env);

        $override = File::get($this->home.'/overrides/alpha.yml');
        $this->assertStringNotContainsString('avahi-publish', $override);
    }

    public function test_local_mode_clears_sail_files(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_IP=172.20.0.11\nSAIL_FILES=docker-compose.yml:/x/alpha.yml\n");

        $this->artisan('sail:network', ['--mode' => 'local'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertMatchesRegularExpression('/^SAIL_FILES=\s*("")?\s*$/m', $env);
    }

    public function test_lan_reuses_existing_bind_ip_when_no_ip_given(): void
    {
        // .env already has a bind IP from a prior lan run (mode=lan); no --ip this time.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=lan\nSAIL_BIND_IP=192.168.9.9\n");

        $this->artisan('sail:network', ['--mode' => 'lan'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        // The stored IP is reused (not re-detected), so the domain is built from it.
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.9.9"', $env);
        $this->assertStringContainsString('alpha.192-168-9-9.nip.io', $env);
    }

    public function test_lan_reuses_stored_custom_domain_on_bare_rerun(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=lan\nSAIL_BIND_IP=192.168.9.9\nSAIL_DOMAIN=custom.example.com\n");

        // Bare re-run (as the bin/sail heal does): no --ip, no --domain.
        $this->artisan('sail:network', ['--mode' => 'lan'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        // Custom domain preserved, NOT reverted to nip.io.
        $this->assertStringContainsString('SAIL_DOMAIN="custom.example.com"', $env);
        $this->assertStringContainsString('APP_URL="https://custom.example.com"', $env);
        $this->assertStringNotContainsString('nip.io', $env);
    }

    public function test_switch_from_local_to_lan_detects_fresh_ip_not_stored_docker_ip(): void
    {
        // Fresh LOCAL install: mode=local and SAIL_BIND_IP is the docker-range
        // default. `--mode=lan` with no --ip must DETECT a fresh LAN IP, never
        // reuse the host-local 172.20.0.10 (the regression this guards).
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=local\nSAIL_BIND_IP=172.20.0.10\n");

        // Register a command double whose detectHostIp() is deterministic — it
        // stands in for HostIpDetector hitting the real host.
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(new LanIpDetectingNetworkCommand);

        $this->artisan('sail:network', ['--mode' => 'lan'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.44.44"', $env);
        $this->assertStringContainsString('alpha.192-168-44-44.nip.io', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE=lan', $env);
        // The stale docker-range IP must NOT survive the switch.
        $this->assertStringNotContainsString('172.20.0.10', $env);
        $this->assertStringNotContainsString('172-20-0-10', $env);
    }

    public function test_explicit_ip_always_wins_even_when_already_lan(): void
    {
        // Already lan with a stored IP, but an explicit --ip must override it.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_NETWORK_MODE=lan\nSAIL_BIND_IP=192.168.9.9\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.7.7'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.7.7"', $env);
        $this->assertStringContainsString('alpha.192-168-7-7.nip.io', $env);
        $this->assertStringNotContainsString('192.168.9.9', $env);
    }
}

/**
 * NetworkCommand with a deterministic host-IP detector, for the local->lan
 * switch test (avoids shelling out to the real host).
 */
class LanIpDetectingNetworkCommand extends \Laravel\Sail\Console\NetworkCommand
{
    protected function detectHostIp(): ?string
    {
        return '192.168.44.44';
    }
}
