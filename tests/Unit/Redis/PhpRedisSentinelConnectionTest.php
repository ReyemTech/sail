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
 *
 * All tests use sentinel_retry_backoff_ms=[0,0,0] to skip the real backoff
 * sleeps so the suite stays fast.
 */
class PhpRedisSentinelConnectionTest extends TestCase
{
    /** @var array<string,mixed> default retry config that disables sleep for speed */
    private const NO_SLEEP_CONFIG = ['sentinel_retry_backoff_ms' => [0, 0, 0]];

    public function test_returns_result_when_command_succeeds(): void
    {
        $client = new RecordingFakeClient(['get' => 'value']);
        $connector = fn () => $client;

        $connection = new PhpRedisSentinelConnection($client, $connector, self::NO_SLEEP_CONFIG);

        $this->assertSame('value', $connection->command('get', ['key']));
        $this->assertSame(1, $client->callsTo('get'));
    }

    public function test_retries_on_redis_exception_using_fresh_client(): void
    {
        $clientA = new RecordingFakeClient(['get' => new RedisException('master gone')]);
        $clientB = new RecordingFakeClient(['get' => 'recovered']);

        $connectorCalls = 0;
        $connector = function () use (&$connectorCalls, $clientB) {
            $connectorCalls++;

            return $clientB;
        };

        $connection = new PhpRedisSentinelConnection($clientA, $connector, self::NO_SLEEP_CONFIG);

        $this->assertSame('recovered', $connection->command('get', ['key']));
        $this->assertSame(1, $clientA->callsTo('get'));
        $this->assertSame(1, $clientB->callsTo('get'));
        $this->assertSame(1, $connectorCalls, 'connector should be called once on the first retry');
    }

    public function test_retries_multiple_times_with_backoff_on_repeated_failures(): void
    {
        // Three failures, then success on the fourth client. Mirrors the
        // worst case where Sentinel takes a few rounds of election before
        // returning a healthy master IP.
        $clientA = new RecordingFakeClient(['get' => new RedisException('attempt 0')]);
        $clientB = new RecordingFakeClient(['get' => new RedisException('attempt 1')]);
        $clientC = new RecordingFakeClient(['get' => new RedisException('attempt 2')]);
        $clientD = new RecordingFakeClient(['get' => 'finally']);

        $reconnects = [$clientB, $clientC, $clientD];
        $connector = function () use (&$reconnects) {
            return array_shift($reconnects);
        };

        $connection = new PhpRedisSentinelConnection($clientA, $connector, self::NO_SLEEP_CONFIG);

        $this->assertSame('finally', $connection->command('get', ['key']));
        $this->assertSame(1, $clientD->callsTo('get'), 'final client should have received the successful retry');
    }

    public function test_propagates_exception_when_all_retries_fail(): void
    {
        $clientA = new RecordingFakeClient(['get' => new RedisException('attempt 0')]);
        $clientB = new RecordingFakeClient(['get' => new RedisException('attempt 1')]);
        $clientC = new RecordingFakeClient(['get' => new RedisException('attempt 2')]);
        $clientD = new RecordingFakeClient(['get' => new RedisException('attempt 3')]);

        $reconnects = [$clientB, $clientC, $clientD];
        $connector = function () use (&$reconnects) {
            return array_shift($reconnects);
        };

        $connection = new PhpRedisSentinelConnection($clientA, $connector, self::NO_SLEEP_CONFIG);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('attempt 3');

        $connection->command('get', ['key']);
    }

    public function test_rethrows_original_when_no_connector(): void
    {
        $client = new RecordingFakeClient(['get' => new RedisException('original')]);

        $connection = new PhpRedisSentinelConnection($client, null, self::NO_SLEEP_CONFIG);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('original');

        $connection->command('get', ['key']);
    }

    public function test_respects_explicit_sentinel_retries_count(): void
    {
        // Override retry count to 1; default backoff array would give 3.
        $clientA = new RecordingFakeClient(['get' => new RedisException('boom')]);
        $clientB = new RecordingFakeClient(['get' => new RedisException('still boom')]);

        $connector = fn () => $clientB;

        $connection = new PhpRedisSentinelConnection($clientA, $connector, [
            'sentinel_retries' => 1,
            'sentinel_retry_backoff_ms' => [0],
        ]);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('still boom');

        $connection->command('get', ['key']);
        $this->assertSame(1, $clientB->callsTo('get'), 'should retry exactly once when sentinel_retries=1');
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
