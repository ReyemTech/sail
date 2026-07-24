<?php

namespace Laravel\Sail\Networking;

class SharedProxyStack
{
    public function __construct(
        private string $bindIp,
        private string $certsDir,
        private string $network = 'sail-shared'
    ) {
    }

    public function proxyCompose(): string
    {
        $stub = file_get_contents(__DIR__.'/../../stubs/shared-proxy.stub');

        return strtr($stub, [
            '__BIND_IP__' => $this->bindIp,
            '__CERTS_DIR__' => $this->certsDir,
            '__NETWORK__' => $this->network,
        ]);
    }

    public function projectOverride(?string $network = null, bool $mdns = false): string
    {
        $network = $network ?: $this->network;

        $avahi = $mdns ? $this->avahiSidecar() : '';

        return <<<YAML
        services:
            nginx-proxy:
                profiles:
                    - standalone
            laravel:
                networks:
                    - sail
                    - {$network}{$avahi}
        networks:
            {$network}:
                external: true
        YAML;
    }

    /**
     * A host-networked sidecar that makes SAIL_DOMAIN resolve to the host's LAN IP
     * over mDNS via the HOST's avahi-daemon. Only emitted when resolver=mdns.
     *
     * It publishes a CNAME (SAIL_DOMAIN -> <host>.local), NOT an A record. An A
     * record (avahi-publish -a) owns the reverse PTR for the address 1:1, so it
     * collides the moment a second project points at the same shared proxy IP —
     * and collides immediately on the host's own IP. A CNAME has no PTR, so every
     * project can alias the one host name, which itself resolves to the LAN IP.
     *
     * HOST PREREQUISITES (both detected + offered by `sail:network --resolver=mdns`):
     *   1. avahi-daemon installed AND running (the publisher is only a D-Bus
     *      client; the load-bearing mount is /run/dbus).
     *   2. avahi advertising the host's <hostname>.local on the LAN interface
     *      (`allow-interfaces=<lan>` in avahi-daemon.conf) — on multi-bridge Docker
     *      hosts it otherwise answers with a 172.x docker address.
     *
     * `network_mode: host` reaches the daemon and announces on the real LAN NIC.
     * `security_opt: apparmor:unconfined` is REQUIRED: dbus-daemon's AppArmor
     * mediation denies the default `docker-default` profile from the system bus
     * ("Access denied" on the very first Hello). The publisher script is inlined
     * (base64) from the mdns-cname-publish.py stub so the override is self-contained.
     * apk/python errors are NOT silenced so failures surface in `docker logs`.
     */
    private function avahiSidecar(): string
    {
        $script = base64_encode((string) file_get_contents(__DIR__.'/../../stubs/mdns-cname-publish.py'));

        return <<<YAML

            avahi-publish:
                image: alpine:3
                restart: unless-stopped
                network_mode: host
                security_opt:
                    - apparmor:unconfined
                command: sh -c "apk add --no-cache python3 py3-dbus && echo {$script} | base64 -d > /pub.py && exec python3 /pub.py \${SAIL_DOMAIN}"
                volumes:
                    - /run/dbus:/run/dbus
        YAML;
    }
}
