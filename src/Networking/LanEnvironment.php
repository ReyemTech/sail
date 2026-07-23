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
        if ($this->resolver !== 'nip') {
            throw new InvalidArgumentException("Unsupported resolver [{$this->resolver}]; only 'nip' is supported in this release.");
        }

        return $this->slug($this->project).'.'.str_replace('.', '-', $this->bindIp).'.nip.io';
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
