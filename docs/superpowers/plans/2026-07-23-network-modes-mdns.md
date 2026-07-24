# Plan 2c — mDNS resolver + two polish fixes

Branch: `feat/network-modes` (PR #27). TDD; small atomic commits.

## Baseline (captured before work)
- `vendor/bin/phpunit`: 124 tests, 278 assertions, 21 skipped, green.
- `vendor/bin/phpstan analyse src`: `[OK] No errors`.
- `shellcheck bin/sail`: SC2155x2, SC2178x2, SC2206x3 (no findings to add).
- `shellcheck bin/sail-setup`: SC1035x3, SC2034x2, SC2086x3.
- Integration guard `tests/Integration/sail-up-uses-override.sh`: passes.

## Key reconciliation
`NetworkConfigTest` pins `config('sail.network.resolver')` default to `mdns`;
existing LAN tests + feature-test #4 require the **effective LAN default to be
nip.io**. Therefore `applyLanConfig` derives the resolver as
`--resolver option ?: 'nip'` (validated to {nip,mdns}) and does NOT fall through
to the config's mdns default — nip.io stays the LAN default, mdns is opt-in via
`--resolver=mdns`. Config default stays `mdns` (roadmap preference, untouched).

## Tasks (TDD — failing test first)

1. **LanEnvironment mdns** — unit tests: `resolver=mdns` → `<slug>.local`
   domain, values wire `SAIL_RESOLVER=mdns`; nip still works; reject unknown
   resolver. Then implement: `.local` branch, keep nip, throw for others.

2. **applyLanConfig wiring** — add `--resolver` to `sail:network` +
   `sail:install`; thread resolver into `applyLanConfig(...$resolver)`; default
   nip, mdns builds `<project>.local`, writes `SAIL_RESOLVER`.

3. **Avahi sidecar** — `SharedProxyStack::projectOverride(?network, bool $mdns)`
   emits an `avahi-publish` service (`network_mode: host`, mounts
   `/var/run/dbus` + `/var/run/avahi-daemon`, runs
   `avahi-publish -a -R ${SAIL_DOMAIN} ${SAIL_BIND_IP}`) only when `$mdns`.
   Unit-test both YAML shapes. Requires a host `avahi-daemon` (documented).

4. **Feature test** — `--mode=lan --resolver=mdns` writes `.local` domain +
   override with avahi sidecar; `--resolver=nip`/default = nip.io, no sidecar.

5. **README** — document `--resolver=mdns` (`.local`, needs host avahi-daemon;
   Android mDNS-unreliable caveat → nip.io stays default).

## Polish fixes
6. **Podman honor** — `bin/sail` `network create sail-shared` +
   `ProxyCommand` docker calls use `SAIL_DOCKER_BINARY` / `getenv(...) ?: 'docker'`.
7. **Cert collision** — `bin/sail-setup generate_certificates()`: LAN mode skips
   generic `public.crt`/`private.key` copies (shared certs dir); local unchanged.

## Un-CI-able (needs real-hardware validation)
- Avahi sidecar runtime (`avahi-publish`, host avahi-daemon, `.local` resolution
  from other LAN devices; Android caveat).
- `bin/sail` / `bin/sail-setup` runtime — verified via `bash -n` + shellcheck +
  review only.
