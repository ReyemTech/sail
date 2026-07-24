# Configurable Network Modes — Design

**Status:** Draft, awaiting user review
**Date:** 2026-07-23
**Related:** `config/sail.php`, `stubs/compose.stub`, `src/Console/Concerns/InteractsWithDockerComposeServices.php`

## Problem

ReyemTech Sail works for local, single-machine development but cannot serve a project to **other devices on the same LAN**. Running `sail up` on a network server (a homelab box, a spare machine, a shared dev server) and then reaching the web app, dashboards, or databases from a laptop or phone does not work today.

The root cause is the fork's networking model. Every published port binds to a single address, `SAIL_IP` (default `172.20.0.10`):

```yaml
# stubs/compose.stub
- "${SAIL_IP:-172.20.0.10}:80:80"        # nginx-proxy
- '${SAIL_IP:-172.20.0.10}:3306:3306'    # mysql
- '${SAIL_IP:-172.20.0.10}:5173:5173'    # vite
```

`172.20.0.10` lives inside the Docker bridge subnet (`172.20.0.0/24`). It is only reachable **on the server host itself** — it is not routable from any other device on the LAN. So nothing published is visible across the network.

Three specific blockers follow from this:

1. **Bind address is host-local.** All ports pinned to a Docker-range IP no other device can reach.
2. **`SAIL_IP` is overloaded.** In `writePorjectEnv()` it is used both as the host publish-bind address *and* as the seed for the Docker bridge subnet:
   ```php
   $subnet = substr($ip, 0, strrpos($ip, '.')) . '.0/24';
   $writer->set('SAIL_IP', $ip);
   $writer->set('SAIL_SUBNET', $subnet);
   ```
   You cannot simply repoint `SAIL_IP` at the server's LAN IP without corrupting the bridge subnet (it would become the real LAN subnet and collide).
3. **URL + routing assume host-local resolution.** `APP_URL=https://${SAIL_DOMAIN}` and `nginx-proxy` routes purely by `Host` header, so any other device must both resolve `SAIL_DOMAIN` to the server *and* send that Host header. Laravel's generated URLs, redirects, session domain, and Vite HMR must all agree on that name.

### Why the current design doesn't already collide (and why that matters)

Two projects both default to `172.20.0.10:3306:3306`, `172.20.0.10:80:80`, etc. They coexist today only because `sail:install` prompts for a **distinct `SAIL_IP` per project** (`.10`, `.11`, `.12`…) — same standard ports, different bind address. The per-project-IP scheme is the entire collision-avoidance mechanism, and `nginx-proxy` is defined **per-project** in `compose.stub`, so Host-header multiplexing only happens within one project's proxy, not across projects.

This is important because the naive fix — binding everything to `0.0.0.0` — throws that mechanism away and reintroduces port collisions across projects (worse: two per-project `nginx-proxy` containers both grabbing `:80`).

## Goals

- Make a project reachable from other devices on the LAN — web app, Vite HMR, HTTP dashboards (Mailpit, MinIO, etc.), and **raw-TCP services** (mysql/pgsql, redis/valkey) directly.
- Ship a **configurable, multi-mode** networking model, not a homelab-specific hack. Modes are selectable and documented for the OSS audience.
- **Batteries-included**: in LAN mode Sail auto-detects the LAN IP, allocates non-colliding host resources across all projects on the machine, wires `APP_URL` + Vite, and advertises a resolvable name — with no manual host surgery for the common path.
- **Zero regression for existing installs.** The current behavior becomes an explicit `local` mode that stays the untouched default.
- Keep the committed repo artifacts **machine-agnostic** so multiple developers sharing a repo never fight over generated files.

## Non-Goals

- macvlan / per-container LAN identity. Conceptually clean but broken on Docker Desktop and fragile with host↔container comms and router promiscuous-mode requirements. Documented as a footnote, not built.
- Public-internet exposure, reverse tunnels, or auth. LAN trust boundary only.
- Automatic router/DHCP reservation. Reserving IPs outside the DHCP pool (when `lan-direct` is used) remains the operator's responsibility.

## Architecture Overview

The design separates four concerns that `SAIL_IP` + `.env` currently conflate:

