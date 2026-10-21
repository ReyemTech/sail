<?php

namespace Laravel\Sail\Redis;

use ErrorException;
use Illuminate\Redis\Connections\PhpRedisConnection;
use RedisException;
use Throwable;

/** Bounded retries for connection setup, before any application command runs. */
trait RetriesRedisConnections
{
    protected function retryRedisConnection(callable $connect, array $config): PhpRedisConnection
    {
        $backoff = $this->connectRetryBackoffMilliseconds($config);

        for ($attempt = 0; ; $attempt++) {
            try {
                return $connect();
            } catch (Throwable $exception) {
                if (! $this->isTransientConnectionFailure($exception) || $attempt >= count($backoff)) {
                    throw $exception;
                }

                $this->sleepBeforeConnectRetry($backoff[$attempt]);
            }
        }
    }

    protected function isTransientConnectionFailure(Throwable $exception): bool
    {
        if ($this->isTransientDnsFailure($exception)) {
            return true;
        }

        if (! $exception instanceof RedisException && ! $exception instanceof ErrorException) {
            return false;
        }

        $message = strtolower(trim($exception->getMessage()));

        return str_contains($message, 'connection refused')
            || str_contains($message, 'read error on connection')
            // A Redis server error code, optionally prefixed by a PHP warning
            // such as "Redis::auth():". Do not match arbitrary LOADING text.
            || preg_match('/(?:^|\\):\\s*)loading(?:\\s|$)/i', $message) === 1;
    }

    /**
     * Whether an exception is a transient DNS resolution failure that is safe to
     * retry. There is no typed exception for this, so detection is string-matching
     * on the message — centralized here so it's easy to extend. Matches getaddrinfo
     * / php_network_getaddresses failures whose cause is transient (EAI_AGAIN and
     * friends). Transport failures are classified separately below.
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
     * The connect-retry backoff schedule from `connect_retry_backoff_ms`, or the
     * default. Non-numeric / negative entries are dropped; an empty result falls
     * back to the default so retries are never accidentally disabled by bad config.
     *
     * @param  array<string, mixed>  $config
     * @return list<int>
     */
    private function connectRetryBackoffMilliseconds(array $config): array
    {
        $default = [100, 250, 500, 1000];
        $configured = $config['connect_retry_backoff_ms'] ?? null;

        if (! is_array($configured) || $configured === []) {
            return $default;
        }

        $backoff = [];

        foreach ($configured as $delay) {
            if (is_numeric($delay) && (int) $delay >= 0) {
                $backoff[] = (int) $delay;
            }
        }

        return $backoff === []
            ? $default
            : $backoff;
    }
}
