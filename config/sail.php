<?php

return [
    'domain' => env('SAIL_DOMAIN', 'localhost'),
    'build' => [
        'environments' => env('SAIL_BUILD_ENVIRONMENT', 'production'),
        'architectures' => env('SAIL_BUILD_ARCHITECTURES', 'linux/amd64,linux/arm64'),
        'repository' => env('SAIL_BUILD_REPOSITORY', 'sail'),
        'push' => env('SAIL_BUILD_PUSH', false),
        'organization' => env('SAIL_BUILD_ORGANIZATION', 'reyemtech'),
        'version' => env('SAIL_BUILD_VERSION', "1.0.0"),
        'remove_node_modules' => env('SAIL_BUILD_REMOVE_NODE_MODULES',
            env('SAIL_BUILD_REMOVE_VENDOR_NODE_MODULES', true)),
    ],
    'deploy' => [
        'domains' => env('SAIL_DEPLOY_DOMAINS', '.reyemtech.com'),
    ],
    'network' => [
        // Networking mode: 'local' (default, host-only) or 'lan' (exposed to LAN).
        'mode' => env('SAIL_NETWORK_MODE', 'local'),
        // How other devices resolve the project domain: 'mdns' | 'nip' | 'manual'.
        'resolver' => env('SAIL_RESOLVER', 'mdns'),
        // Issue a trusted (mkcert) certificate for the domain in exposed mode.
        'tls' => env('SAIL_NETWORK_TLS', false),
        // Host address published ports bind to. Local default is the Docker-range IP.
        'bind_ip' => env('SAIL_BIND_IP', '172.20.0.10'),
        // Docker bridge subnet (internal). Independent of bind_ip.
        'subnet' => env('SAIL_SUBNET', '172.20.0.0/24'),
        // Path to the per-machine host registry. Null => ~/.config/sail/registry.json.
        'registry_path' => env('SAIL_REGISTRY_PATH', null),
    ],
];
