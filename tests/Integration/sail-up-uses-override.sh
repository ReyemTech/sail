#!/usr/bin/env bash
# Regression guard: bin/sail's `up` branch must launch docker compose via
# "${COMPOSE_CMD[@]}" (built at the top of the script from SAIL_FILES), not a
# bare `docker compose up`. A bare invocation silently drops any `-f`
# override assembled from SAIL_FILES — in lan mode that means the
# per-project compose override (which gates the local nginx-proxy behind a
# `standalone` profile and joins the app to the shared `sail-shared`
# network) never gets applied, so the shared-proxy design goes inert.
#
# This runs entirely offline: a fake `docker` on PATH just logs every
# invocation's arguments and exits 0, so no real Docker daemon is required.
#
# Run from the package root:
#   bash tests/Integration/sail-up-uses-override.sh
set -uo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SAIL_BIN="$REPO_ROOT/bin/sail"

WORKDIR=$(mktemp -d)
cleanup() { rm -rf "$WORKDIR"; }
trap cleanup EXIT

FAKE_BIN_DIR="$WORKDIR/fakebin"
mkdir -p "$FAKE_BIN_DIR"

ARGS_LOG="$WORKDIR/docker-args.log"
: > "$ARGS_LOG"

# Fake `docker`: log the full argument list for every invocation and
# succeed unconditionally, so bin/sail's "is the image built?" / "bring
# containers up" checks never need a real daemon.
cat > "$FAKE_BIN_DIR/docker" <<FAKE_DOCKER_EOF
#!/usr/bin/env bash
echo "\$*" >> "$ARGS_LOG"
exit 0
FAKE_DOCKER_EOF
chmod +x "$FAKE_BIN_DIR/docker"

PROJECT_DIR="$WORKDIR/project"
mkdir -p "$PROJECT_DIR/vendor/reyemtech/sail/runtimes/8.x"

OVERRIDE_FILE="$WORKDIR/ovr.yml"
echo "services: {}" > "$PROJECT_DIR/compose.yaml"
echo "services: {}" > "$OVERRIDE_FILE"

# SAIL_BIND_IP is set so the Plan-2a auto-heal block (sail:network) is
# skipped; SAIL_FILES is the thing under test — it must survive into the
# docker compose invocation via COMPOSE_CMD's assembled `-f` flags.
cat > "$PROJECT_DIR/.env" <<ENV_EOF
APP_NAME=testapp
SAIL_BUILD_ORGANIZATION=default
SAIL_BUILD_VERSION=default
SAIL_BIND_IP=127.0.0.1
SAIL_FILES=compose.yaml:$OVERRIDE_FILE
ENV_EOF

echo "==> Running 'bin/sail up' against a fake docker with SAIL_FILES set"

(
    cd "$PROJECT_DIR" || exit 1
    PATH="$FAKE_BIN_DIR:$PATH" \
        SAIL_SKIP_CHECKS=1 \
        SAIL_SKIP_SETUP=1 \
        bash "$SAIL_BIN" up
)
SAIL_RC=$?

echo "==> Captured docker invocations:"
cat "$ARGS_LOG"

if [ "$SAIL_RC" -ne 0 ]; then
    echo "FAIL: bin/sail up exited $SAIL_RC" >&2
    exit 1
fi

if ! grep -qF -- "-f $OVERRIDE_FILE" "$ARGS_LOG"; then
    echo "FAIL: docker compose was never invoked with the SAIL_FILES override ('-f $OVERRIDE_FILE')." >&2
    echo "This means bin/sail's 'up' branch bypassed \${COMPOSE_CMD[@]} — regression of the" >&2
    echo "SAIL_FILES / lan-mode compose-override bug." >&2
    exit 1
fi

if ! grep -qE -- "(^| )up($| )" "$ARGS_LOG"; then
    echo "FAIL: no logged docker invocation looked like a 'compose ... up' call." >&2
    exit 1
fi

echo "PASS: bin/sail up passes the SAIL_FILES override through to docker compose"
