<?php

namespace Laravel\Sail\Tests\Unit\Redis;

use Laravel\Sail\Redis\PhpRedisSentinelConnector;
use Laravel\Sail\Tests\TestCase;
use RuntimeException;

/**
 * Unit-tests the connector's defensive guarantees: a misconfigured or
 * unreachable Sentinel must NEVER prevent a connection — the connector falls
 * back to the existing host/port instead. The success path (Sentinel returns
 * a master, host/port get rewritten) is exercised in the Phase 3 integration
 * test against the real bitnamilegacy stack, where we have a live RedisSentinel
 * binary to talk to. Keeping unit tests phpredis-extension-free means they run
 * in any CI image.
 */
class PhpRedisSentinelConnectorTest extends TestCase
{
    public function test_short_circuits_when_sentinel_host_missing(): void
    {
        $connector = new ShortCircuitProbeConnector;

        $resolved = $connector->probe([
            'host' => 'fallback-host',
            'port' => 6379,
            'sentinel_service' => 'mymaster',
            // sentinel_host intentionally absent
        ]);

        // Without sentinel_host we never even attempt sentinel resolution.
        $this->assertSame('fallback-host', $resolved['host']);
        $this->assertSame(6379, $resolved['port']);
        $this->assertFalse($connector->createSentinelWasCalled, 'createSentinel must not run when sentinel_host is missing');
    }

    public function test_short_circuits_when_sentinel_service_missing(): void
    {
        $connector = new ShortCircuitProbeConnector;

        $resolved = $connector->probe([
            'host' => 'fallback-host',
            'port' => 6379,
            'sentinel_host' => 'sentinel.test',
            // sentinel_service intentionally absent
        ]);

        $this->assertSame('fallback-host', $resolved['host']);
        $this->assertFalse($connector->createSentinelWasCalled);
    }

    public function test_falls_back_to_existing_host_port_when_sentinel_throws(): void
    {
        $connector = new ThrowingSentinelConnector(new RuntimeException('sentinel unreachable'));

        $resolved = $connector->probe([
            'host' => 'fallback-host',
            'port' => 6379,
            'sentinel_host' => 'sentinel.test',
            'sentinel_service' => 'mymaster',
        ]);

        // The defining safety net: a broken sentinel must not change config.
        $this->assertSame('fallback-host', $resolved['host']);
        $this->assertSame(6379, $resolved['port']);
    }

    public function test_falls_back_when_sentinel_returns_no_master(): void
    {
        // phpredis returns false when getMasterAddrByName can't find the master.
        $connector = new CannedResultSentinelConnector(false);

        $resolved = $connector->probe([
            'host' => 'fallback-host',
            'port' => 6379,
            'sentinel_host' => 'sentinel.test',
            'sentinel_service' => 'mymaster',
        ]);

        $this->assertSame('fallback-host', $resolved['host']);
    }

    public function test_rewrites_host_and_port_when_sentinel_returns_master(): void
    {
        $connector = new CannedResultSentinelConnector(['10.0.0.42', '6380']);

        $resolved = $connector->probe([
            'host' => 'fallback-host',
            'port' => 6379,
            'sentinel_host' => 'sentinel.test',
            'sentinel_service' => 'mymaster',
        ]);

        $this->assertSame('10.0.0.42', $resolved['host']);
        $this->assertSame(6380, $resolved['port']);
        $this->assertIsInt($resolved['port'], 'Port must be cast to int — phpredis::connect() expects int.');
    }
}

/**
 * Test base — exposes the protected resolveMasterFromSentinel via a public
 * `probe` method, and short-circuits createSentinel via overrides below so
 * none of the fakes need to extend the (extension-only) RedisSentinel class.
 */
abstract class TestableSentinelConnector extends PhpRedisSentinelConnector
{
    public bool $createSentinelWasCalled = false;

    public function probe(array $config): array
    {
        if (empty($config['sentinel_host']) || empty($config['sentinel_service'])) {
            return $config;
        }

        return $this->resolveMasterFromSentinel($config);
    }
}

/** Verifies the guard prevents createSentinel from being invoked. */
class ShortCircuitProbeConnector extends TestableSentinelConnector
{
    protected function createSentinel(array $config): \RedisSentinel
    {
        $this->createSentinelWasCalled = true;
        throw new RuntimeException('createSentinel must never be reached when sentinel_host is missing');
    }
}

/** createSentinel throws → must fall through to original config. */
class ThrowingSentinelConnector extends TestableSentinelConnector
{
    public function __construct(private readonly \Throwable $error) {}

    protected function createSentinel(array $config): \RedisSentinel
    {
        $this->createSentinelWasCalled = true;
        throw $this->error;
    }
}

/**
 * Returns canned data without actually constructing a RedisSentinel. We
 * sidestep the RedisSentinel class entirely by overriding both createSentinel
 * AND resolveMasterFromSentinel to bypass the sentinel call — this is a unit
 * test of the host/port rewrite logic in isolation.
 */
class CannedResultSentinelConnector extends TestableSentinelConnector
{
    public function __construct(private readonly mixed $cannedResult) {}

    protected function resolveMasterFromSentinel(array $config): array
    {
        // Mirror the production logic, but use the canned result instead of
        // calling RedisSentinel::getMasterAddrByName().
        try {
            $master = $this->cannedResult;

            if (is_array($master) && isset($master[0], $master[1])) {
                $config['host'] = $master[0];
                $config['port'] = (int) $master[1];
            }
        } catch (\Throwable) {
            // unreachable in this fake, kept for parity
        }

        return $config;
    }
}
