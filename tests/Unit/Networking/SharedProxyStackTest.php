<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\SharedProxyStack;
use Laravel\Sail\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class SharedProxyStackTest extends TestCase
{
    public function test_proxy_compose_binds_ip_mounts_certs_and_joins_network(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/home/u/.config/sail/certs'))->proxyCompose();
        $parsed = Yaml::parse($yaml);

        $svc = $parsed['services']['nginx-proxy'];
        $this->assertContains('192.168.1.50:80:80', $svc['ports']);
        $this->assertContains('192.168.1.50:443:443', $svc['ports']);
        $this->assertContains('/var/run/docker.sock:/tmp/docker.sock:ro', $svc['volumes']);
        $this->assertContains('/home/u/.config/sail/certs:/etc/nginx/certs:ro', $svc['volumes']);
        $this->assertContains('sail-shared', $svc['networks']);
        $this->assertTrue($parsed['networks']['sail-shared']['external']);
    }

    public function test_project_override_disables_local_proxy_and_joins_shared_network(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/x/certs'))->projectOverride();
        $parsed = Yaml::parse($yaml);

        $this->assertSame(['standalone'], $parsed['services']['nginx-proxy']['profiles']);
        $this->assertContains('sail', $parsed['services']['laravel']['networks']);
        $this->assertContains('sail-shared', $parsed['services']['laravel']['networks']);
        $this->assertTrue($parsed['networks']['sail-shared']['external']);
    }

    public function test_project_override_omits_avahi_sidecar_by_default(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/x/certs'))->projectOverride();
        $parsed = Yaml::parse($yaml);

        $this->assertArrayNotHasKey('avahi-publish', $parsed['services']);
    }

    public function test_project_override_emits_avahi_sidecar_when_mdns(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/x/certs'))->projectOverride(null, true);
        $parsed = Yaml::parse($yaml);

        $svc = $parsed['services']['avahi-publish'];
        $this->assertSame('host', $svc['network_mode']);
        // The load-bearing mount: the D-Bus system bus the host avahi-daemon
        // listens on (canonical /run path; /var/run is a symlink to it).
        $this->assertContains('/run/dbus:/run/dbus', $svc['volumes']);
        $this->assertContains('/var/run/avahi-daemon:/var/run/avahi-daemon', $svc['volumes']);
        // The advertised name + address come from .env at compose time.
        $this->assertStringContainsString('avahi-publish -a ${SAIL_DOMAIN} ${SAIL_BIND_IP}', $yaml);
        // apk / avahi failures must surface in `docker logs`, never be silenced.
        $this->assertStringNotContainsString('>/dev/null', $yaml);
        // The mdns sidecar is host-networked, so it must NOT join the shared network.
        $this->assertArrayNotHasKey('networks', $svc);
    }
}
