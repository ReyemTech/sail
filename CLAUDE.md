# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ReyemTech Sail is a fork of Laravel Sail extended with Docker multi-architecture builds, Helm chart generation/validation, multi-registry support, and CI/CD pipeline generation. It conflicts with `laravel/sail` and replaces it as a drop-in.

## Commands

```bash
# Testing (uses Orchestra Testbench)
composer test                    # All tests
composer test:feature            # Feature tests only
composer test:integration        # Integration tests only
vendor/bin/phpunit --filter=TestName  # Single test by name

# Static analysis
vendor/bin/phpstan analyse src   # PHPStan level 0
```

## Architecture

**Namespace:** `Laravel\Sail\` (PSR-4 from `src/`)

**Service Provider:** `SailServiceProvider` implements `DeferrableProvider`. Registers 9 artisan commands and pushes `ForceHttps` middleware in production.

**Console Commands** (`src/Console/`):
- `sail:install` — Initial project setup (docker-compose, .env, phpunit)
- `sail:add` — Add services to existing installation
- `sail:publish` — Publish Docker runtimes, bin scripts, database configs
- `sail:build` — Build Docker images with multi-arch support + generate Helm charts
- `sail:helm` — Regenerate Helm charts only (merges values.stub)
- `sail:helm:validate` — Validate Helm charts via `helm lint`
- `sail:ci` — Generate CI/CD configs (GitHub Actions, GitLab CI, Azure DevOps, CircleCI, AWS CodeBuild, Travis CI)
- `sail:network` — Report/allocate networking: `--mode=local|lan|lan-direct`, `--resolver=nip|mdns`, `--tls/--no-tls`, `--status`
- `sail:proxy` — Manage the shared LAN reverse proxy (`up|down|status`)

**Traits** (`src/Console/Concerns/`) — Commands compose behavior via traits:
- `InteractsWithDocker` — Build config, Dockerfile/bake generation, version bumping
- `InteractsWithDockerRegistry` — Multi-registry auth (ECR, ACR, GHCR, GitLab, Docker Hub, Quay, Harbor)
- `InteractsWithDockerComposeServices` — Service stubs and docker-compose management
- `InteractsWithDockerPrompts` — Interactive prompts with non-interactive CLI flag overrides
- `InteractsWithHelm` — Helm chart generation, values merging, validation

**Networking** (`src/Networking/`) — LAN-exposure modes. `local` (default) is untouched; `lan` puts every project behind one shared `nginx-proxy` via a generated per-project override (`~/.config/sail/overrides/`, wired through `SAIL_FILES`) so `stubs/compose.stub` stays byte-identical; `lan-direct` aliases a dedicated LAN IP per project.
- `HostIpDetector` / `HostRegistry` — LAN IP detection; per-project port-slot allocation (file-locked)
- `LanEnvironment` — builds the domain + `.env` values per resolver (`nip` → `<project>.<ip>.nip.io`, `mdns` → `<project>.local`)
- `SailHome` — host state paths (`~/.config/sail`: proxy, certs, overrides, registry)
- `SharedProxyStack` — renders the shared proxy compose + per-project override, incl. the mDNS sidecar
- `AvahiDetector` / `AvahiInterfaceDetector` — host mDNS readiness (daemon installed+running; host advertising the LAN IP for `<hostname>.local`). Both take an injectable `?callable $runner` test seam and are container-resolved so tests can bind fakes.

**mDNS notes** (hard-won, don't regress): the sidecar publishes a **CNAME** `<project>.local` → `<hostname>.local` — an A record owns the reverse PTR 1:1 and collides when projects share the proxy IP. It needs `security_opt: apparmor:unconfined` (AppArmor mediates the system D-Bus and denies `docker-default`), and re-registers on `NameOwnerChanged` because an avahi-daemon restart drops all client records. nip.io is the reliable default; mDNS is opt-in.

**Configuration** (`config/sail.php`): Build and deploy settings via `SAIL_BUILD_*` and `SAIL_DEPLOY_*` env vars. Config is persisted to `.env` using `mirazmac/dotenvwriter`.

**Templates** (`stubs/`): `.stub` files for docker-compose services, Helm charts (deployments, HPA, PDB, ingress, external secrets, ArgoCD presync), and CI/CD pipelines.

**Docker Runtimes** (`runtimes/8.x/`): Multi-stage builds using a single `docker-bake.hcl` with targets: base, app, production (cli/fpm). PHP version is the `PHP_VERSION` bake variable, so one runtime covers every supported 8.x release — there is deliberately no per-version runtime directory.

Frontend build-time config (`VITE_SENTRY_DSN`, `VITE_SENTRY_RELEASE`, `SENTRY_ORG`, `SENTRY_PROJECT`) is forwarded from `config('sail.build.args')` into the bake command; `SENTRY_AUTH_TOKEN` comes from `config('sail.build.secrets')` and is passed through the build process environment as a BuildKit `type=env` secret, never through the command line Sail prints.

## Code Style

- Laravel preset (StyleCI)
- 4-space indentation, LF line endings
- YAML files use 2-space indentation
