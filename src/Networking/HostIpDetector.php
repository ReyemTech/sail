<?php

namespace Laravel\Sail\Networking;

class HostIpDetector
{
    /** @var callable(string):string */
    private $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? fn (string $command): string => (string) shell_exec($command);
    }

    public function detect(string $os = PHP_OS_FAMILY): ?string
    {
        if ($os === 'Darwin') {
            $ip = trim(($this->runner)('ipconfig getifaddr en0'));

            return $this->isPlausibleLanIp($ip) ? $ip : null;
        }

        $candidates = preg_split('/\s+/', trim(($this->runner)('hostname -I'))) ?: [];

        foreach ($candidates as $candidate) {
            if ($this->isPlausibleLanIp($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isPlausibleLanIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        // Docker bridge range 172.16.0.0 – 172.31.255.255.
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
            return false;
        }

        return true;
    }
}
