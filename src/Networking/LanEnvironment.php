<?php

namespace Laravel\Sail\Networking;

use InvalidArgumentException;

class LanEnvironment
{
    public function __construct(
        private string $project,
        private string $bindIp,
        private string $resolver = 'nip'
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

        return [
            'SAIL_BIND_IP' => $this->bindIp,
            'SAIL_DOMAIN' => $domain,
            'APP_URL' => 'https://'.$domain,
            'VITE_DEV_SERVER_URL' => 'https://'.$domain.'/vite',
            'SAIL_NETWORK_MODE' => 'lan',
            'SAIL_RESOLVER' => $this->resolver,
            'COMPOSE_PROFILES' => 'lan',
        ];
    }

    private function slug(string $value): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
