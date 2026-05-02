#!/usr/bin/env bash
# Phase 0 smoke test: bring up the local sentinel stack, ask sentinel for the
# master, kill the master container, and assert sentinel reports a NEW master
# within the failover timeout.
#
# Run from the package root:
#   bash tests/Integration/redis-sentinel/smoke.sh
set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
COMPOSE="docker compose -f $DIR/docker-compose.yml"
PW=redis-test-pw
SVC=mymaster

cleanup() { $COMPOSE down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT

echo "==> Starting stack"
$COMPOSE up -d --wait

echo "==> Waiting for sentinel quorum to settle"
for _ in $(seq 1 20); do
    if redis-cli -h 127.0.0.1 -p 26379 -a "$PW" --no-auth-warning sentinel get-master-addr-by-name "$SVC" 2>/dev/null | head -1 | grep -q .; then
        break
    fi
    sleep 1
done

initial_addr=$(redis-cli -h 127.0.0.1 -p 26379 -a "$PW" --no-auth-warning sentinel get-master-addr-by-name "$SVC")
echo "==> Initial master: $(echo "$initial_addr" | tr '\n' ' ')"

# The bootstrap master is `redis-master` container. Stop it to trigger failover.
echo "==> Stopping redis-master to trigger failover"
$COMPOSE stop redis-master >/dev/null

echo "==> Waiting up to 15s for sentinel to elect a new master"
new_addr=""
for _ in $(seq 1 15); do
    new_addr=$(redis-cli -h 127.0.0.1 -p 26379 -a "$PW" --no-auth-warning sentinel get-master-addr-by-name "$SVC")
    if [ "$new_addr" != "$initial_addr" ] && [ -n "$new_addr" ]; then
        break
    fi
    sleep 1
done

echo "==> New master: $(echo "$new_addr" | tr '\n' ' ')"

if [ "$new_addr" = "$initial_addr" ] || [ -z "$new_addr" ]; then
    echo "FAIL: sentinel did not elect a new master after stopping the bootstrap master"
    exit 1
fi

echo "PASS: sentinel failover succeeded"
