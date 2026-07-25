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

        // Frontend build-time configuration forwarded to `npm run build` inside
        // Dockerfile.app-build. Bundlers such as Vite inline VITE_* values at build
        // time, so anything the browser needs must be present during the image
        // build — a container runtime env var (helm, k8s secret) is too late.
        //
        // These are all public once the bundle ships, so they travel as build args.
        // Keys must have a matching `variable` block in runtimes/8.x/docker-bake.hcl
        // and a matching ARG in Dockerfile.app-build.
        //
        // Unset values are dropped, so a project that does not use Sentry builds
        // exactly as it did before.
        //
        // VITE_SENTRY_RELEASE is optional: when a DSN is configured and no release
        // is given, Sail defaults it to the version it is building, so the release
        // tag tracks the image without separate configuration.
        'args' => array_filter([
            'VITE_SENTRY_DSN' => env('VITE_SENTRY_DSN'),
            'VITE_SENTRY_RELEASE' => env('VITE_SENTRY_RELEASE'),
            'SENTRY_ORG' => env('SENTRY_ORG'),
            'SENTRY_PROJECT' => env('SENTRY_PROJECT'),
        ], fn ($value) => $value !== null && $value !== ''),

        // Values that must never appear in an image layer, in `docker history`, or
        // in the build command Sail echoes to the terminal. These are handed to the
        // build process through its environment and picked up by the `type=env`
        // secret declared in docker-bake.hcl.
        'secrets' => array_filter([
            'SENTRY_AUTH_TOKEN' => env('SENTRY_AUTH_TOKEN'),
        ], fn ($value) => $value !== null && $value !== ''),
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
