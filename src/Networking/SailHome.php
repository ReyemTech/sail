<?php

namespace Laravel\Sail\Networking;

class SailHome
{
    private string $home;

    public function __construct(?string $home = null)
    {
        $this->home = $home ?: (getenv('SAIL_HOME') ?: (getenv('HOME').'/.config/sail'));
    }

    public function root(): string
    {
        return $this->home;
    }

    public function proxyDir(): string
    {
        return $this->home.'/proxy';
    }

    public function certsDir(): string
    {
        return $this->home.'/certs';
    }

    public function overridesDir(): string
    {
        return $this->home.'/overrides';
    }

    public function registryPath(): string
    {
        return $this->home.'/registry.json';
    }

    public function ensureDirectories(): void
    {
        foreach ([$this->proxyDir(), $this->certsDir(), $this->overridesDir()] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
