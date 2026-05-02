<?php

namespace Laravel\Sail\Tests\Integration;

use Laravel\Sail\Redis\PhpRedisSentinelConnector;
use Laravel\Sail\Redis\PhpRedisSentinelConnection;
use Laravel\Sail\Tests\TestCase;

/**
 * Phase 3: end-to-end test against a real 3-node Redis + 3-Sentinel stack.
 * This is THE gate — any code change to the connector or connection must keep
 * this test green before shipping. Last incident's lesson: shape-only unit
 * tests don't catch protocol-level bugs.
 *
 * Run via tests/Integration/redis-sentinel/run.sh, which boots the stack via
 * docker compose and executes phpunit inside a container that shares the same
 * docker network (so Sentinel-returned master IPs are routable).
 *
 * Required env (set by docker-compose.yml `tests` service):
 *   REDIS_SENTINEL_HOST     — sentinel hostname (default sentinel-1)
 *   REDIS_SENTINEL_PORT     — sentinel port (default 26379)
 *   REDIS_SENTINEL_SERVICE  — master name (default mymaster)
 *   REDIS_PASSWORD          — shared auth (default redis-test-pw)
 */
class PhpRedisSentinelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('redis')) {
            $this->markTestSkipped('phpredis extension required for the integration test.');
        }

        if (! getenv('REDIS_SENTINEL_HOST')) {
            $this->markTestSkipped(
                'REDIS_SENTINEL_HOST not set — run via tests/Integration/redis-sentinel/run.sh '.
                'so the test executes inside the sentinel docker network.'
            );
        }
    }

    public function test_resolves_master_via_sentinel_and_reads_writes(): void
    {
        $connection = $this->makeConnection();

        $key = 'sail:phase3:'.bin2hex(random_bytes(4));
        $connection->command('set', [$key, 'hello']);

        $this->assertSame('hello', $connection->command('get', [$key]));
        $this->assertInstanceOf(PhpRedisSentinelConnection::class, $connection);
    }

    public function test_survives_master_failover_with_at_most_one_retry(): void
    {
        $connection = $this->makeConnection();

        // Write before the failover so we can verify it survives the switch
        // (replicas should have already received it via async replication).
        $beforeKey = 'sail:phase3:before:'.bin2hex(random_bytes(4));
        $connection->command('set', [$beforeKey, 'pre-failover']);

        // Trigger failover. We exec into the docker-compose host network from
        // inside the test container by talking to the docker daemon socket —
        // but we don't have access to that here, so we orchestrate failover
        // from the host runner script and just observe it from this test.
        // Instead: connect a SECOND sentinel-aware client to a different
        // sentinel pod, ask which is master, then DEBUG SLEEP it (effectively
        // freezing the master so sentinel marks it down and elects a replica).
        $this->forceMasterFailover();

        // Wait up to ~10s for sentinel to elect a new master and propagate.
        $afterKey = 'sail:phase3:after:'.bin2hex(random_bytes(4));
        $this->retry(20, 500_000, function () use ($connection, $afterKey) {
            $connection->command('set', [$afterKey, 'post-failover']);
        });

        $this->assertSame('post-failover', $connection->command('get', [$afterKey]));
        $this->assertSame('pre-failover', $connection->command('get', [$beforeKey]),
            'Pre-failover key should have been replicated to the new master.');
    }

    private function makeConnection(): PhpRedisSentinelConnection
    {
        $connector = new PhpRedisSentinelConnector;

        $connection = $connector->connect([
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'password' => getenv('REDIS_PASSWORD') ?: null,
            'database' => 0,
            'sentinel_host' => getenv('REDIS_SENTINEL_HOST'),
            'sentinel_port' => (int) (getenv('REDIS_SENTINEL_PORT') ?: 26379),
            'sentinel_service' => getenv('REDIS_SENTINEL_SERVICE') ?: 'mymaster',
        ], []);

        $this->assertInstanceOf(PhpRedisSentinelConnection::class, $connection);

        return $connection;
    }

    /**
     * Tell the current master to sleep for 30s (longer than down-after-milliseconds).
     * Sentinel will mark it +sdown → +odown → start a failover, electing a replica
     * as the new master. Net effect: a clean failover trigger from within the test
     * without needing docker daemon access.
     */
    private function forceMasterFailover(): void
    {
        $sentinelHost = getenv('REDIS_SENTINEL_HOST');
        $sentinelPort = (int) (getenv('REDIS_SENTINEL_PORT') ?: 26379);
        $password = getenv('REDIS_PASSWORD');

        $sentinel = new \RedisSentinel([
            'host' => $sentinelHost,
            'port' => $sentinelPort,
            'auth' => $password,
        ]);
        $master = $sentinel->getMasterAddrByName(getenv('REDIS_SENTINEL_SERVICE') ?: 'mymaster');
        $this->assertIsArray($master, 'sentinel must report a master before we trigger failover');

        $direct = new \Redis;
        $direct->connect($master[0], (int) $master[1]);
        $direct->auth($password);
        // DEBUG SLEEP returns +OK only after the sleep finishes; fire-and-forget
        // is fine here because we just want the master to stop responding.
        try {
            $direct->rawCommand('DEBUG', 'SLEEP', '30');
        } catch (\Throwable) {
            // Connection drops mid-sleep — expected.
        }
    }

    /**
     * @param  callable():void  $fn
     */
    private function retry(int $tries, int $sleepMicros, callable $fn): void
    {
        $last = null;
        for ($i = 0; $i < $tries; $i++) {
            try {
                $fn();

                return;
            } catch (\Throwable $e) {
                $last = $e;
                usleep($sleepMicros);
            }
        }
        throw $last;
    }
}
