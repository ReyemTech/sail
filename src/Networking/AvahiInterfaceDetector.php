<?php

namespace Laravel\Sail\Networking;

/**
 * Detects whether the HOST's avahi-daemon advertises the LAN bind IP for its own
 * <hostname>.local — the CNAME target the mDNS sidecar points every <project>.local
 * at. On a multi-interface Docker host (many docker, bridge and tailscale addresses) avahi
 * with no `allow-interfaces` may answer with a 172.x bridge address instead of the
 * real LAN IP, so <project>.local resolves to an unreachable address. This is a host
 * avahi-daemon.conf concern (not fixable in the container), so Sail detects it and
 * offers `allow-interfaces=<lan-iface>`. Linux only — macOS/Bonjour picks the right
 * interface itself.
 */
class AvahiInterfaceDetector
{
    /** @var callable(string):string */
    private $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? fn (string $command): string => (string) shell_exec($command);
    }

    public function hostname(): string
    {
        return trim(($this->runner)('hostname'));
    }

    /**
     * The first IPv4 the host currently advertises for its own <hostname>.local,
     * or null when it doesn't resolve (daemon down / not yet announced).
     */
    public function advertisedIpv4(string $os = PHP_OS_FAMILY): ?string
    {
        if ($os !== 'Linux') {
            return null;
        }

        $host = $this->hostname();

        if ($host === '') {
            return null;
        }

        $out = ($this->runner)('getent ahostsv4 '.escapeshellarg($host.'.local').' 2>/dev/null');

        foreach (preg_split('/\r?\n/', trim($out)) ?: [] as $line) {
            $ip = strtok(trim($line), ' ');

            if ($ip !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * The network interface currently holding $ip (e.g. "wlp2s0"), for the
     * allow-interfaces fix, or null if no interface holds it.
     */
    public function interfaceFor(string $ip): ?string
    {
        $out = ($this->runner)('ip -o -4 addr show 2>/dev/null');

        foreach (preg_split('/\r?\n/', trim($out)) ?: [] as $line) {
            if (preg_match('/^\d+:\s+(\S+)\s+inet\s+([\d.]+)/', trim($line), $m)
                && $m[2] === $ip) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * True when the host advertises a DIFFERENT IP than the LAN bind IP for its
     * own name — i.e. <project>.local (a CNAME to it) would resolve wrong. False
     * when it matches or doesn't resolve at all (that's the daemon-presence case).
     */
    public function isMisrouted(string $bindIp, string $os = PHP_OS_FAMILY): bool
    {
        $advertised = $this->advertisedIpv4($os);

        return $advertised !== null && $advertised !== $bindIp;
    }
}
