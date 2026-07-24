<?php

namespace Laravel\Sail\Networking;

/**
 * Detects whether the HOST can advertise <project>.local over mDNS.
 *
 * mDNS (resolver=mdns) relies on a running host avahi-daemon reachable by the
 * shared-proxy sidecar via the mounted /run/dbus socket. This is a genuine host
 * prerequisite that cannot be satisfied from inside a container — so Sail detects
 * it and warns (with the exact fix) instead of resolving <project>.local silently
 * to nothing. macOS/Windows ship mDNS (Bonjour) built in, so only Linux is probed.
 */
class AvahiDetector
{
    public const AVAILABLE = 'available';

    public const NOT_INSTALLED = 'not-installed';

    public const NOT_RUNNING = 'not-running';

    /** @var callable(string):string */
    private $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? fn (string $command): string => (string) shell_exec($command);
    }

    /**
     * Return one of the AVAILABLE / NOT_INSTALLED / NOT_RUNNING constants.
     */
    public function status(string $os = PHP_OS_FAMILY): string
    {
        // macOS and Windows resolve *.local via built-in Bonjour/mDNS — nothing
        // to install, so mDNS is always considered available there.
        if ($os !== 'Linux') {
            return self::AVAILABLE;
        }

        $installed = trim(($this->runner)('command -v avahi-daemon 2>/dev/null')) !== '';

        if (! $installed) {
            return self::NOT_INSTALLED;
        }

        $active = trim(($this->runner)('systemctl is-active avahi-daemon 2>/dev/null'));

        return $active === 'active' ? self::AVAILABLE : self::NOT_RUNNING;
    }

    public function isAvailable(string $os = PHP_OS_FAMILY): bool
    {
        return $this->status($os) === self::AVAILABLE;
    }
}
