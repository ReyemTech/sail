<?php

/*
 * PHPUnit bootstrap.
 *
 * The package's Redis tests target classes from ext-redis (RedisSentinel,
 * RedisException) which won't always be installed locally — production runs
 * with phpredis 6.2.0 in the sail container, but contributors and CI runners
 * may not have the extension loaded. Polyfill the bare minimum we need so
 * unit tests stay isolated from the C extension; success-path coverage is
 * provided by the Phase 3 integration test against a real Sentinel stack.
 */

require_once __DIR__.'/../vendor/autoload.php';

if (! extension_loaded('redis')) {
    require_once __DIR__.'/Stubs/phpredis-polyfill.php';
}
