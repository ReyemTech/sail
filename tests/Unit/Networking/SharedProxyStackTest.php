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
}
