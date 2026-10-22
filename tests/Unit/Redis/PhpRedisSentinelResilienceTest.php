<?php

namespace Laravel\Sail\Tests\Unit\Redis;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Laravel\Sail\Redis\PhpRedisSentinelConnector;
use Laravel\Sail\Tests\TestCase;
use Mockery;
use RedisException;
use Throwable;

/**
 * Unit-tests the DNS-resilience behavior folded into the base connector: connect()
 * retries transient DNS failures with bounded backoff, and resolveMasterFromSentinel
 * falls back to the configured host when the sentinel-returned master is not
 * resolvable. Kept extension-free (RedisException is polyfilled by tests/bootstrap
 * when ext-redis is absent) and DNS-free (host resolution is scripted or uses IP
 * literals) so it runs in any CI image without touching the network.
 */
class PhpRedisSentinelResilienceTest extends TestCase
{
    public function test_falls_back_to_configured_host_when_master_unresolvable(): void
    {
        $connector = new SentinelResilienceProbe;
        $connector->forceResolvable = false; // simulate an unresolvable sentinel host

        $configured = ['host' => 'redis.service', 'port' => 6379, 'password' => 'secret'];
        $sentinelMaster = ['host' => 'missing-master.invalid', 'port' => 6379, 'password' => 'secret'];

        $this->assertSame($configured, $connector->select($configured, $sentinelMaster));
    }

    public function test_uses_sentinel_master_when_it_is_an_ip(): void
    {
        $connector = new SentinelResilienceProbe; // no force — a literal IP resolves without DNS

        $sentinelMaster = ['host' => '10.0.0.42', 'port' => 6379];

        $this->assertSame(
            $sentinelMaster,
            $connector->select(['host' => 'redis.service', 'port' => 6379], $sentinelMaster)
        );
    }

    public function test_keeps_config_when_sentinel_and_config_agree(): void
    {
        $connector = new SentinelResilienceProbe;
        $config = ['host' => 'redis-main-master.data.svc.cluster.local', 'port' => 6379];

        // Equal host/port short-circuits — no DNS probe even for a non-IP host.
        $this->assertSame($config, $connector->select($config, $config));
    }

    public function test_normalizes_schemes_and_ports_before_the_ip_check(): void
    {
        $connector = new SentinelResilienceProbe;

        // Scheme + port stripped, [IPv6] unwrapped — all reduce to a literal IP,
        // so the fast path returns true without any DNS lookup.
        $this->assertTrue($connector->canResolve('tcp://10.0.0.42:6379'));
        $this->assertTrue($connector->canResolve('[2001:db8::1]'));
        $this->assertTrue($connector->canResolve('tcp://[2001:db8::1]:6379'));
    }

    public function test_ip_literals_bypass_the_resolver_and_non_ips_delegate_to_it(): void
    {
        // A hostname with only AAAA records must still count as resolvable — the
        // resolver seam covers A + AAAA, so gethostbynamel's IPv4-only blindness
        // can't discard a valid IPv6-only master. Here the seam is scripted so the
        // test never touches real DNS.
        $unresolvable = new class extends PhpRedisSentinelConnector {
            public bool $seamCalled = false;

            public function probe(string $host): bool
            {
                return $this->canResolveSentinelMasterHost($host);
            }

            protected function hostResolvesToAddress(string $hostname): bool
            {
                $this->seamCalled = true;

                return false;
            }
        };

        // IPv4 + IPv6 literals resolve via filter_var — the resolver is never consulted.
        $this->assertTrue($unresolvable->probe('10.0.0.42'));
        $this->assertTrue($unresolvable->probe('[2001:db8::1]'));
        $this->assertFalse($unresolvable->seamCalled, 'IP literals must not hit the resolver');

        // A non-IP host is delegated to the (A + AAAA) resolver seam.
        $this->assertFalse($unresolvable->probe('aaaa-only-master.cluster.local'));
        $this->assertTrue($unresolvable->seamCalled);
    }

    public function test_identifies_transient_dns_failures_as_retryable(): void
    {
        $connector = new SentinelResilienceProbe;

        $this->assertTrue($connector->isTransient(new RedisException(
            'php_network_getaddresses: getaddrinfo for redis-main-master.data.svc.cluster.local failed: Try again'
        )));
        $this->assertTrue($connector->isTransient(new RedisException(
            'php_network_getaddresses: getaddrinfo for redis-node-1.headless failed: Name does not resolve'
        )));
        // Not a DNS failure — must not be retried.
        $this->assertFalse($connector->isTransient(new RedisException('Connection refused')));
        // DNS-shaped word but not a getaddrinfo failure — must not match.
        $this->assertFalse($connector->isTransient(new RedisException('READONLY You can\'t write against a replica')));
    }

    public function test_retries_transient_dns_failures_then_succeeds(): void
    {
        $connection = Mockery::mock(PhpRedisConnection::class);

        $connector = new SentinelConnectRetryProbe;
        $connector->connectFailures = [
            new RedisException('php_network_getaddresses: getaddrinfo for redis-main-master failed: Try again'),
            new RedisException('php_network_getaddresses: getaddrinfo for redis-main-master failed: Try again'),
        ];
        $connector->successfulConnection = $connection;

        $result = $connector->connect(['connect_retry_backoff_ms' => [0, 0, 0, 0]], []);

        $this->assertSame($connection, $result);
        $this->assertSame(3, $connector->connectCalls);
    }

