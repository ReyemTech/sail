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
     * mDNS via the HOST's avahi-daemon. Only emitted when resolver=mdns.
     *
     * HOST PREREQUISITE: avahi-daemon must be installed AND running on the host
     * (`sudo apt install -y avahi-daemon`). avahi-publish is a *client* — it does
     * not itself answer mDNS; it registers the record with the host daemon over
     * the D-Bus system bus (the load-bearing mount below is /run/dbus). Without a
     * running host daemon the container exits and restart-loops (visible in
     * `docker logs`), and <project>.local will not resolve.
     *
     * `network_mode: host` is required so the registration reaches the daemon and
     * is announced on the real LAN interface. SAIL_DOMAIN / SAIL_BIND_IP are
     * interpolated from .env at compose time. apk/avahi errors are NOT silenced so
     * failures surface in `docker logs <project>-avahi-publish-1`.
     */
    private function avahiSidecar(): string
    {
        return <<<YAML

            avahi-publish:
                image: alpine:3
                restart: unless-stopped
                network_mode: host
                command: sh -c "apk add --no-cache avahi-tools && exec avahi-publish -a \${SAIL_DOMAIN} \${SAIL_BIND_IP}"
                volumes:
                    - /run/dbus:/run/dbus
                    - /var/run/avahi-daemon:/var/run/avahi-daemon
        YAML;
    }
}
