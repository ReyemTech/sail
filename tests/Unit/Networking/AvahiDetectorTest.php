<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\AvahiDetector;
use Laravel\Sail\Tests\TestCase;

class AvahiDetectorTest extends TestCase
{
    public function test_macos_is_always_available_via_builtin_bonjour(): void
    {
        // The runner must never be consulted on macOS — Bonjour is built in.
        $detector = new AvahiDetector(function ($cmd) {
            $this->fail("Runner should not be called on macOS; got [{$cmd}].");
        });

        $this->assertSame(AvahiDetector::AVAILABLE, $detector->status('Darwin'));
        $this->assertTrue($detector->isAvailable('Darwin'));
    }

    public function test_linux_with_installed_and_active_daemon_is_available(): void
    {
        $detector = new AvahiDetector(function ($cmd) {
            if (str_contains($cmd, 'command -v')) {
                return "/usr/sbin/avahi-daemon\n";
            }

            return "active\n";
        });

        $this->assertSame(AvahiDetector::AVAILABLE, $detector->status('Linux'));
        $this->assertTrue($detector->isAvailable('Linux'));
    }

    public function test_linux_without_binary_reports_not_installed(): void
    {
        $detector = new AvahiDetector(function ($cmd) {
            if (str_contains($cmd, 'command -v')) {
                return '';
            }

            $this->fail('systemctl must not be probed when the binary is absent.');
        });

        $this->assertSame(AvahiDetector::NOT_INSTALLED, $detector->status('Linux'));
        $this->assertFalse($detector->isAvailable('Linux'));
    }

    public function test_linux_installed_but_inactive_reports_not_running(): void
    {
        $detector = new AvahiDetector(function ($cmd) {
            if (str_contains($cmd, 'command -v')) {
                return "/usr/sbin/avahi-daemon\n";
            }

            return "inactive\n";
        });

        $this->assertSame(AvahiDetector::NOT_RUNNING, $detector->status('Linux'));
        $this->assertFalse($detector->isAvailable('Linux'));
    }

    public function test_linux_installed_but_systemctl_absent_reports_not_running(): void
    {
        // No systemd (returns empty / "unknown"): binary is there but we cannot
        // confirm it is running, so treat it as not-running (warn, don't brick).
        $detector = new AvahiDetector(function ($cmd) {
            if (str_contains($cmd, 'command -v')) {
                return "/usr/sbin/avahi-daemon\n";
            }

            return '';
        });

        $this->assertSame(AvahiDetector::NOT_RUNNING, $detector->status('Linux'));
    }
}
