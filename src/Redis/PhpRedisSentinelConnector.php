<?php

namespace Laravel\Sail\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connectors\PhpRedisConnector;
use Illuminate\Support\Arr;
use RedisException;
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
 *                                 retry on a transient DNS failure. Defaults to
 *                                 [100, 250, 500, 1000] (≤ 4 retries, 5 attempts).
 *
 * Resilience: in Kubernetes the sentinel-returned master is sometimes a headless
 * pod hostname CoreDNS cannot resolve yet, and the stable service name briefly
 * returns EAI_AGAIN ("Try again"). Two behaviors guard against that transient
 * window, both backward compatible (when DNS is healthy the first attempt
 * succeeds and the resolved master is used, exactly as before):
 *   1. connect() retries on transient DNS failures with bounded backoff.
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
    /**
     * Default delays (ms) before each connect retry after a transient DNS failure.
     *
     * @var list<int>
     */
    private const DEFAULT_CONNECT_RETRY_BACKOFF_MILLISECONDS = [100, 250, 500, 1000];

    /**
     * Establish a Sentinel-aware connection, retrying transient DNS failures with
     * bounded backoff. Non-transient failures (config/auth/refused, and any
     * RedisException that is not a transient-DNS error) re-throw immediately.
     */
    public function connect(array $config, array $options): PhpRedisConnection
    {
        $backoff = $this->connectRetryBackoffMilliseconds($config);
        $attempts = max(1, count($backoff) + 1);
        $lastException = null;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            try {
                return $this->establishSentinelConnection($config, $options);
            } catch (RedisException $exception) {
                $lastException = $exception;
            } catch (Throwable $exception) {
                // A non-RedisException that isn't a transient DNS error is a real
                // failure — surface it now rather than masking it behind retries.
                if (! $this->isTransientDnsFailure($exception)) {
                    throw $exception;
                }

                $lastException = $exception;
            }

            if (! $this->isTransientDnsFailure($lastException) || $attempt === $attempts - 1) {
                throw $lastException;
            }

            $this->sleepBeforeConnectRetry($backoff[$attempt]);
        }

        throw $lastException;
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

        return gethostbynamel($hostname) !== false;
    }

    /**
     * Whether an exception is a transient DNS resolution failure that is safe to
     * retry. There is no typed exception for this, so detection is string-matching
     * on the message — centralized here so it's easy to extend. Matches getaddrinfo
     * / php_network_getaddresses failures whose cause is transient (EAI_AGAIN and
     * friends), NOT permanent errors like "Connection refused".
     */
    protected function isTransientDnsFailure(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        if (! str_contains($message, 'getaddrinfo') && ! str_contains($message, 'php_network_getaddresses')) {
            return false;
        }

        return str_contains($message, 'try again')
            || str_contains($message, 'name or service not known')
            || str_contains($message, 'name does not resolve')
            || str_contains($message, 'temporary failure');
    }

    /**
     * Sleep before the next connect retry. Overridable so tests don't actually wait.
     */
    protected function sleepBeforeConnectRetry(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
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
     * The connect-retry backoff schedule from `connect_retry_backoff_ms`, or the
     * default. Non-numeric / negative entries are dropped; an empty result falls
     * back to the default so retries are never accidentally disabled by bad config.
     *
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    private function connectRetryBackoffMilliseconds(array $config): array
    {
        $configured = $config['connect_retry_backoff_ms'] ?? null;

        if (! is_array($configured) || $configured === []) {
            return self::DEFAULT_CONNECT_RETRY_BACKOFF_MILLISECONDS;
        }

        $backoff = [];

        foreach ($configured as $delay) {
            if (is_numeric($delay) && (int) $delay >= 0) {
                $backoff[] = (int) $delay;
            }
        }

        return $backoff === []
            ? self::DEFAULT_CONNECT_RETRY_BACKOFF_MILLISECONDS
            : $backoff;
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
