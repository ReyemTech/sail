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

    public function projectOverride(?string $network = null): string
    {
        $network = $network ?: $this->network;

        return <<<YAML
        services:
            nginx-proxy:
                profiles:
                    - standalone
            laravel:
                networks:
                    - sail
                    - {$network}
        networks:
            {$network}:
                external: true
        YAML;
    }
}
