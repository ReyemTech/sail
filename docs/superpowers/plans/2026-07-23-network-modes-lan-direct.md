# Plan 3 — `lan-direct` mode + plain-HTTP option

Branch: `feat/network-modes` (PR #27). Builds on the existing `local` and `lan`
(shared-proxy) modes. **Do not edit `stubs/compose.stub`.** `local` and `lan`
must stay behaviorally identical.

## Pre-work bugfix (separate commit)
- `SharedProxyStack::avahiSidecar()` emits `avahi-publish -a -R ...`. `-R` is not
  portable across avahi builds. Drop it → `avahi-publish -a ${SAIL_DOMAIN} ${SAIL_BIND_IP}`.
  Update `SharedProxyStackTest` + `NetworkLanSharedTest` assertions.
  Commit: `fix(network): drop non-portable -R from avahi-publish sidecar`.

## Part A — `lan-direct` mode (per-project distinct LAN IP, standard ports)
Concept: each project binds its OWN `nginx-proxy` (from compose.stub) to a
DISTINCT real LAN IP aliased onto the host NIC. Standard ports 80/443/3306/…,
no collisions, no override, no `SAIL_FILES`, no `sail-shared`. Linux/macOS only
(NIC aliasing needs root); NOT Docker-Desktop-compatible.

TDD tasks:
1. `LanEnvironment`: add `tls` (scheme) + `mode` params → `SAIL_NETWORK_MODE`,
   http/https URLs, `SAIL_NETWORK_TLS`. `COMPOSE_PROFILES=lan` only for `lan`.
   (unit test)
2. Trait `applyLanDirectConfig(?ip, ?domain, ?resolver, tls)`: REQUIRE an IP
   (`--ip`, or reuse a stored lan-direct bind IP; else throw with a hint from
   `HostIpDetector::detect()`). Reuse `LanEnvironment`. Writes mode/bind/domain/
   URLs/tls. Clears `SAIL_FILES` + `COMPOSE_PROFILES`. NO port offsets. (feature test)
3. Wire `sail:network --mode=lan-direct` + `sail:install --mode=lan-direct`
   (add `--ip` to install). (feature test)
4. `--mode=local` revert already clears `SAIL_FILES`/`COMPOSE_PROFILES` and
   restores `SAIL_BIND_IP` from `SAIL_IP` — verify it covers lan-direct.
5. `bin/sail-setup`: cert target = vendor certs (like local) for lan-direct;
   alias IP on the **LAN interface** (default route iface), not loopback; skip
   `/etc/hosts`. Idempotent. `bash -n` + shellcheck.
6. `bin/sail`: NEEDS_SETUP must re-run setup when the lan-direct LAN alias is
   missing (reboot gap). Shared-proxy bootstrap guard is exact-match `"lan"` →
   already excludes lan-direct (verify).
7. README section.

## Part B — plain-HTTP option (lan + lan-direct)
- `--tls` / `--no-tls` on `sail:network` + `sail:install`; honor `SAIL_NETWORK_TLS`.
- TLS OFF → `APP_URL`/`VITE_DEV_SERVER_URL` use `http://`; `bin/sail-setup` skips
  mkcert entirely. TLS ON → current mkcert behavior. Default: ON (no regression).
- Feature-test http vs https URL wiring; bash-guard the cert-skip.

## Gates
- `vendor/bin/phpunit` green (baseline 131). `phpstan analyse src` → No errors.
- `bash -n bin/sail bin/sail-setup`. shellcheck no-new-findings vs baseline
  (sail-setup: SC1035×3 SC2034×2 SC2086×3; sail: SC2155×2 SC2178×2 SC2206×3).
- `bash tests/Integration/sail-up-uses-override.sh` PASS.
- local + lan (shared) byte-identical: verify via targeted feature tests +
  `git diff` on compose.stub (untouched).

## Un-CI-able (manual, real-hardware)
- lan-direct NIC IP aliasing (root, default-route iface detection).
- plain-HTTP cert-skip in bin/sail-setup.
- mDNS avahi-publish sidecar runtime.
