<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\HostIpDetector;
use Laravel\Sail\Tests\TestCase;

class HostIpDetectorTest extends TestCase
{
    public function test_detects_first_lan_ip_from_hostname_output(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => "172.20.0.1 192.168.1.50 10.0.0.4\n");
        $this->assertSame('192.168.1.50', $detector->detect('Linux'));
    }

    public function test_skips_loopback_linklocal_and_docker_ranges(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => "127.0.0.1 169.254.1.1 172.18.0.1 10.1.2.3\n");
        $this->assertSame('10.1.2.3', $detector->detect('Linux'));
    }

    public function test_returns_null_when_no_plausible_ip(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => "127.0.0.1 172.20.0.1\n");
        $this->assertNull($detector->detect('Linux'));
    }

    public function test_uses_ipconfig_on_macos(): void
    {
        $detector = new HostIpDetector(function ($cmd) {
            return str_contains($cmd, 'ipconfig getifaddr') ? "192.168.7.7\n" : '';
        });
        $this->assertSame('192.168.7.7', $detector->detect('Darwin'));
    }

    public function test_plausibility_predicate(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => '');
        $this->assertTrue($detector->isPlausibleLanIp('192.168.0.1'));
        $this->assertTrue($detector->isPlausibleLanIp('10.0.0.1'));
        $this->assertFalse($detector->isPlausibleLanIp('127.0.0.1'));
        $this->assertFalse($detector->isPlausibleLanIp('169.254.0.1'));
        $this->assertFalse($detector->isPlausibleLanIp('172.20.0.10'));
        $this->assertFalse($detector->isPlausibleLanIp('not-an-ip'));
    }
}
