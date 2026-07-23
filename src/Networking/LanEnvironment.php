<?php

namespace Laravel\Sail\Networking;

use InvalidArgumentException;

class LanEnvironment
{
    public function __construct(
        private string $project,
        private string $bindIp,
        private string $resolver = 'nip',
        private bool $tls = true,
        private string $mode = 'lan'
    ) {
    }

    public function domain(): string
    {
        return match ($this->resolver) {
            'nip' => $this->slug($this->project).'.'.str_replace('.', '-', $this->bindIp).'.nip.io',
            'mdns' => $this->slug($this->project).'.local',
            default => throw new InvalidArgumentException("Unsupported resolver [{$this->resolver}]; supported: 'nip', 'mdns'."),
        };
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        $domain = $this->domain();
        $scheme = $this->tls ? 'https' : 'http';

        $values = [
            'SAIL_BIND_IP' => $this->bindIp,
            'SAIL_DOMAIN' => $domain,
            'APP_URL' => $scheme.'://'.$domain,
            'VITE_DEV_SERVER_URL' => $scheme.'://'.$domain.'/vite',
            'SAIL_NETWORK_MODE' => $this->mode,
            'SAIL_RESOLVER' => $this->resolver,
            'SAIL_NETWORK_TLS' => $this->tls ? 'true' : 'false',
        ];

        // Only the shared-proxy 'lan' mode activates the compose 'lan' profile
        // (which gates each project's standalone nginx-proxy off). 'lan-direct'
        // keeps its own per-project proxy on standard ports — no profile.
        if ($this->mode === 'lan') {
            $values['COMPOSE_PROFILES'] = 'lan';
        }

        return $values;
    }

    private function slug(string $value): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
