<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Networking\AvahiDetector;
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

    public function test_mdns_warns_and_gives_install_fix_when_avahi_absent(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::NOT_INSTALLED);

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->expectsOutputToContain('mDNS selected')
            ->expectsOutputToContain('sudo apt install -y avahi-daemon')
            ->expectsOutputToContain('--resolver=nip')
            ->expectsConfirmation('Install/start avahi-daemon now? (needs sudo)', 'no')
            ->assertSuccessful();
        // Each asserted line lands in its own write chunk (warn / bullet / info),
        // so expectsOutputToContain can match all three independently.

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_RESOLVER=mdns', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="myproj.local"', $env);
    }

    public function test_mdns_warns_with_start_fix_when_avahi_installed_but_stopped(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");
        $this->fakeAvahi(AvahiDetector::NOT_RUNNING);

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--resolver' => 'mdns'])
            ->expectsOutputToContain('installed but not running')
            ->expectsOutputToContain('sudo systemctl enable --now avahi-daemon')
            ->expectsConfirmation('Install/start avahi-daemon now? (needs sudo)', 'no')
            ->assertSuccessful();
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
