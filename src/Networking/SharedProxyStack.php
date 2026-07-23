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
     * A host-networked sidecar that advertises SAIL_DOMAIN -> SAIL_BIND_IP over
     * mDNS via the HOST's avahi-daemon (requires avahi-daemon running on the
     * host; the socket + dbus are mounted in). Only emitted when resolver=mdns.
     * SAIL_DOMAIN / SAIL_BIND_IP are interpolated from .env at compose time.
     */
    private function avahiSidecar(): string
    {
        return <<<YAML

            avahi-publish:
                image: alpine:3
                restart: unless-stopped
                network_mode: host
                command: sh -c "apk add --no-cache avahi-tools >/dev/null 2>&1 && exec avahi-publish -a \${SAIL_DOMAIN} \${SAIL_BIND_IP}"
                volumes:
                    - /var/run/dbus:/var/run/dbus
                    - /var/run/avahi-daemon:/var/run/avahi-daemon
        YAML;
    }
}