| Concern | Owner | Lifetime |
|---|---|---|
| **Intent** (which mode, resolver, TLS) | `config/sail.php` `network` section | Committed, team-wide |
| **Compose structure** (services, images, `${}`-templated ports) | `docker-compose.yml` | Committed, machine-agnostic, regenerated only on structural change |
| **Per-machine values** (bind IP, allocated ports, domain, active profiles) | `.env` (git-ignored) | Per-machine, generated/auto-healed |
| **Cross-project allocation** (which ports/IPs/domains are taken on this host) | Host registry `~/.config/sail/` | Per-machine, spans all projects |

### Core primitive: decouple bind address from bridge subnet

Introduce `SAIL_BIND_IP`, used **only** in `ports:` mappings. `SAIL_SUBNET` stays internal (`172.20.0.0/24`) and independent. `writePorjectEnv()` stops deriving the subnet from the bind address. Default `SAIL_BIND_IP=172.20.0.10` → byte-identical behavior for existing installs.

```yaml
- '${SAIL_BIND_IP:-172.20.0.10}:${FORWARD_DB_PORT:-3306}:3306'
```

### Two orthogonal axes

**`SAIL_NETWORK_MODE`** — what/where we bind:
- `local` (default) — today's behavior. Per-project Docker-range bind IP, per-project `nginx-proxy`, `.test` domain, host-only. Untouched.
- `lan` — exposed to the local network via a shared server-wide proxy + per-project port offsets (see below).
- `lan-direct` (advanced, Linux only) — per-project LAN IP alias on the host NIC, standard ports, per-project proxy retained. Requires host IP aliasing; opt-in.

**`SAIL_RESOLVER`** — how other devices resolve the name (independent of mode):
- `mdns` — `<project>.local` advertised by an `avahi-publish` sidecar (`profiles: [lan]`, `network_mode: host`, talks to the host `avahi-daemon` over the mounted D-Bus/avahi sockets). Prettiest; **Android browsers resolve `.local` unreliably** — documented caveat.
- `nip` — `<project>.<lan-ip>.nip.io`, seeded from the detected LAN IP. Resolves from anywhere on the LAN with zero client config, including Android. The phone-friendly fallback.
- `manual` — Sail sets the domain and values but advertises nothing; operator wires `/etc/hosts` or LAN DNS.

### LAN mode addressing (the crux decision)

`lan` mode uses **shared proxy + per-project port offsets** (Approach 1 from brainstorming), chosen because it makes the fewest host assumptions and is the only portable option across Linux servers *and* Docker Desktop:

- **Web + all HTTP dashboards** multiplex through **one server-wide `nginx-proxy`** bound to the LAN IP `:80/:443`, disambiguated by domain (`project-a.local`, `project-b.local`). Unlimited projects, zero web collision. This shared proxy is a **host-level stack managed by Sail** (in `~/.config/sail/`), *not* baked into any project's compose — every project's committed compose stays identical regardless of how many projects run.
- **Raw-TCP services** (mysql/pgsql/redis/valkey) publish on the LAN IP with a **per-project port offset** allocated and recorded by the host registry (e.g. project-a mysql `3306`, project-b mysql `3316`). The `FORWARD_*_PORT` env vars already in the stubs are the lever; the registry supplies collision-free values.

Trade-off accepted: DB GUIs connect on the project's *assigned* port, not always `3306`. `lan-direct` exists for users who require standard ports and can reserve host IPs.

### Compose mechanics — the committed file does not churn

The committed `docker-compose.yml` stays a stable, `${}`-templated, machine-agnostic file. It is regenerated **only on structural change** (`sail:install`, `sail:add`), exactly as today. Per-machine and per-mode variance is expressed *without* rewriting it:

- **Per-machine values** live in the git-ignored `.env` (`SAIL_BIND_IP`, `FORWARD_*_PORT`, `SAIL_DOMAIN`, `COMPOSE_PROFILES`). Two developers on the same repo with different LAN IPs / modes / OSes get the same committed file and never collide in git.
- **Mode-specific services** (e.g. the mDNS sidecar) are tagged with docker-compose `profiles:` and toggled by a per-machine `COMPOSE_PROFILES` value — no file rewrite to switch modes:
  ```yaml
  mdns:
    profiles: [lan]
    network_mode: host
    command: avahi-publish -a -R ${SAIL_DOMAIN} ${SAIL_BIND_IP}
    volumes:
      - /var/run/dbus:/var/run/dbus
      - /var/run/avahi-daemon:/var/run/avahi-daemon
  ```