    public function test_throws_after_exhausting_connect_retries(): void
    {
        $connector = new SentinelConnectRetryProbe;
        $connector->connectFailures = array_fill(0, 5, new RedisException(
            'php_network_getaddresses: getaddrinfo for redis failed: Try again'
        ));

        try {
            $connector->connect(['connect_retry_backoff_ms' => [0, 0, 0, 0]], []);
            $this->fail('Expected the connector to throw after exhausting retries.');
        } catch (RedisException $e) {
            $this->assertStringContainsStringIgnoringCase('try again', $e->getMessage());
        }

        $this->assertSame(5, $connector->connectCalls); // 1 initial + 4 backoff retries
    }

    public function test_retries_loading_refusals_and_connect_read_errors_with_bounded_backoff(): void
    {
        foreach ([
            new RedisException('LOADING Redis is loading the dataset in memory'),
            new \ErrorException('Redis::auth(): LOADING Redis is loading the dataset in memory'),
            new RedisException('Connection refused'),
            new \ErrorException('Redis::connect(): read error on connection to redis:6379'),
        ] as $failure) {
            $connector = new SentinelConnectRetryProbe;
            $connector->connectFailures = [$failure, $failure];
            $connection = Mockery::mock(PhpRedisConnection::class);
            $connector->successfulConnection = $connection;

            $this->assertSame($connection, $connector->connect(['connect_retry_backoff_ms' => [5, 10]], []));
            $this->assertSame(3, $connector->connectCalls);
            $this->assertSame([5, 10], $connector->sleeps);
        }
    }

    public function test_stops_retrying_when_a_loading_failure_changes_to_auth_failure(): void
    {
        $connector = new SentinelConnectRetryProbe;
        $authFailure = new RedisException('WRONGPASS invalid username-password pair');
        $connector->connectFailures = [new RedisException('LOADING Redis is loading the dataset in memory'), $authFailure];

        try {
            $connector->connect(['connect_retry_backoff_ms' => [0, 0, 0]], []);
            $this->fail('Expected authentication failure.');
        } catch (RedisException $exception) {
            $this->assertSame($authFailure, $exception);
        }
        $this->assertSame(2, $connector->connectCalls);
    }

    public function test_persistent_loading_throws_the_final_failure_at_the_retry_limit(): void
    {
        $connector = new SentinelConnectRetryProbe;
        $last = new RedisException('LOADING final failure');
        $connector->connectFailures = [new RedisException('LOADING first failure'), $last];

        try {
            $connector->connect(['connect_retry_backoff_ms' => [25]], []);
            $this->fail('Expected final LOADING failure.');
        } catch (RedisException $exception) {
            $this->assertSame($last, $exception);
        }
        $this->assertSame(2, $connector->connectCalls);
        $this->assertSame([25], $connector->sleeps);
    }

    public function test_does_not_retry_unrelated_errors_that_mention_loading_or_connection_words(): void
    {
        foreach ([
            new RedisException('WRONGPASS credentials while loading configuration'),
            new RedisException('ERR script failed while LOADING data'),
            new \LogicException('Connection refused by application configuration'),
        ] as $failure) {
            $connector = new SentinelConnectRetryProbe;
            $connector->connectFailures = [$failure];
            try {
                $connector->connect(['connect_retry_backoff_ms' => [0, 0]], []);
                $this->fail('Unrelated errors must not be retried.');
            } catch (Throwable $exception) {
                $this->assertSame($failure, $exception);
            }
            $this->assertSame(1, $connector->connectCalls);
        }
    }

    public function test_does_not_retry_authentication_failures(): void
    {
        $connector = new SentinelConnectRetryProbe;
        $connector->connectFailures = [new RedisException('WRONGPASS invalid username-password pair')];

        try {
            $connector->connect(['connect_retry_backoff_ms' => [0, 0, 0, 0]], []);
            $this->fail('Expected authentication failure to be re-thrown immediately.');
        } catch (RedisException $e) {
            $this->assertStringContainsString('WRONGPASS', $e->getMessage());
        }

        $this->assertSame(1, $connector->connectCalls);
    }
}

/**
 * Exposes the protected resolution helpers, and lets a test force the DNS
 * resolvability verdict so the fallback logic can be verified without real DNS.
 */
class SentinelResilienceProbe extends PhpRedisSentinelConnector
{
    public ?bool $forceResolvable = null;

    /** @param array<string,mixed> $original @param array<string,mixed> $resolved @return array<string,mixed> */
    public function select(array $original, array $resolved): array
    {
        return $this->selectResolvableMasterConfig($original, $resolved);
    }

    public function canResolve(string $host): bool
    {
        return $this->canResolveSentinelMasterHost($host);
    }

    public function isTransient(Throwable $exception): bool
    {
        return $this->isTransientDnsFailure($exception);
    }

    protected function canResolveSentinelMasterHost(string $host): bool
    {
        // When a test forces the verdict, skip the real (IP-literal or DNS) probe.
        return $this->forceResolvable ?? parent::canResolveSentinelMasterHost($host);
    }
}

/**
 * Scripts establishSentinelConnection outcomes so the retry loop can be exercised
 * without a real Redis/Sentinel, and makes retries instant.
 */
class SentinelConnectRetryProbe extends PhpRedisSentinelConnector
{
    /** @var list<Throwable> */
    public array $connectFailures = [];

    public int $connectCalls = 0;

    public array $sleeps = [];

    public ?PhpRedisConnection $successfulConnection = null;

    protected function establishSentinelConnection(array $config, array $options): PhpRedisConnection
    {
        $this->connectCalls++;

        if ($this->connectFailures !== []) {
            throw array_shift($this->connectFailures);
        }

        return $this->successfulConnection ?? Mockery::mock(PhpRedisConnection::class);
    }

    protected function sleepBeforeConnectRetry(int $milliseconds): void
    {
        $this->sleeps[] = $milliseconds;
    }
}
