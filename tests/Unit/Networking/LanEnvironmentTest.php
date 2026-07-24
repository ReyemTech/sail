<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use InvalidArgumentException;
use Laravel\Sail\Networking\LanEnvironment;
use Laravel\Sail\Tests\TestCase;

class LanEnvironmentTest extends TestCase
{
    public function test_builds_nip_io_domain_with_dashed_ip(): void
    {
        $env = new LanEnvironment('myproj', '192.168.1.50');
        $this->assertSame('myproj.192-168-1-50.nip.io', $env->domain());
    }

    public function test_slugifies_project_segment(): void
    {
        $env = new LanEnvironment('My App_v2', '10.0.0.4');
        $this->assertSame('my-app-v2.10-0-0-4.nip.io', $env->domain());
    }

    public function test_values_wire_url_domain_mode_and_profile(): void
    {
        $values = (new LanEnvironment('myproj', '192.168.1.50'))->values();

        $this->assertSame('192.168.1.50', $values['SAIL_BIND_IP']);
        $this->assertSame('myproj.192-168-1-50.nip.io', $values['SAIL_DOMAIN']);
        $this->assertSame('https://myproj.192-168-1-50.nip.io', $values['APP_URL']);
        $this->assertSame('https://myproj.192-168-1-50.nip.io/vite', $values['VITE_DEV_SERVER_URL']);
        $this->assertSame('lan', $values['SAIL_NETWORK_MODE']);
        $this->assertSame('nip', $values['SAIL_RESOLVER']);
        $this->assertSame('lan', $values['COMPOSE_PROFILES']);
    }

    public function test_builds_mdns_local_domain_without_ip(): void
    {
        $env = new LanEnvironment('My App_v2', '192.168.1.50', 'mdns');
        $this->assertSame('my-app-v2.local', $env->domain());
    }

    public function test_mdns_values_wire_local_domain_and_resolver(): void
    {
        $values = (new LanEnvironment('myproj', '192.168.1.50', 'mdns'))->values();

        $this->assertSame('192.168.1.50', $values['SAIL_BIND_IP']);
        $this->assertSame('myproj.local', $values['SAIL_DOMAIN']);
        $this->assertSame('https://myproj.local', $values['APP_URL']);
        $this->assertSame('https://myproj.local/vite', $values['VITE_DEV_SERVER_URL']);
        $this->assertSame('lan', $values['SAIL_NETWORK_MODE']);
        $this->assertSame('mdns', $values['SAIL_RESOLVER']);
        $this->assertSame('lan', $values['COMPOSE_PROFILES']);
    }

    public function test_rejects_unsupported_resolver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LanEnvironment('myproj', '192.168.1.50', 'manual'))->domain();
    }

    public function test_tls_off_uses_http_scheme_and_records_flag(): void
    {
        $values = (new LanEnvironment('myproj', '192.168.1.50', 'nip', false))->values();

        $this->assertSame('http://myproj.192-168-1-50.nip.io', $values['APP_URL']);
        $this->assertSame('http://myproj.192-168-1-50.nip.io/vite', $values['VITE_DEV_SERVER_URL']);
        $this->assertSame('false', $values['SAIL_NETWORK_TLS']);
    }

    public function test_tls_on_records_flag_and_https_scheme(): void
    {
        $values = (new LanEnvironment('myproj', '192.168.1.50', 'nip', true))->values();

        $this->assertSame('https://myproj.192-168-1-50.nip.io', $values['APP_URL']);
        $this->assertSame('true', $values['SAIL_NETWORK_TLS']);
    }

    public function test_lan_direct_mode_sets_mode_and_omits_compose_profiles(): void
    {
        $values = (new LanEnvironment('myproj', '192.168.1.50', 'nip', true, 'lan-direct'))->values();

        $this->assertSame('lan-direct', $values['SAIL_NETWORK_MODE']);
        // lan-direct uses a per-project proxy on standard ports: no shared-proxy profile.
        $this->assertArrayNotHasKey('COMPOSE_PROFILES', $values);
    }
}
