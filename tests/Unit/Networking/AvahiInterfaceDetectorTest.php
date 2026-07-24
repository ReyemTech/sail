<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\AvahiInterfaceDetector;
use Laravel\Sail\Tests\TestCase;

class AvahiInterfaceDetectorTest extends TestCase
{
    private function runner(string $hostname, string $advertised, string $ipAddrOutput): callable
    {
        return function (string $cmd) use ($hostname, $advertised, $ipAddrOutput) {
            if (str_contains($cmd, 'hostname')) {
                return $hostname."\n";
            }
            if (str_contains($cmd, 'getent ahostsv4')) {
                return $advertised;
            }
            if (str_contains($cmd, 'ip -o -4 addr')) {
                return $ipAddrOutput;
            }

            return '';
        };
    }

    public function test_advertised_ipv4_takes_the_first_v4_from_getent(): void
    {
        $getent = "192.168.2.50   STREAM creed.local\n192.168.2.50   DGRAM\n";
        $d = new AvahiInterfaceDetector($this->runner('creed', $getent, ''));

        $this->assertSame('192.168.2.50', $d->advertisedIpv4('Linux'));
    }

    public function test_interface_for_finds_the_iface_holding_the_ip(): void
    {
        $ipOut = "1: lo    inet 127.0.0.1/8 scope host lo\n"
            ."3: wlp2s0    inet 192.168.2.50/24 brd 192.168.2.255 scope global dynamic wlp2s0\n"
            ."5: docker0    inet 172.17.0.1/16 brd 172.17.255.255 scope global docker0\n";
        $d = new AvahiInterfaceDetector($this->runner('creed', '', $ipOut));

        $this->assertSame('wlp2s0', $d->interfaceFor('192.168.2.50'));
        $this->assertSame('docker0', $d->interfaceFor('172.17.0.1'));
        $this->assertNull($d->interfaceFor('10.9.9.9'));
    }

    public function test_misrouted_when_host_advertises_a_non_lan_ip(): void
    {
        // avahi answering with a docker-bridge IP for creed.local (the bug).
        $d = new AvahiInterfaceDetector($this->runner('creed', "172.20.0.1  STREAM creed.local\n", ''));

        $this->assertTrue($d->isMisrouted('192.168.2.50', 'Linux'));
    }

    public function test_not_misrouted_when_host_advertises_the_bind_ip(): void
    {
        $d = new AvahiInterfaceDetector($this->runner('creed', "192.168.2.50  STREAM creed.local\n", ''));

        $this->assertFalse($d->isMisrouted('192.168.2.50', 'Linux'));
    }

    public function test_not_misrouted_when_name_does_not_resolve(): void
    {
        // avahi/daemon can't resolve the host name — the daemon-presence check
        // owns that case; the interface check must not also fire.
        $d = new AvahiInterfaceDetector($this->runner('creed', '', ''));

        $this->assertFalse($d->isMisrouted('192.168.2.50', 'Linux'));
    }

    public function test_macos_is_never_misrouted(): void
    {
        // macOS resolves .local via Bonjour on the right interface itself.
        $d = new AvahiInterfaceDetector(function ($cmd) {
            $this->fail("Runner must not be consulted on macOS; got [{$cmd}].");
        });

        $this->assertFalse($d->isMisrouted('192.168.2.50', 'Darwin'));
    }
}
