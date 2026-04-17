#!/bin/sh
set -e

MODE="${SAIL_LOG_MODE:-both}"

case "$MODE" in
  stdout|file|both) ;;
  *)
    echo "nginx-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

# In stdout mode, s6-log writes nothing to disk; skip the directory setup
# so the chart works on pods with readOnlyRootFilesystem and no writable
# /var/log mount.
if [ "$MODE" = "stdout" ]; then
    exit 0
fi

mkdir -p /var/log/nginx
chown root:root /var/log/nginx
chmod 02755 /var/log/nginx
