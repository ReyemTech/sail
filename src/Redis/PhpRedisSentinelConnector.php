<?php

namespace Laravel\Sail\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connectors\PhpRedisConnector;
use Illuminate\Support\Arr;
use RedisSentinel;
use Throwable;

/**
 * Sentinel-aware variant of Laravel's PhpRedisConnector. On every connection
 * attempt — including reconnects after the master fails over — it asks Sentinel
 * for the current master via getMasterAddrByName(), then delegates to the
 * standard phpredis connection logic against the resolved address.
 *
 * Designed for Bitnami-style topologies (3 data nodes + 3 sentinels), where a
 * shared password authenticates both data plane and sentinel.
 *
 * Required $config keys (in addition to the standard host/port/password):
 *   - sentinel_host:    Sentinel service hostname or IP
 *   - sentinel_service: Master name registered with Sentinel (e.g. "mymaster")
 *
 * Optional:
 *   - sentinel_port:    Sentinel port (default 26379)
 *
 * Safety: if Sentinel is unreachable or returns no master, the connector
 * silently falls back to the existing host/port — worst case the behavior
 * matches the pre-existing PhpRedisConnector. A misconfigured Sentinel cannot
 * take the app down on its own.
 */
class PhpRedisSentinelConnector extends PhpRedisConnector
{
    public function connect(array $config, array $options): PhpRedisConnection
    {
        if (empty($config['sentinel_host']) || empty($config['sentinel_service'])) {
            return parent::connect($config, $options);
        }

        $sentinelConfig = $config;
        $formattedOptions = Arr::pull($config, 'options', []);
        if (isset($config['prefix'])) {
            $formattedOptions['prefix'] = $config['prefix'];
        }

        $resolveMaster = fn (): array => $this->resolveMasterFromSentinel($sentinelConfig);

        $connector = function () use ($resolveMaster, $options, $formattedOptions) {
            $resolved = $resolveMaster();

            return $this->createClient(array_merge($resolved, $options, $formattedOptions));
        };

        return new PhpRedisSentinelConnection($connector(), $connector, $resolveMaster());
    }

    /**
     * Query Sentinel for the current master and return $config with host/port replaced.
     * On any error returns $config unchanged so the caller falls back to the original
     * direct host/port — defensive guarantee that a broken Sentinel cannot brick the app.
     */
    protected function resolveMasterFromSentinel(array $config): array
    {
        try {
            $sentinel = $this->createSentinel($config);
            $master = $sentinel->getMasterAddrByName($config['sentinel_service']);

            if (is_array($master) && isset($master[0], $master[1])) {
                $config['host'] = $master[0];
                $config['port'] = (int) $master[1];
            }
        } catch (Throwable) {
            // Defensive fallback — keep $config unchanged.
        }

        return $config;
    }

    /**
     * Factory for the RedisSentinel client. Override in tests to inject a fake.
     */
    protected function createSentinel(array $config): RedisSentinel
    {
        return new RedisSentinel([
            'host' => $config['sentinel_host'],
            'port' => (int) ($config['sentinel_port'] ?? 26379),
            'connectTimeout' => 1.5,
            'auth' => $config['password'] ?? null,
        ]);
    }
}
