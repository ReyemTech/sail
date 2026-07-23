<?php

namespace Laravel\Sail\Networking;

class HostRegistry
{
    /**
     * @var array<string, int> project => slot
     */
    private array $slots;

    public function __construct(private string $path)
    {
        $this->slots = $this->load();
    }

    /**
     * Assign (idempotently) and persist the lowest free slot for a project.
     */
    public function slotFor(string $project): int
    {
        return $this->withLock(function () use ($project): int {
            if (array_key_exists($project, $this->slots)) {
                return $this->slots[$project];
            }

            $slot = $this->lowestFreeSlot();
            $this->slots[$project] = $slot;

            return $slot;
        });
    }

    /**
     * Free a project's slot.
     */
    public function release(string $project): void
    {
        $this->withLock(function () use ($project): void {
            unset($this->slots[$project]);
        });
    }

    /**
     * Drop any registered project not present in the given list.
     *
     * @param  array<int, string>  $existingProjects
     */
    public function prune(array $existingProjects): void
    {
        $this->withLock(function () use ($existingProjects): void {
            $this->slots = array_filter(
                $this->slots,
                fn (string $project) => in_array($project, $existingProjects, true),
                ARRAY_FILTER_USE_KEY
            );
        });
    }

    /**
     * @return array<string, int> project => slot, ascending by slot
     */
    public function projects(): array
    {
        $slots = $this->slots;
        asort($slots);

        return $slots;
    }

    /**
     * Compute a host port from a base port and a slot.
     */
    public static function port(int $base, int $slot, int $stride = 10): int
    {
        return $base + $slot * $stride;
    }

    private function lowestFreeSlot(): int
    {
        $taken = array_values($this->slots);
        $slot = 0;
        while (in_array($slot, $taken, true)) {
            $slot++;
        }

        return $slot;
    }

    /**
     * @return array<string, int>
     */
    private function load(): array
    {
        if (! is_file($this->path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Run a mutation under an exclusive file lock: reload committed state,
     * mutate, persist — atomically with respect to other processes.
     *
     * @param  callable  $mutation
     * @return mixed
     */
    private function withLock(callable $mutation)
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($this->path.'.lock', 'c');

        if ($handle === false) {
            // Locking unavailable — best-effort (reload, mutate, save).
            $this->slots = $this->load();
            $result = $mutation();
            $this->save();

            return $result;
        }

        flock($handle, LOCK_EX);

        try {
            $this->slots = $this->load();
            $result = $mutation();
            $this->save();

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function save(): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = $this->path.'.'.getmypid().'.tmp';
        $json = json_encode($this->slots, JSON_PRETTY_PRINT).PHP_EOL;

        if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write registry temp file [{$tmp}].");
        }

        rename($tmp, $this->path);
    }
}
