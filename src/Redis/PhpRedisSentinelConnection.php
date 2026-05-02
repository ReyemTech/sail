<?php

namespace Laravel\Sail\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use RedisException;

/**
 * PhpRedis connection that tolerates a Redis master failover mid-request. On a
 * RedisException, the connection re-runs the connector closure — which queries
 * Sentinel for the freshly-elected master — and retries the command, with
 * exponential backoff between attempts to give Sentinel time to complete its
 * own election cycle (Bitnami default `down-after-milliseconds` is 30s and the
 * election itself takes another ~1-5s after that).
 *
 * Defaults: 3 retries with 200ms / 500ms / 1000ms backoff (≤1.7s total wait).
 * Override per-connection via the config keys:
 *   - sentinel_retries: int  — total retry attempts after the initial failure
 *   - sentinel_retry_backoff_ms: int[]  — backoff per attempt (ms)
 *
 * If neither override is set, the defaults handle the common case where a
 * Redis pod crashes (Sentinel detects via TCP RST in <1s and elects within
 * ~1-2s). The slower case — a graceful pod delete that gives Sentinel the
 * full down-after-milliseconds to detect — is a deliberate operation, not
 * a steady-state failure mode.
 */
class PhpRedisSentinelConnection extends PhpRedisConnection
{
    /** @var int[] backoff ladder in milliseconds */
    private const DEFAULT_BACKOFF_MS = [200, 500, 1000];

    public function command($method, array $parameters = [])
    {
        try {
            return parent::command($method, $parameters);
        } catch (RedisException $e) {
            if (! $this->connector) {
                throw $e;
            }

            return $this->retryWithSentinelReresolve($method, $parameters, $e);
        }
    }

    /**
     * @param  string  $method
     * @param  list<mixed>  $parameters
     * @throws RedisException
     */
    protected function retryWithSentinelReresolve(string $method, array $parameters, RedisException $original): mixed
    {
        $backoff = $this->config['sentinel_retry_backoff_ms'] ?? self::DEFAULT_BACKOFF_MS;
        $attempts = (int) ($this->config['sentinel_retries'] ?? count($backoff));

        $lastException = $original;

        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $sleepMs = $backoff[$attempt] ?? end($backoff);
            usleep($sleepMs * 1000);

            try {
                // Connector re-resolves master via Sentinel + creates a fresh client.
                $this->client = call_user_func($this->connector);

                return $this->client->{$method}(...$parameters);
            } catch (RedisException $e) {
                $lastException = $e;
                // Loop and try again with the next backoff step.
            }
        }

        throw $lastException;
    }
}
