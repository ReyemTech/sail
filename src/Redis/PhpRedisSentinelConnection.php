<?php

namespace Laravel\Sail\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use RedisException;

/**
 * PhpRedis connection that tolerates a master failover mid-request. On a
 * RedisException, it forces the connector closure to re-run — which queries
 * Sentinel for the new master — and retries the command exactly once before
 * giving up. Net effect: the user-visible error count during a failover
 * window drops from "every request" to "zero" for any single command.
 */
class PhpRedisSentinelConnection extends PhpRedisConnection
{
    public function command($method, array $parameters = [])
    {
        try {
            return parent::command($method, $parameters);
        } catch (RedisException $e) {
            if (! $this->connector) {
                throw $e;
            }

            // The connector closure re-resolves the master via Sentinel before
            // creating a new phpredis client, so this retry hits the new master.
            $this->client = call_user_func($this->connector);

            return $this->client->{$method}(...$parameters);
        }
    }
}
