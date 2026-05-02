<?php

namespace Laravel\Sail\Redis;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the `phpredis-sentinel` Redis client driver. Kept in its own
 * non-deferred provider so it's installed before the first call to
 * Redis::connection(), independent of when SailServiceProvider (which is
 * deferred) loads.
 */
class RedisSentinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving('redis', function (RedisFactory $redis) {
            $redis->extend('phpredis-sentinel', function () {
                return new PhpRedisSentinelConnector;
            });
        });
    }
}
