<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Networking\AvahiDetector;
use Laravel\Sail\Networking\AvahiInterfaceDetector;
use Laravel\Sail\Tests\TestCase;

class NetworkMdnsAdvisoryTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-mdns-'.uniqid();
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
        // Default: host-interface check quiet (not misrouted) so the daemon-presence
        // advisory tests below assert only their own output. Individual tests that
        // exercise the misroute path rebind this with fakeMisroute().
        $this->app->instance(AvahiInterfaceDetector::class, new AvahiInterfaceDetector(fn ($cmd) => ''));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    /** Bind an AvahiDetector whose runner reports a specific host state. */
    private function fakeAvahi(string $whichStatus): void
    {
        $runner = match ($whichStatus) {
            AvahiDetector::NOT_INSTALLED => fn ($cmd) => '',
            AvahiDetector::NOT_RUNNING => fn ($cmd) => str_contains($cmd, 'command -v') ? "/usr/sbin/avahi-daemon\n" : "inactive\n",
            default => fn ($cmd) => str_contains($cmd, 'command -v') ? "/usr/sbin/avahi-daemon\n" : "active\n",
        };

        $this->app->instance(AvahiDetector::class, new AvahiDetector($runner));
    }

    /** Bind an AvahiInterfaceDetector reporting <host>.local -> $advertised, with $bindIp on $iface. */
    private function fakeMisroute(string $advertised, string $bindIp, string $iface): void
    {
        $ipOut = "1: lo    inet 127.0.0.1/8 scope host lo\n"
            ."3: {$iface}    inet {$bindIp}/24 brd 1.2.3.255 scope global {$iface}\n";

        $this->app->instance(AvahiInterfaceDetector::class, new AvahiInterfaceDetector(function ($cmd) use ($advertised, $ipOut) {
            if (str_contains($cmd, 'hostname')) {
                return "creed\n";
            }
            if (str_contains($cmd, 'getent ahostsv4')) {
                return "{$advertised}  STREAM creed.local\n";
            }
            if (str_contains($cmd, 'ip -o -4 addr')) {
                return $ipOut;
            }

            return '';
        }));
    }

    public function test_mdns_warns_and_offers_allow_interfaces_when_host_misrouted(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::AVAILABLE);              // daemon-presence check stays quiet
        $this->fakeMisroute('172.20.0.1', '192.168.1.50', 'wlp2s0');

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->assertSuccessful();

        $this->assertStringContainsString('SAIL_RESOLVER=mdns', File::get($this->base.'/.env'));
    }

    public function test_mdns_no_misroute_warning_when_host_advertises_bind_ip(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::AVAILABLE);
        $this->fakeMisroute('192.168.1.50', '192.168.1.50', 'wlp2s0');   // advertised == bind

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->doesntExpectOutputToContain('advertises')
            ->assertSuccessful();
    }

    public function test_mdns_warns_and_gives_install_fix_when_avahi_absent(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::NOT_INSTALLED);

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_RESOLVER=mdns', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="myproj.local"', $env);
    }

    public function test_mdns_warns_with_start_fix_when_avahi_installed_but_stopped(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::NOT_RUNNING);

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->assertSuccessful();

        $this->assertStringContainsString('SAIL_RESOLVER=mdns', File::get($this->base.'/.env'));
    }

    public function test_mdns_stays_quiet_when_avahi_available(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::AVAILABLE);

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->doesntExpectOutputToContain('mDNS selected')
            ->assertSuccessful();
    }

    public function test_nip_resolver_never_probes_avahi(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        // A runner that fails the test if consulted — nip must not probe the host.
        $this->app->instance(AvahiDetector::class, new AvahiDetector(function ($cmd) {
            $this->fail("nip resolver must not probe avahi; got [{$cmd}].");
        }));

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])
            ->doesntExpectOutputToContain('mDNS selected')
            ->assertSuccessful();
    }
}
