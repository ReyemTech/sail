<?php

namespace Laravel\Sail\Tests\Unit\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Laravel\Sail\Redis\PhpRedisConnector;
use Laravel\Sail\Redis\PhpRedisSentinelConnector;
use Laravel\Sail\Redis\RedisSentinelServiceProvider;
use Laravel\Sail\Tests\TestCase;
use RedisException;
use ReflectionMethod;

class PhpRedisConnectorTest extends TestCase
{
    public function test_direct_connections_retry_setup_without_replaying_commands(): void
    {
        $this->assertTrue(class_exists(PhpRedisConnector::class));

        $connector = new class extends PhpRedisConnector {
            public int $attempts = 0;

            protected function createClient(array $config)
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new RedisException('LOADING Redis is loading the dataset in memory');
                }

                return new class {
                    public function get($key)
                    {
                        return $key;
                    }

                    public function incr($key)
                    {
                        throw new RedisException('LOADING Redis is loading the dataset in memory');
                    }
                };
            }
        };

        $connection = $connector->connect(['connect_retry_backoff_ms' => [0]], []);
        $this->assertSame(PhpRedisConnection::class, get_class($connection));
        $this->assertSame('key', $connection->command('get', ['key']));
        $this->assertSame(2, $connector->attempts);

        try {
            $connection->command('incr', ['counter']);
            $this->fail('Command-level LOADING must not cause a new setup retry.');
        } catch (RedisException $exception) {
            $this->assertStringStartsWith('LOADING', $exception->getMessage());
        }
        $this->assertSame(2, $connector->attempts);
    }

    public function test_provider_registers_direct_and_sentinel_connectors(): void
    {
        $this->app->register(RedisSentinelServiceProvider::class);
        config()->set('database.redis.client', 'phpredis');
        $redis = $this->app->make('redis');
        $method = new ReflectionMethod($redis, 'connector');
        $method->setAccessible(true);

        $this->assertInstanceOf(PhpRedisConnector::class, $method->invoke($redis));
        config()->set('database.redis.client', 'phpredis-sentinel');
        $this->app->forgetInstance('redis');
        $this->assertInstanceOf(PhpRedisSentinelConnector::class, $method->invoke($this->app->make('redis')));
    }
}
