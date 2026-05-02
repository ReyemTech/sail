#!/usr/bin/env bash
# Phase 3 orchestrator: bring up the redis-sentinel stack, build the
# test-runner image (php + phpredis), run phpunit inside the container that
# shares the docker network, then tear everything down.
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
COMPOSE="docker compose -f $DIR/docker-compose.yml"

cleanup() {
    $COMPOSE --profile tests down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "==> Building test runner image"
$COMPOSE --profile tests build tests >/dev/null

echo "==> Starting redis sentinel stack"
$COMPOSE up -d --wait redis-master redis-replica-1 redis-replica-2 sentinel-1 sentinel-2 sentinel-3

echo "==> Waiting for sentinel quorum to settle"
for _ in $(seq 1 20); do
    if $COMPOSE exec -T sentinel-1 redis-cli -p 26379 -a redis-test-pw --no-auth-warning sentinel get-master-addr-by-name mymaster 2>/dev/null | head -1 | grep -q .; then
        break
    fi
    sleep 1
done

echo "==> Running integration phpunit suite inside test container"
$COMPOSE run --rm tests vendor/bin/phpunit --testsuite Integration --testdox

echo "==> PASS"
