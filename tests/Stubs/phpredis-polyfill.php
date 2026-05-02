<?php

/*
 * Stub declarations for the bits of ext-redis our unit tests reference.
 * Only ever loaded by tests/bootstrap.php when the real extension is absent.
 *
 * If you're seeing a "cannot redeclare class" error from this file, your env
 * has ext-redis installed and bootstrap.php is including this anyway — that's
 * a bug in bootstrap.php's guard.
 */

class RedisException extends RuntimeException
{
}

class RedisSentinel
{
    public function __construct(array $config = [])
    {
    }

    public function getMasterAddrByName(string $service): mixed
    {
        return false;
    }
}
