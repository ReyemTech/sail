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
 *   - sentinel_port:              Sentinel port (default 26379)
 *   - connect_retry_backoff_ms:   list<int> of delays (ms) before each connect
 *                                 retry on a transient connection failure. Defaults to
 *                                 [100, 250, 500, 1000] (≤ 4 retries, 5 attempts).
 *
 * Resilience: in Kubernetes the sentinel-returned master is sometimes a headless
 * pod hostname CoreDNS cannot resolve yet, and the stable service name briefly
 * returns EAI_AGAIN ("Try again"). Two behaviors guard against that transient
 * window, both backward compatible (when DNS is healthy the first attempt
 * succeeds and the resolved master is used, exactly as before):
 *   1. connect() retries DNS, refusal, setup read errors, and LOADING with bounded backoff.
 *   2. resolveMasterFromSentinel() falls back to the configured host/port when
 *      the sentinel-returned host is not resolvable from this process.
 *
 * Safety: if Sentinel is unreachable or returns no master, the connector
 * silently falls back to the existing host/port — worst case the behavior
 * matches the pre-existing PhpRedisConnector. A misconfigured Sentinel cannot
 * take the app down on its own.
 */
class PhpRedisSentinelConnector extends PhpRedisConnector
{
    use RetriesRedisConnections;

    /**
     * Establish a Sentinel-aware connection with bounded setup retries.
     */
    public function connect(array $config, array $options): PhpRedisConnection
    {
        return $this->retryRedisConnection(
            fn (): PhpRedisConnection => $this->establishSentinelConnection($config, $options),
            $config
        );
    }

    /**
     * A single connection attempt: resolve the current master via Sentinel, then
     * delegate to phpredis. Wrapped by connect()'s retry loop. Overridable in
     * tests to script connect outcomes without a real Redis/Sentinel.
     */
    protected function establishSentinelConnection(array $config, array $options): PhpRedisConnection
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
     * Resolve the master via Sentinel, but fall back to the configured host/port
     * when the sentinel-returned host is not resolvable from this process.
     */
    protected function resolveMasterFromSentinel(array $config): array
    {
        return $this->selectResolvableMasterConfig($config, $this->queryMasterFromSentinel($config));
    }

    /**
     * Query Sentinel for the current master and return $config with host/port replaced.
     * On any error returns $config unchanged so the caller falls back to the original
     * direct host/port — defensive guarantee that a broken Sentinel cannot brick the app.
     */
    protected function queryMasterFromSentinel(array $config): array
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
     * Choose the sentinel-resolved master unless its host can't be resolved here.
     * When resolved host/port already equal the configured ones there's nothing to
     * verify (fast path, and it avoids the synchronous DNS probe below).
     *
     * @param  array<string, mixed>  $originalConfig
     * @param  array<string, mixed>  $resolvedConfig
     * @return array<string, mixed>
     */
    protected function selectResolvableMasterConfig(array $originalConfig, array $resolvedConfig): array
    {
        if (($resolvedConfig['host'] ?? null) === ($originalConfig['host'] ?? null)
            && ((int) ($resolvedConfig['port'] ?? 0)) === ((int) ($originalConfig['port'] ?? 0))) {
            return $resolvedConfig;
        }

        $resolvedHost = $resolvedConfig['host'] ?? null;

        if (! is_string($resolvedHost) && ! is_int($resolvedHost)) {
            return $originalConfig;
        }

        return $this->canResolveSentinelMasterHost((string) $resolvedHost)
            ? $resolvedConfig
            : $originalConfig;
    }

    /**
     * Whether a sentinel-returned host can be resolved by this process. A literal
     * IP is always fine; otherwise this does a SYNCHRONOUS gethostbynamel() probe —
     * a deliberate resolvability check, reached only when the resolved host differs
     * from the configured one (see the equal-host fast path above).
     */
    protected function canResolveSentinelMasterHost(string $host): bool
    {
        $hostname = $this->normalizeHostForDnsLookup($host);

        if ($hostname === '') {
            return false;
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return $this->hostResolvesToAddress($hostname);
    }

    /**
     * Whether a hostname resolves to any usable address. Checks IPv4 (A records)
     * via the system resolver first — gethostbynamel() honours /etc/hosts and the
     * resolv.conf search domains, which matters for cluster-internal names — then
     * falls back to an AAAA lookup. The AAAA check is essential: gethostbynamel()
     * is IPv4-only, so on an IPv6-only cluster an AAAA-only master would otherwise
     * be wrongly discarded and the connector would keep using a stale fallback.
     * Overridable so tests can verify the resolvability wiring without real DNS.
     */
    protected function hostResolvesToAddress(string $hostname): bool
    {
        if (gethostbynamel($hostname) !== false) {
            return true;
        }

        $aaaa = @dns_get_record($hostname, DNS_AAAA);

        return is_array($aaaa) && $aaaa !== [];
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

    /**
     * Reduce a Redis host value to the bare hostname/IP for a DNS lookup: strip a
     * scheme (tcp://…), unwrap [IPv6] brackets, and drop a trailing :port.
     */
    private function normalizeHostForDnsLookup(string $host): string
    {
        $hostname = preg_replace('/^[a-z][a-z0-9+.-]*:\/\//i', '', trim($host)) ?? trim($host);

        if (str_starts_with($hostname, '[')) {
            $closingBracket = strpos($hostname, ']');

            return $closingBracket === false ? $hostname : substr($hostname, 1, $closingBracket - 1);
        }

        if (substr_count($hostname, ':') === 1) {
            return (string) substr($hostname, 0, (int) strrpos($hostname, ':'));
        }

        return $hostname;
    }
}
