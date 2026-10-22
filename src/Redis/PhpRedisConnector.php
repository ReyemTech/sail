<?php

namespace Laravel\Sail\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Redis\Connectors\PhpRedisConnector as BasePhpRedisConnector;

/** Direct phpredis connections with the same bounded setup retries as Sentinel. */
class PhpRedisConnector extends BasePhpRedisConnector
{
    use RetriesRedisConnections;

    public function connect(array $config, array $options): PhpRedisConnection
    {
        return $this->retryRedisConnection(
            fn (): PhpRedisConnection => parent::connect($config, $options),
            $config
        );
    }
}