### Host registry

A per-machine state store at `~/.config/sail/` (JSON) owns what is scarce on the host: allocated raw-TCP ports, `lan-direct` IP aliases, and claimed domains, keyed by project. Responsibilities:

- Allocate a collision-free set for a project on first LAN bring-up; return the same set on subsequent calls (idempotent).
- Release a project's allocation on teardown / project deletion (with a `prune` path for stale entries whose project directory no longer exists).
- Because it is per-machine, it coordinates across projects **on one box only** — different developers' machines allocate independently and never need to agree.

## Command Surface

Two entry points, deliberately separated so a cloner never overwrites shared structure:

- **`sail:install` (structural, run once by the project creator)** — generates the committed compose structure *and* the creator's local `.env`. Gains a `--mode=` flag and interactive prompt (via the existing `InteractsWithDockerPrompts` pattern) plus `--resolver=` / `--tls`. Detects the LAN IP in `lan` mode and seeds intent into `config/sail.php`.
- **`sail:network` (per-machine allocation, idempotent)** — detects this machine's LAN IP, consults/updates the host registry, writes/refreshes *this machine's* `.env` networking values, and (re)starts the shared proxy + resolver as needed. Safe to run repeatedly; the command a cloner runs. Subcommands: `sail:network up|status|prune|--mode=`.
- **`sail up` auto-heal** — before bringing services up, detects a missing or stale networking `.env` (e.g. fresh clone, or mode set in `config/sail.php` but not yet allocated locally) and transparently runs the `sail:network` allocation path. Fresh clone → `sail up` just works. Explicit re-runs / overrides go through `sail:network`.

## Auto-wiring in LAN mode

When a project comes up in `lan` mode, Sail ensures the following are consistent (written to the git-ignored `.env`, derived from the detected IP + resolver + registry allocation):

- `APP_URL` matches the resolvable domain (`https://<project>.local` or the nip.io host).
- Vite `server.host` / `hmr.host` point at the resolvable domain and `VITE_PORT` binds to `SAIL_BIND_IP`, so HMR websockets don't fall back to localhost.
- The shared `nginx-proxy` is running host-wide and this project is registered via `VIRTUAL_HOST`.
- The resolver sidecar (mDNS) or nip.io domain advertises/points to `SAIL_BIND_IP`.

## TLS

Configurable, off the critical path:
- Default on a trusted LAN: allow plain HTTP for exposed mode (no cert friction).
- `--tls` / `network.tls` opt-in: `mkcert`-issued cert for the domain (with the domain in SANs); the operator imports the mkcert CA on client devices. `.local` and nip.io names cannot obtain public-CA certs, so this is the trusted-HTTPS path.

## Backward Compatibility

- `SAIL_NETWORK_MODE` unset ⇒ `local`. `SAIL_BIND_IP` unset ⇒ `172.20.0.10`. `SAIL_SUBNET` still `172.20.0.0/24`. Existing `.env` files and committed compose files behave identically; no migration required.
- `SAIL_IP` may be retained as a deprecated alias that seeds `SAIL_BIND_IP` for one release to avoid breaking any consumer that sets it directly.

## Open Questions for the Plan

- Exact host-registry file schema and locking (concurrent `sail up` across projects).
- Whether the shared proxy is a Sail-managed compose stack in `~/.config/sail/` vs. a documented external service; lifecycle on last-project-down.
- LAN IP detection heuristic across Linux server vs. Docker Desktop (macOS/Windows) and multi-NIC hosts.
- Port-offset allocation scheme (fixed stride vs. next-free) and how it surfaces the assigned DB port to the user (`sail:network status`).

## Testing Strategy

- **Feature tests (Testbench):** `local` mode generates today's exact compose + `.env` (regression lock). `lan` mode writes expected `.env` keys, `COMPOSE_PROFILES`, and resolved `APP_URL`/Vite wiring. `sail:install` vs `sail:network` separation: `sail:network` never rewrites committed compose.
- **Registry unit tests:** allocation is collision-free and idempotent across N simulated projects; prune removes stale entries; release frees resources.
- **Integration:** compose renders and validates (`docker compose config`) in each mode; profiles gate the mdns sidecar correctly.
- **Manual matrix (documented):** reach web + dashboard + raw DB from a second device via mDNS and via nip.io; `lan-direct` on Linux with an IP alias.
