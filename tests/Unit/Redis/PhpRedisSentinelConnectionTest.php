<?php

namespace Laravel\Sail\Tests\Unit\Redis;

use Laravel\Sail\Redis\PhpRedisSentinelConnection;
use Laravel\Sail\Tests\TestCase;
use RedisException;

/**
 * Unit-tests the retry-on-RedisException behavior. The connection class itself
 * is independent of phpredis — it routes commands to whatever object the
 * connector closure returns, so a plain anonymous client suffices for the test
 * without any extension dependency.
 */
class PhpRedisSentinelConnectionTest extends TestCase
{
    public function test_returns_result_when_command_succeeds(): void
    {
        $client = new RecordingFakeClient(['get' => 'value']);
        $connector = fn () => $client;

        $connection = new PhpRedisSentinelConnection($client, $connector);

        $this->assertSame('value', $connection->command('get', ['key']));
        $this->assertSame(1, $client->callsTo('get'));
    }

    public function test_retries_once_on_redis_exception_using_fresh_client(): void
    {
        $clientA = new RecordingFakeClient(['get' => new RedisException('master gone')]);
        $clientB = new RecordingFakeClient(['get' => 'recovered']);

        // Connector returns clientB on the reconnect call (simulates Sentinel
        // re-resolution producing a connection to the freshly-elected master).
        $callIndex = 0;
        $connector = function () use (&$callIndex, $clientB) {
            // The connection constructor calls connector() once for $this->client,
            // but in this test we pass clientA directly so the FIRST connector
            // invocation we observe here is the post-failure retry.
            $callIndex++;
            return $clientB;
        };

        $connection = new PhpRedisSentinelConnection($clientA, $connector);

        $this->assertSame('recovered', $connection->command('get', ['key']));
        $this->assertSame(1, $clientA->callsTo('get'), 'First client should have received exactly one call');
        $this->assertSame(1, $clientB->callsTo('get'), 'Second client (post-Sentinel reresolve) should have received exactly one retry');
        $this->assertSame(1, $callIndex, 'Connector closure should be called exactly once for the retry');
    }

    public function test_propagates_exception_when_retry_also_fails(): void
    {
        $clientA = new RecordingFakeClient(['get' => new RedisException('master gone')]);
        $clientB = new RecordingFakeClient(['get' => new RedisException('still broken')]);

        $connector = fn () => $clientB;
        $connection = new PhpRedisSentinelConnection($clientA, $connector);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('still broken');

        $connection->command('get', ['key']);
    }

    public function test_rethrows_original_when_no_connector(): void
    {
        $client = new RecordingFakeClient(['get' => new RedisException('original')]);

        $connection = new PhpRedisSentinelConnection($client, null);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('original');

        $connection->command('get', ['key']);
    }
}

/**
 * Minimal phpredis-shaped fake. Each entry in $responses is the per-method
 * scripted result; if the value is a Throwable it gets thrown, otherwise it's
 * returned. Tracks call counts so tests can assert each client received
 * exactly the expected number of invocations.
 */
class RecordingFakeClient
{
    private array $callCounts = [];

    public function __construct(private readonly array $responses) {}

    public function __call(string $method, array $arguments): mixed
    {
        $this->callCounts[$method] = ($this->callCounts[$method] ?? 0) + 1;

        $response = $this->responses[$method] ?? null;
        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    public function callsTo(string $method): int
    {
        return $this->callCounts[$method] ?? 0;
    }
}
