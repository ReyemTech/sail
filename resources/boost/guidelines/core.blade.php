{{-- ReyemTech Sail – Laravel Boost guideline --}}

# ReyemTech Sail

- Provides Docker/Helm scaffolding for Laravel apps (web, worker, scheduler).
- Ships Helm stubs (deployments, ingress, secrets, scheduler CronJob, vendor PVC).
- `sail:build` command builds images and Helm chart; supports non-interactive flags.

## Install / Update
- Install package per project composer requirements.
- During `php artisan boost:install`, accept `reyemtech/sail` when prompted; if installation already ran, rerun `php artisan boost:update --discover` and accept it.
- Before either discovery command, back up `CLAUDE.md`.
- After discovery, diff `CLAUDE.md` against the backup; restore `CLAUDE.md` from the backup if any existing section disappears.

## Build Command (sail:build)
- Non-interactive flags:
  - `--environments=local,production`
  - `--architectures=linux/amd64,linux/arm64`
  - `--repository=ghcr.io|registry.gitlab.com|docker.io|none`
  - `--organization=acme`
  - `--domains=app.example.com,api.example.com`
  - `--build-version=1.2.3`
  - `--push` (push built images)
  - `--use-previous` (reuse last saved config, no prompts)
  - `--bump=patch|minor|major|no` (increments version; `no` keeps current)
- If any flag is provided, prompts are skipped. `repository=none` disables push.
- Writes `.env` keys: `SAIL_BUILD_*`, `SAIL_DEPLOY_DOMAINS`, `VITE_DEV_SERVER_URL`.

## Helm Notes
- Helm stubs live in `stubs/helm`.
- Scheduler CronJob mounts a vendor PVC when `scheduler.vendorPvc.enabled` (default true).
  - PVC name: `<name>-scheduler-vendor`
  - Default size: `5Gi`, storage class `sata`; override via `values.yaml`.
- Images derive from `global.tag` / `sail.build.version`; repositories use `<project>-web` / `<project>-worker`.

## Common Tasks
- Build with stored config and bump patch: `php artisan sail:build --use-previous --bump=patch`.
- Fully specified build: `php artisan sail:build --environments=production --architectures=linux/amd64,linux/arm64 --repository=ghcr.io --organization=acme --domains=app.example.com --build-version=1.2.3 --push --bump=no`.

## AI Hints
- Prefer non-interactive flags in automation (CI/CD).
- Keep vendor PVC enabled for scheduler to avoid repeated `composer install`.
- Update Helm values for storage class/size when cluster defaults differ.

# Sail LAN Networking

- When `ERR_SSL_UNRECOGNIZED_NAME_ALERT` occurs in LAN shared-proxy mode and the app container is stuck in `created`, remember that this documented collision chain is not a certificate problem; check port and subnet collisions before changing certificates or mkcert.

## Shared Proxy Invariant

- Remember that every LAN project shares one `nginx-proxy` on one `SAIL_BIND_IP`.
- Give every project a unique `SAIL_SUBNET` and unique published ports.
- Read the assigned project slot from `~/.config/sail/registry.json`; Sail derives allocated ports as `base + slot * 10`.
- Expect automatic offsets for `mysql`, `pgsql`, `mariadb`, `redis`, and `valkey`, plus only the Mailpit dashboard through `FORWARD_MAILPIT_DASHBOARD_PORT`.
- Allocate `SAIL_SUBNET`, `VITE_PORT`, Mailpit SMTP through `FORWARD_MAILPIT_PORT`, and published ports for `minio`, `gotenberg`, and `typesense` yourself; Sail does not currently offset them.
- Check existing allocations before changing values:
  - Networks: `docker network inspect $(docker network ls -q) --format '@{{.Name}} @{{range .IPAM.Config}}@{{.Subnet}}@{{end}}'`
  - Ports: `docker ps --format '@{{.Names}}\t@{{.Ports}}'`

## Diagnose SNI Rejection Bottom-Up

- Follow the actual failure chain:

```text
port or subnet already taken on the host
  -> app container fails to start and stays in `created`
  -> no running `VIRTUAL_HOST` container on `sail-shared`
  -> nginx-proxy generates no vhost for the domain
  -> TLS has no matching SNI server block
  -> ERR_SSL_UNRECOGNIZED_NAME_ALERT
```

- Run `docker compose ps -a`; confirm whether the app container is `running`, `created`, or `exited`.
- Run `docker inspect <container> --format '@{{.State.Error}}'`; use this as the source of the real startup error.
- Do not rely on `docker logs` for a container that never started; its logs are empty and the error exists only in `.State.Error`.
- Run `docker exec <proxy> grep <domain> /etc/nginx/conf.d/default.conf`; confirm that the proxy generated the vhost.
- Inspect certificates only after the container is running and the vhost exists.
- Interpret `Pool overlaps with other one on this address space` as another project already holding that `SAIL_SUBNET`.

## Verify The Fix

- Verify with the shared trusted root instead of the browser cache:

```bash
curl -sS -o /dev/null -w '%{http_code} tls=%{ssl_verify_result}\n' \
  --cacert ~/.config/sail/certs/mkcert-rootCA.pem https://<domain>/
```

- Read `tls=0` as successful certificate verification.
- Retry a `502` immediately after `up -d`; it usually means the application is still booting.
