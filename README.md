<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="/art/reyemtech-logo-dark.png">
    <img height="56" src="/art/reyemtech-logo-light.png" alt="ReyemTech">
  </picture>
  &nbsp;&nbsp;&nbsp;&nbsp;
  <img height="56" src="/art/logo.svg" alt="Laravel Sail">
</p>

<p align="center">
<a href="https://packagist.org/packages/reyemtech/sail"><img src="https://img.shields.io/packagist/dt/reyemtech/sail" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/reyemtech/sail"><img src="https://img.shields.io/packagist/v/reyemtech/sail" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/reyemtech/sail"><img src="https://img.shields.io/packagist/l/reyemtech/sail" alt="License"></a>
</p>

## Introduction

**ReyemTech Sail** is a fork of [Laravel Sail](https://github.com/laravel/sail) that keeps everything you already use for local development — the `sail` CLI, the `docker-compose` services, the PHP runtimes — and extends it into a **build-and-ship toolchain** for getting a Laravel app from your laptop into a Kubernetes cluster.

Where upstream Sail stops at local Docker, this fork adds:

- **Multi-architecture image builds** (`linux/amd64` + `linux/arm64`) driven by Docker Bake, with optimized multi-stage production targets (cli/fpm).
- **Helm chart generation** — `sail:build` and `sail:helm` emit a complete, opinionated chart (web/worker/scheduler tiers, HPA, PDBs, ingress, external secrets, pre-sync migration jobs) straight from your project.
- **Multi-registry push with auto-authentication** — GHCR, Docker Hub, GitLab, Quay, Harbor, AWS ECR, and Azure ACR.
- **CI/CD pipeline generation** — one command emits a ready-to-run pipeline for GitHub Actions, GitLab CI, Azure DevOps, CircleCI, AWS CodeBuild, or Travis.
- **Non-interactive build flags** so the same commands work in CI as on your machine.
- **Production extras baked into the chart and runtime:** Redis Sentinel-aware PHP client, Typesense subchart, and a Laravel Nightwatch agent sidecar.

It is a **drop-in replacement** for `laravel/sail` — it uses the same `Laravel\Sail` namespace and conflicts with the upstream package, so install one or the other.

> **Relationship to upstream:** this fork periodically merges `laravel/sail` so the local-dev experience stays current. Everything in the [Laravel Sail documentation](https://laravel.com/docs/sail) applies here too; this README focuses on what the fork adds on top.

## Quickstart

From an empty `composer require` to a production image and Helm chart in four steps.

**1. Install into your Laravel app**

New Laravel apps ship with `laravel/sail` in `require-dev`. Remove it first — this fork uses the same `Laravel\Sail` namespace and **will collide** with the upstream package:

```bash
composer remove laravel/sail         # required: new Laravel apps include it by default
composer require reyemtech/sail --dev

php artisan sail:install --php=8.4   # or --php=8.5
php artisan sail:publish             # publish Docker runtimes, bin scripts, configs
```

**2. Develop locally**

```bash
./vendor/bin/sail up -d              # start the stack
./vendor/bin/sail artisan migrate    # run migrations
# app is now on http://localhost
```

Tip: install the [global `sail` wrapper](#multi-project-sail-wrapper) once and just run `sail up -d` from any project.

**3. Build a production image + Helm chart**

```bash
php artisan sail:build \
  --environments=production \
  --architectures=linux/amd64,linux/arm64 \
  --repository=ghcr.io \
  --organization=acme \
  --domains=app.example.com \
  --push \
  --bump=patch
```

This builds multi-arch images, pushes them to your registry (authenticating automatically), and generates a deployable Helm chart under `helm/`. See [Building images + Helm charts](#building-images--helm-charts) for every flag.

**4. Wire up CI (optional)**

```bash
php artisan sail:ci --provider=github-actions
```

Emits a pipeline that runs the same build on every push and tag. See [CI/CD generation](#cicd-generation) for the other providers.

## Commands

| Command | Purpose |
| --- | --- |
| `sail:install` | Initial project setup — `docker-compose.yml`, `.env`, PHPUnit config |
| `sail:add` | Add services to an existing installation |
| `sail:publish` | Publish Docker runtimes, `bin` scripts, and database configs |
| `sail:build` | Build multi-arch Docker images (optionally push) **and** generate the Helm chart |
| `sail:helm` | Regenerate the Helm chart only, merging new keys from `values.stub` |
| `sail:helm:validate` | Validate generated charts via `helm lint` |
| `sail:ci` | Generate a CI/CD pipeline (GitHub Actions, GitLab, Azure, CircleCI, CodeBuild, Travis) |

## Multi-project `sail` wrapper

The fork ships a `sail-wrapper` that resolves and runs the **nearest** project's `vendor/bin/sail`, so a single global `sail` works across every project on your machine.

It is installed automatically to `~/.local/bin/sail` (or `~/bin`) on `composer install`/`update`. Run it from anywhere:

```bash
cd /path/to/project-a && sail up -d             # uses project-a's Sail
cd /path/to/project-b && sail artisan migrate   # uses project-b's Sail
```

Manual install, if the automatic step is skipped:

```bash
cp vendor/reyemtech/sail/bin/sail-wrapper ~/.local/bin/sail
chmod +x ~/.local/bin/sail
```

Make sure the target directory is on your `PATH`:

```bash
export PATH="$HOME/.local/bin:$PATH"   # add to ~/.bashrc or ~/.zshrc
```

## Running on a LAN server

By default Sail binds to a host-local address, so a project is only reachable
on the machine running it. To reach it from other devices on your network
(laptop, phone), enable **LAN mode**:

```bash
# On the server, after install:
sail artisan sail:network --mode=lan          # auto-detects the LAN IP
# or pin the IP / domain explicitly:
sail artisan sail:network --mode=lan --ip=192.168.1.50
sail artisan sail:network --mode=lan --domain=dev.example.lan

sail up
```

LAN mode:
- Binds published ports to your server's LAN IP.
- Uses a `nip.io` domain (`<project>.<lan-ip>.nip.io`) that resolves from any
  device on the network — no `/etc/hosts` editing required, and it works on
  Android where mDNS/`.local` does not.
- Generates a trusted certificate with **mkcert**. To avoid TLS warnings on
  your other devices, import the mkcert root CA
  (`vendor/reyemtech/sail/certs/mkcert-rootCA.pem`) into each device's trust
  store once.

Return to local-only mode with `sail artisan sail:network --mode=local`.

### Choosing a name resolver

By default LAN mode uses **nip.io** (`<project>.<lan-ip>.nip.io`), which needs
no extra host services and resolves everywhere — including Android, where
mDNS/`.local` is unreliable. This is why nip.io stays the default.

If you prefer a clean `<project>.local` name, opt into the **mDNS** resolver:

```bash
sail artisan sail:network --mode=lan --resolver=mdns   # <project>.local
sail artisan sail:network --mode=lan --resolver=nip    # <project>.<ip>.nip.io (default)
```

mDNS mode:

- **Host prerequisite (required):** `avahi-daemon` must be **installed and
  running** on the Linux host. The `avahi-publish` sidecar is only a *client* —
  it registers the record with the host daemon over the D-Bus system bus; it
  does not itself answer mDNS. Without a running daemon the sidecar just
  restart-loops and `<project>.local` never resolves.

  ```bash
  sudo apt install -y avahi-daemon        # Debian/Ubuntu
  sudo systemctl enable --now avahi-daemon
  ```

  macOS already provides mDNS via Bonjour — no daemon to install.
- **Verify resolution** from any machine on the LAN once `sail up` is running:

  ```bash
  avahi-resolve -n <project>.local        # should print <project>.local <SAIL_BIND_IP>
  ```

  If it fails, check the sidecar logs: `docker logs <project>-avahi-publish-1`
  (apk/avahi errors are surfaced there, not silenced).
- Adds an `avahi-publish` sidecar (host-networked) to the project's compose
  override that advertises `<project>.local` → your `SAIL_BIND_IP` to other
  devices via the host daemon.
- **Android caveat:** many Android devices don't resolve `.local` names
  reliably — use nip.io for those clients.

Once resolved, `.local` and nip.io projects share the same LAN reverse proxy and
mkcert certificates described below.

### Multiple projects on one server

LAN mode uses a single shared reverse proxy so any number of projects can run
at once, each reachable at its own `nip.io` domain:

```bash
# In each project:
sail artisan sail:network --mode=lan
sail up      # auto-starts the shared proxy the first time
```

- Web traffic for every project is routed by domain through one shared proxy on
  `:80/:443` — no port juggling for the web apps.
- Each project's database/cache is published on a **unique** host port
  (e.g. project A MySQL `3306`, project B `3316`) so they don't collide; run
  `sail artisan sail:network --status` to see the assigned ports.
- Certificates live in a shared dir (`~/.config/sail/certs`); import the mkcert
  root CA (`~/.config/sail/certs/mkcert-rootCA.pem`) on client devices once.
- Manage the shared proxy directly with `sail artisan sail:proxy up|down|status`.

Return any project to local-only mode with `sail artisan sail:network --mode=local`.

### One dedicated LAN IP per project (`lan-direct`)

If you'd rather **not** share a proxy and want each project on its own real LAN
IP with the standard ports (`80/443/3306/…`) — no port juggling, no shared
network — use **`lan-direct`** mode. Each project runs its own `nginx-proxy`
bound to a distinct address you reserve on your LAN:

```bash
# Reserve a free address on your LAN subnet for THIS project, then:
sail artisan sail:network --mode=lan-direct --ip=192.168.1.61
sail up      # sail-setup aliases the IP onto your LAN NIC (needs sudo)
```

- Each project needs its **own** dedicated IP (`--ip` is required). Pick free
  addresses on your LAN subnet — ideally outside your router's DHCP pool so they
  aren't handed to other devices.
- The IP is aliased onto your host's **default-route (LAN) interface**, so the
  project is reachable from any device at `http(s)://<project>.<ip>.nip.io` (or
  `<project>.local` with `--resolver=mdns`) on standard ports.
- **Linux/macOS only, and NOT Docker-Desktop-compatible:** aliasing an IP onto
  the host NIC needs root and a real host network interface. Docker Desktop's
  VM-based networking can't publish to a NIC-aliased host IP. Use `lan` (shared
  proxy) on Docker Desktop.
- No `/etc/hosts` edits are needed — nip.io/.local resolve on their own.
- Certificates use the per-project `vendor/reyemtech/sail/certs` dir (same as
  local); import its `mkcert-rootCA.pem` on client devices.

Switch back with `sail artisan sail:network --mode=local`.

### Plain HTTP (no TLS)

By default every exposed mode issues a trusted **mkcert** certificate and serves
HTTPS. If you'd rather serve **plain HTTP** (e.g. quick throwaway testing, or a
device you can't install the root CA on), add `--no-tls`:

```bash
sail artisan sail:network --mode=lan --no-tls          # shared proxy, HTTP
sail artisan sail:network --mode=lan-direct --ip=192.168.1.61 --no-tls
sail artisan sail:network --mode=lan --tls             # back to HTTPS (default)
```

With TLS off, `APP_URL`/`VITE_DEV_SERVER_URL` use `http://` and `sail-setup`
skips mkcert entirely — `nginx-proxy` serves plain HTTP on `:80`. TLS stays
**on by default**, so existing setups are unaffected. `--tls`/`--no-tls` are
also available on `sail install`.

## Building images + Helm charts

`sail:build` builds multi-arch images via Docker Bake and generates the Helm chart in one step:

```bash
php artisan sail:build \
  --environments=production \
  --architectures=linux/amd64,linux/arm64 \
  --repository=ghcr.io \
  --organization=acme \
  --domains=app.example.com \
  --build-version=1.2.3 \
  --push \
  --use-previous \
  --bump=patch
```

Key flags:

- `--use-previous` — reuse the last saved build config without prompting (ideal for CI)
- `--bump=patch|minor|major|no` — bump the version non-interactively
- `--repository=none` — local-only build (disables push)
- `--remove-vendor-node-modules` / `--keep-vendor-node-modules` — strip or keep `vendor/` and `node_modules/` in the final image (stripped by default)

Validation rules:

- Environments must be within `local, production`
- Architectures must be in the package's allowed list
- Repository must be a known registry shorthand, `none`, or a full registry URL (e.g. `888657980245.dkr.ecr.us-east-1.amazonaws.com`)

### Registry support

`sail:build` authenticates against the target registry automatically, prompting only if you are not already logged in.

**Standard registries** — GitHub Container Registry (`ghcr.io`), Docker Hub (`docker.io`), GitLab (`registry.gitlab.com`), Quay (`quay.io`), Harbor, and any custom registry URL.

**AWS ECR:**

```bash
php artisan sail:build --repository=888657980245.dkr.ecr.us-east-1.amazonaws.com --push
php artisan sail:build --repository=ecr --push   # shorthand
# Requires: AWS CLI configured (aws configure). Optional: AWS_REGION, AWS_ACCOUNT_ID
```

**Azure ACR:**

```bash
php artisan sail:build --repository=myregistry.azurecr.io --push
php artisan sail:build --repository=azurecr --push   # shorthand
# Requires: Azure CLI logged in (az login). Optional: AZURE_ACR_NAME
```

## Helm

### Regenerate the chart

Regenerate the Helm chart without rebuilding images:

```bash
php artisan sail:helm                          # current version
php artisan sail:helm --chart-version=1.2.3    # specific version
php artisan sail:helm --bump=patch             # bump and regenerate
php artisan sail:helm --no-version-update      # skip Chart.yaml version bump
```

This refreshes templates from the stubs, **merges new keys from `values.stub` into your existing `values.yaml`** (without clobbering your overrides), updates `Chart.yaml`, and runs `helm lint`.

### Validate

```bash
php artisan sail:helm:validate
```

### What the chart includes

- **Tiers:** `web`, `worker`, and `scheduler` deployments, each independently configurable.
- **Autoscaling:** HPA enabled by default for the web tier, configurable per tier.
- **High availability:** configurable Pod Disruption Budgets.
- **ServiceAccounts:** optional creation with annotations.
- **External Secrets:** automatic API-version detection (`v1`/`v1beta1`).
- **Pre-sync jobs:** ArgoCD pre-sync hooks for image existence checks and database migrations.
- **Scheduler vendor PVC:** enabled by default (`scheduler.vendorPvc.*`), 5Gi, storage class `sata`.
- **Probes:** web readiness/liveness on `/up`.
- **Security defaults:** non-root `securityContext`, `fsGroup` 1000, resource requests/limits set in `values.stub`.
- **Typesense subchart** and **Laravel Nightwatch agent sidecar** support.

Stubs live in `stubs/helm`. User-owned overrides belong in `values.production.yaml`, which is never overwritten by regeneration.

### Rollback

```bash
helm history <release>
helm rollback <release> <revision>
```

## Redis Sentinel

The fork ships a Sentinel-aware phpredis client (`src/Redis/`) that discovers the current master through Sentinel and retries reconnects with backoff. Wire it up through the Helm chart's Redis Sentinel env vars, or use the connector directly in your Redis config.

## Docker runtimes

- PHP runtimes live in `runtimes/` as Docker Bake files: `runtimes/8.x` is parameterized via `PHP_VERSION` (default `8.4`) and `runtimes/8.5` reuses the same bake structure pinned to `PHP_VERSION=8.5`.
- Multi-stage targets: `base`, `app`, and `production` (cli/fpm).

## CI/CD generation

Generate a pipeline that builds images and Helm charts for your provider:

```bash
php artisan sail:ci                              # interactive
php artisan sail:ci --provider=github-actions
php artisan sail:ci --provider=gitlab-ci
php artisan sail:ci --provider=azure-devops
php artisan sail:ci --provider=circleci
php artisan sail:ci --provider=aws-codebuild
php artisan sail:ci --provider=travis
php artisan sail:ci --provider=github-actions --overwrite
```

| Provider | Output |
| --- | --- |
| GitHub Actions | `.github/workflows/build.yml` |
| GitLab CI/CD | `.gitlab-ci.yml` |
| Azure DevOps | `azure-pipelines/build.yml` |
| CircleCI | `.circleci/config.yml` |
| AWS CodeBuild | `buildspec.yml` |
| Travis CI | `.travis.yml` |

Every generated pipeline:

- Builds on push to `main`/`master` and on version tags (`v*`)
- Supports multi-architecture builds (amd64, arm64)
- Authenticates against the target registry (ECR, ACR, standard)
- Produces both Docker images and Helm charts
- Derives the version from git tags, falling back to a date-based version

### Required secrets / variables

| Provider | Configuration |
| --- | --- |
| GitHub Actions | `REGISTRY_USERNAME`, `REGISTRY_PASSWORD` (or `GHCR_IO_USERNAME`/`GHCR_IO_PASSWORD`); ECR: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`; ACR: `AZURE_CREDENTIALS` |
| GitLab CI | `REGISTRY_USERNAME`, `REGISTRY_PASSWORD` (or `CI_REGISTRY_USER`/`CI_REGISTRY_PASSWORD`); ECR: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`; ACR: `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET`, `AZURE_TENANT_ID` |
| Azure DevOps | `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`; service connections for ACR and AWS |
| CircleCI | `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`; ECR: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION` |
| AWS CodeBuild | `REGISTRY_USERNAME`, `REGISTRY_PASSWORD`; IAM role for ECR (no static credentials) |
| Travis CI | `REGISTRY_USERNAME`, `REGISTRY_PASSWORD` |

## Development

```bash
composer test                  # full suite (Orchestra Testbench)
composer test:feature
composer test:integration
vendor/bin/phpstan analyse src # static analysis (PHPStan level 0)
```

## Credits

Built on [Laravel Sail](https://github.com/laravel/sail) by Taylor Otwell and the Laravel community. ReyemTech Sail tracks upstream and layers the build/deploy tooling described above on top.

## Contributing & Security

- Issues: <https://github.com/reyemtech/sail/issues>
- Upstream Sail security policy: <https://github.com/laravel/sail/security/policy>
- License: [MIT](LICENSE.md)
