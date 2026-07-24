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

    public function test_proxy_compose_mounts_raised_header_buffers(): void
    {
        $stack = new SharedProxyStack('192.168.1.50', '/home/u/.config/sail/certs');
        $parsed = Yaml::parse($stack->proxyCompose());

        // Relative to the compose file's dir (compose resolves host paths there),
        // so no absolute path needed — ProxyCommand writes proxy-buffers.conf next
        // to the compose file.
        $this->assertContains(
            './proxy-buffers.conf:/etc/nginx/conf.d/00-proxy-buffers.conf:ro',
            $parsed['services']['nginx-proxy']['volumes'],
            'compose should mount the proxy-buffers conf into conf.d'
        );

        // The buffers raise nginx's 4k/8k default so a large upstream response
        // header (long CSP, many Set-Cookie) doesn't overflow and 502.
        $conf = $stack->proxyBuffersConf();
        $this->assertStringContainsString('proxy_buffer_size', $conf);
        $this->assertStringContainsString('proxy_buffers', $conf);
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

    public function test_project_override_emits_cname_avahi_sidecar_when_mdns(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/x/certs'))->projectOverride(null, true);
        $parsed = Yaml::parse($yaml);

        $svc = $parsed['services']['avahi-publish'];
        $this->assertSame('host', $svc['network_mode']);
        // AppArmor's D-Bus mediation blocks the docker-default profile from the
        // system bus (member="Hello" DENIED) — without unconfined the publisher
        // gets "Failed to create client object: Access denied" and crash-loops.
        $this->assertContains('apparmor:unconfined', $svc['security_opt']);
        // The D-Bus system bus is the only load-bearing mount.
        $this->assertContains('/run/dbus:/run/dbus', $svc['volumes']);
        // Publishes a CNAME (<project>.local -> <host>.local), NOT an A record:
        // avahi-publish -a owns the reverse PTR 1:1 and collides when many
        // projects share one host IP. The publisher runs from an inlined stub.
        $this->assertStringContainsString('py3-dbus', $yaml);
        // PyGObject provides the GLib main loop the resilient publisher runs on.
        $this->assertStringContainsString('py3-gobject3', $yaml);
        $this->assertStringContainsString('python3 /pub.py ${SAIL_DOMAIN}', $yaml);
        $this->assertStringContainsString('base64 -d', $yaml);
        // The inlined blob must decode to the real CNAME publisher, and it must
        // re-register across avahi-daemon restarts (NameOwnerChanged watcher).
        preg_match('/echo\s+(\S+)\s*\|\s*base64 -d/', $yaml, $m);
        $decoded = base64_decode($m[1] ?? '', true);
        $this->assertNotFalse($decoded);
        $this->assertStringContainsString('EntryGroup', (string) $decoded);
        $this->assertStringContainsString('GetHostNameFqdn', (string) $decoded);
        $this->assertStringContainsString('NameOwnerChanged', (string) $decoded);
        // apk / python failures must surface in `docker logs`, never be silenced.
        $this->assertStringNotContainsString('>/dev/null', $yaml);
        // The mdns sidecar is host-networked, so it must NOT join the shared network.
        $this->assertArrayNotHasKey('networks', $svc);
    }
}
