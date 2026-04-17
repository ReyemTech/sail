#!/bin/sh
set -e

MODE="${SAIL_LOG_MODE:-both}"

case "$MODE" in
  stdout|file|both) ;;
  *)
    echo "php-fpm-log-prepare: invalid SAIL_LOG_MODE='$MODE' (expected stdout|file|both)" >&2
    exit 1
    ;;
esac

if [ "$MODE" = "stdout" ]; then
    exit 0
fi

mkdir -p /var/log/php-fpm /var/log/php
chown -R sail:sail /var/log/php-fpm /var/log/php
chmod 02755 /var/log/php-fpm /var/log/php
