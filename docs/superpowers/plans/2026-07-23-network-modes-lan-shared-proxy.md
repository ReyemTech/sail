# Network Modes — LAN Shared Proxy / Multi-Project (Plan 2b of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let **multiple** Sail projects run simultaneously on one LAN server, each reachable from other devices, by replacing the per-project nginx-proxy (in `lan` mode) with ONE shared host-level nginx-proxy that routes every project by domain, plus per-project raw-TCP port offsets so their databases/caches don't collide.

**Architecture (chosen: Option A — `lan` = shared).** In `lan` mode a single host-level `nginx-proxy` (managed stack under `~/.config/sail/`) binds `SAIL_BIND_IP:80/443`, watches the Docker socket, and routes each project by its `VIRTUAL_HOST` (`SAIL_DOMAIN`). Each lan project joins a shared external Docker network (`sail-shared`) via a **generated compose override** activated through `SAIL_FILES` — so the committed `docker-compose.yml`/`compose.stub` is never modified and `local` mode is byte-for-byte unchanged. Raw-TCP services get per-project host ports from `HostRegistry` slot offsets. mkcert certs go to a shared certs dir the proxy mounts. `local` mode keeps the per-project proxy exactly as today.

**Tech Stack:** PHP 8.x (Laravel package, Testbench, PHPUnit), Bash (`bin/sail`, `bin/sail-setup`), Docker Compose + external networks, `nginxproxy/nginx-proxy`, mkcert, `mirazmac/dotenvwriter`, Symfony YAML.

## Global Constraints

- Namespace `Laravel\Sail\` (from `src/`); tests `Laravel\Sail\Tests\` (from `tests/`).
- PHPStan level 0 clean on `src` (`vendor/bin/phpstan analyse src`); do NOT add baseline entries to silence errors.
- StyleCI Laravel preset: 4-space PHP, LF; YAML 2-space.
- PHP tests via `vendor/bin/phpunit` (default suite Feature+Integration+Unit; currently 109 green, 21 skipped).
- Bash changes: `bash -n` valid and `shellcheck` introduces NO new findings vs baseline; each bash task ends with a documented manual-verification note.
- **`local` mode MUST stay byte-for-byte unchanged.** The committed `compose.stub`/`docker-compose.yml` is NOT edited by this plan; all shared-mode behavior rides on a generated override + `SAIL_FILES` + a separate host-level stack. A fresh/existing `local` install and `sail up` in `local` mode behave exactly as before this plan.
- No new Composer dependencies.
- Host-state root is `SAIL_HOME` if set, else `$HOME/.config/sail`. Shared external network name is `sail-shared`. Shared certs dir is `<SAIL_HOME>/certs`.
- `SAIL_FILES` in `bin/sail` receives ONLY the files listed (no implicit base), so lan mode sets `SAIL_FILES=docker-compose.yml:<override-path>` (base first, override second).
- This is Plan 2b of 3. OUT OF SCOPE (do not implement): mDNS/`.local` resolver (stays Plan 2c — nip.io is the resolver here); `lan-direct` per-project IP-alias mode; plain-HTTP LAN. Port offsets apply in `lan` mode only (local mode uses distinct bind IPs and standard ports, unchanged).

## Validation Caveat (read before executing)

The Docker-runtime behavior of this plan (two projects actually routing through the shared proxy, cross-network reachability, cert discovery) CANNOT be exercised in CI/unit tests. Automated gates here cover **file/override/config generation, port allocation, YAML content, and linting**. Final acceptance requires the maintainer to manually validate on a real LAN server with two projects (see Task 9). Tasks are structured so the generation logic is unit/feature-tested and the docker/bash glue is thin, lint-clean, and reviewed.

## File Structure

- `src/Networking/SailHome.php` (new) — resolve host-state paths under `~/.config/sail`.
- `src/Networking/SharedProxyStack.php` (new) — render the shared-proxy compose + the per-project lan override YAML (pure, from a template + values).
- `stubs/shared-proxy.stub` (new) — the shared nginx-proxy compose template.
- `src/Console/ProxyCommand.php` (new) — `sail:proxy up|down|status`.
- `src/Console/Concerns/InteractsWithDockerComposeServices.php` (modify) — extend `applyLanConfig()` to write the override + `SAIL_FILES` + port offsets; local revert clears them.
- `src/SailServiceProvider.php` (modify) — register `ProxyCommand`.
- `bin/sail-setup` (modify) — lan mode writes certs to the shared certs dir.
- `bin/sail` (modify) — in lan mode ensure the shared network + shared proxy are up before `up`.
- `README.md` (modify) — multi-project LAN docs.
- Tests: `tests/Unit/Networking/SailHomeTest.php`, `tests/Unit/Networking/SharedProxyStackTest.php`, `tests/Feature/NetworkLanSharedTest.php`, `tests/Feature/ProxyCommandTest.php`.

---

### Task 1: `SailHome` — resolve host-state paths

**Files:**
- Create: `src/Networking/SailHome.php`
- Test: `tests/Unit/Networking/SailHomeTest.php`

**Interfaces:**
- Produces:
  - `SailHome::__construct(?string $home = null)` — base dir; defaults to `getenv('SAIL_HOME') ?: (getenv('HOME').'/.config/sail')`. `$home` injectable for tests.
  - `root(): string`, `proxyDir(): string` (`root()/proxy`), `certsDir(): string` (`root()/certs`), `overridesDir(): string` (`root()/overrides`), `registryPath(): string` (`root()/registry.json`).
  - `ensureDirectories(): void` — `mkdir -p` for proxy/certs/overrides (0755), idempotent.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Networking/SailHomeTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\SailHome;
use Laravel\Sail\Tests\TestCase;

class SailHomeTest extends TestCase
{
    public function test_paths_derive_from_injected_home(): void
    {
        $home = new SailHome('/tmp/sail-home-x');
        $this->assertSame('/tmp/sail-home-x', $home->root());
        $this->assertSame('/tmp/sail-home-x/proxy', $home->proxyDir());
        $this->assertSame('/tmp/sail-home-x/certs', $home->certsDir());
        $this->assertSame('/tmp/sail-home-x/overrides', $home->overridesDir());
        $this->assertSame('/tmp/sail-home-x/registry.json', $home->registryPath());
    }

    public function test_ensure_directories_creates_them(): void
    {
        $base = sys_get_temp_dir().'/sail-home-'.uniqid();
        $home = new SailHome($base);
        $home->ensureDirectories();

        $this->assertDirectoryExists($base.'/proxy');
        $this->assertDirectoryExists($base.'/certs');
        $this->assertDirectoryExists($base.'/overrides');

        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($base);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Networking/SailHomeTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `SailHome`**

Create `src/Networking/SailHome.php`:

```php
<?php

namespace Laravel\Sail\Networking;

class SailHome
{
    private string $home;

    public function __construct(?string $home = null)
    {
        $this->home = $home ?: (getenv('SAIL_HOME') ?: (getenv('HOME').'/.config/sail'));
    }

    public function root(): string
    {
        return $this->home;
    }

    public function proxyDir(): string
    {
        return $this->home.'/proxy';
    }

    public function certsDir(): string
    {
        return $this->home.'/certs';
    }

    public function overridesDir(): string
    {
        return $this->home.'/overrides';
    }

    public function registryPath(): string
    {
        return $this->home.'/registry.json';
    }

    public function ensureDirectories(): void
    {
        foreach ([$this->proxyDir(), $this->certsDir(), $this->overridesDir()] as $dir) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Networking/SailHomeTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Static analysis + commit**

Run: `vendor/bin/phpstan analyse src` → `[OK] No errors`.

```bash
git add src/Networking/SailHome.php tests/Unit/Networking/SailHomeTest.php
git commit -m "feat(network): add SailHome host-state path resolver"
```

---

### Task 2: `SharedProxyStack` + `shared-proxy.stub` — render the proxy compose and the lan override

**Files:**
- Create: `stubs/shared-proxy.stub`
- Create: `src/Networking/SharedProxyStack.php`
- Test: `tests/Unit/Networking/SharedProxyStackTest.php`

**Interfaces:**
- Consumes: nothing (pure string rendering).
- Produces:
  - `SharedProxyStack::__construct(string $bindIp, string $certsDir, string $network = 'sail-shared')`
  - `proxyCompose(): string` — the shared nginx-proxy compose YAML: service `nginx-proxy` (image `nginxproxy/nginx-proxy:alpine`) binding `<bindIp>:80:80` and `<bindIp>:443:443`, mounting `/var/run/docker.sock:/tmp/docker.sock:ro` and `<certsDir>:/etc/nginx/certs:ro`, joined to the external network `<network>`; the compose declares `networks: { <network>: { external: true } }` and `name: sail-shared-proxy`.
  - `projectOverride(string $network = 'sail-shared'): string` — a per-project override YAML: `services: { nginx-proxy: { profiles: ["standalone"] }, laravel: { networks: ["sail", "<network>"] } }` and `networks: { <network>: { external: true } }`. (Gating the per-project `nginx-proxy` behind the `standalone` profile — which lan mode does NOT enable — disables it; adding `laravel` to the shared network lets the shared proxy reach it.)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Networking/SharedProxyStackTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\SharedProxyStack;
use Laravel\Sail\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

class SharedProxyStackTest extends TestCase
{
    public function test_proxy_compose_binds_ip_mounts_certs_and_joins_network(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/home/u/.config/sail/certs'))->proxyCompose();
        $parsed = Yaml::parse($yaml);

        $svc = $parsed['services']['nginx-proxy'];
        $this->assertContains('192.168.1.50:80:80', $svc['ports']);
        $this->assertContains('192.168.1.50:443:443', $svc['ports']);
        $this->assertContains('/var/run/docker.sock:/tmp/docker.sock:ro', $svc['volumes']);
        $this->assertContains('/home/u/.config/sail/certs:/etc/nginx/certs:ro', $svc['volumes']);
        $this->assertContains('sail-shared', $svc['networks']);
        $this->assertTrue($parsed['networks']['sail-shared']['external']);
    }

    public function test_project_override_disables_local_proxy_and_joins_shared_network(): void
    {
        $yaml = (new SharedProxyStack('192.168.1.50', '/x/certs'))->projectOverride();
        $parsed = Yaml::parse($yaml);

        $this->assertSame(['standalone'], $parsed['services']['nginx-proxy']['profiles']);
        $this->assertContains('sail', $parsed['services']['laravel']['networks']);
        $this->assertContains('sail-shared', $parsed['services']['laravel']['networks']);
        $this->assertTrue($parsed['networks']['sail-shared']['external']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Networking/SharedProxyStackTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the stub template**

Create `stubs/shared-proxy.stub`:

```yaml
name: sail-shared-proxy
services:
    nginx-proxy:
        image: nginxproxy/nginx-proxy:alpine
        restart: unless-stopped
        ports:
            - "__BIND_IP__:80:80"
            - "__BIND_IP__:443:443"
        volumes:
            - /var/run/docker.sock:/tmp/docker.sock:ro
            - __CERTS_DIR__:/etc/nginx/certs:ro
        networks:
            - __NETWORK__
networks:
    __NETWORK__:
        external: true
```

- [ ] **Step 4: Implement `SharedProxyStack`**

Create `src/Networking/SharedProxyStack.php`:

```php
<?php

namespace Laravel\Sail\Networking;

class SharedProxyStack
{
    public function __construct(
        private string $bindIp,
        private string $certsDir,
        private string $network = 'sail-shared'
    ) {
    }

    public function proxyCompose(): string
    {
        $stub = file_get_contents(__DIR__.'/../../stubs/shared-proxy.stub');

        return strtr($stub, [
            '__BIND_IP__' => $this->bindIp,
            '__CERTS_DIR__' => $this->certsDir,
            '__NETWORK__' => $this->network,
        ]);
    }

    public function projectOverride(string $network = null): string
    {
        $network = $network ?: $this->network;

        return <<<YAML
        services:
            nginx-proxy:
                profiles:
                    - standalone
            laravel:
                networks:
                    - sail
                    - {$network}
        networks:
            {$network}:
                external: true
        YAML;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Networking/SharedProxyStackTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Static analysis + commit**

Run: `vendor/bin/phpstan analyse src` → `[OK] No errors`.

```bash
git add stubs/shared-proxy.stub src/Networking/SharedProxyStack.php tests/Unit/Networking/SharedProxyStackTest.php
git commit -m "feat(network): render shared-proxy compose + per-project lan override"
```

---

### Task 3: `sail:proxy` command (up / down / status)

**Files:**
- Create: `src/Console/ProxyCommand.php`
- Modify: `src/SailServiceProvider.php` (import, `registerCommands()`, `provides()`)
- Test: `tests/Feature/ProxyCommandTest.php`

**Interfaces:**
- Consumes: `SailHome` (Task 1), `SharedProxyStack` (Task 2).
- Produces: command `sail:proxy {action=up : up|down|status}`. Behavior:
  - `up`: `SailHome::ensureDirectories()`; write `proxyDir()/docker-compose.yml` from `SharedProxyStack::proxyCompose()` using `SAIL_BIND_IP` from `.env`; run `docker network create sail-shared` (idempotent) then `docker compose -f <proxyDir>/docker-compose.yml up -d`.
  - `down`: `docker compose -f <proxyDir>/docker-compose.yml down` (if the file exists).
  - `status`: print whether the stack file exists and the `sail-shared` network exists.
  - Shelling out is via a `protected function runProcess(array $cmd): int` seam (default uses Symfony `Process`), overridable in tests so no real Docker runs. The file-writing + network-name + command-assembly are what tests assert.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ProxyCommandTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class ProxyCommandTest extends TestCase
{
    private string $base;
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-proxy-'.uniqid();
        $this->home = $this->base.'/home';
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_BIND_IP=192.168.1.50\n");
        putenv('SAIL_HOME='.$this->home);
    }

    protected function tearDown(): void
    {
        putenv('SAIL_HOME');
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_up_writes_stack_and_issues_network_and_compose_commands(): void
    {
        // Test double logs each shelled command to a file (deterministic, no real Docker).
        $log = $this->base.'/cmds.log';
        $this->app->bind(\Laravel\Sail\Console\ProxyCommand::class, function () use ($log) {
            return new class($log) extends \Laravel\Sail\Console\ProxyCommand {
                private string $log;
                public function __construct(string $log) { $this->log = $log; parent::__construct(); }
                protected function runProcess(array $cmd): int { file_put_contents($this->log, implode(' ', $cmd)."\n", FILE_APPEND); return 0; }
            };
        });

        $this->artisan('sail:proxy', ['action' => 'up'])->assertSuccessful();

        $stack = $this->home.'/proxy/docker-compose.yml';
        $this->assertFileExists($stack);
        $this->assertStringContainsString('192.168.1.50:80:80', File::get($stack));

        $cmds = File::get($log);
        $this->assertStringContainsString('network create sail-shared', $cmds);
        $this->assertStringContainsString('compose -f '.$stack.' up -d', $cmds);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/ProxyCommandTest.php`
Expected: FAIL — command not defined.

- [ ] **Step 3: Implement `ProxyCommand`**

Create `src/Console/ProxyCommand.php`:

```php
<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Laravel\Sail\Networking\SailHome;
use Laravel\Sail\Networking\SharedProxyStack;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'sail:proxy')]
class ProxyCommand extends Command
{
    protected $signature = 'sail:proxy {action=up : up, down, or status}';

    protected $description = 'Manage the shared host-level nginx-proxy for LAN mode';

    public function handle(): int
    {
        $home = new SailHome;
        $stack = $home->proxyDir().'/docker-compose.yml';

        return match ($this->argument('action')) {
            'up' => $this->up($home, $stack),
            'down' => $this->down($stack),
            'status' => $this->status($home, $stack),
            default => $this->invalidAction(),
        };
    }

    private function up(SailHome $home, string $stack): int
    {
        $home->ensureDirectories();

        $bindIp = $this->bindIp();
        file_put_contents($stack, (new SharedProxyStack($bindIp, $home->certsDir()))->proxyCompose());

        $this->runProcess(['docker', 'network', 'create', 'sail-shared']);
        $code = $this->runProcess(['docker', 'compose', '-f', $stack, 'up', '-d']);

        $this->components->info("Shared proxy up on {$bindIp}:80/443.");

        return $code === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function down(string $stack): int
    {
        if (is_file($stack)) {
            $this->runProcess(['docker', 'compose', '-f', $stack, 'down']);
        }

        $this->components->info('Shared proxy stopped.');

        return self::SUCCESS;
    }

    private function status(SailHome $home, string $stack): int
    {
        $this->components->twoColumnDetail('Stack file', is_file($stack) ? $stack : '(not written)');
        $this->components->twoColumnDetail('Shared network', 'sail-shared');

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->components->error('Unknown action. Use: up, down, status.');

        return self::FAILURE;
    }

    private function bindIp(): string
    {
        $envPath = $this->laravel->basePath('.env');
        if (is_file($envPath) && preg_match('/^SAIL_BIND_IP=(.*)$/m', file_get_contents($envPath), $m)) {
            return trim($m[1], " \"'");
        }

        return (string) config('sail.network.bind_ip', '172.20.0.10');
    }

    protected function runProcess(array $cmd): int
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $process->getExitCode() ?? 1;
    }
}
```

- [ ] **Step 4: Register the command**

In `src/SailServiceProvider.php`: add `use Laravel\Sail\Console\ProxyCommand;`, add `ProxyCommand::class` to the `registerCommands()` array and to `provides()`.

- [ ] **Step 5: Run test + suite + phpstan**

Run: `vendor/bin/phpunit tests/Feature/ProxyCommandTest.php` → PASS.
Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse src` → all pass, `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Console/ProxyCommand.php src/SailServiceProvider.php tests/Feature/ProxyCommandTest.php
git commit -m "feat(network): add sail:proxy command to manage the shared LAN proxy"
```

---

### Task 4: Wire lan mode to the shared proxy — override + SAIL_FILES + port offsets

Extend `applyLanConfig()` so `lan` mode: (a) writes the per-project override to `SailHome::overridesDir()/<project>.yml`, (b) sets `SAIL_FILES=docker-compose.yml:<override-path>` in `.env`, (c) allocates a `HostRegistry` slot and writes per-project `FORWARD_*_PORT` offsets for services present in the project's compose. `--mode=local` clears `SAIL_FILES` and releases the slot.

**Files:**
- Modify: `src/Console/Concerns/InteractsWithDockerComposeServices.php` (`applyLanConfig()` from Plan 2a; add a `forwardPortMap()` helper)
- Modify: `src/Console/NetworkCommand.php` (`--mode=local` branch: clear `SAIL_FILES`, release slot)
- Test: `tests/Feature/NetworkLanSharedTest.php` (create)

**Interfaces:**
- Consumes: `SailHome`, `SharedProxyStack`, `HostRegistry` (`slotFor`, `port`), `resolveProjectName()`.
- Produces: `applyLanConfig()` additionally writes `SAIL_FILES` and `FORWARD_*_PORT`; trait helper `forwardPortMap(): array` returning `['mysql' => ['FORWARD_DB_PORT', 3306], 'pgsql' => ['FORWARD_DB_PORT', 5432], 'mariadb' => ['FORWARD_DB_PORT', 3306], 'redis' => ['FORWARD_REDIS_PORT', 6379], 'valkey' => ['FORWARD_VALKEY_PORT', 6379], 'mailpit' => ['FORWARD_MAILPIT_DASHBOARD_PORT', 8025]]` (the services whose host port must be unique per project on a shared IP).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NetworkLanSharedTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class NetworkLanSharedTest extends TestCase
{
    private string $base;
    private string $home;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-lanshared-'.uniqid();
        $this->home = $this->base.'/home';
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
        putenv('SAIL_HOME='.$this->home);
        // Minimal compose so present-service detection finds mysql + redis.
        File::put($this->base.'/docker-compose.yml', "services:\n  laravel: {}\n  mysql: {}\n  redis: {}\n");
    }

    protected function tearDown(): void
    {
        putenv('SAIL_HOME');
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lan_writes_override_sail_files_and_slot0_ports(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $override = $this->home.'/overrides/alpha.yml';
        $this->assertFileExists($override);
        $this->assertStringContainsString('SAIL_FILES="docker-compose.yml:'.$override.'"', $env);
        // Slot 0 → base ports.
        $this->assertStringContainsString('FORWARD_DB_PORT=3306', $env);
        $this->assertStringContainsString('FORWARD_REDIS_PORT=6379', $env);
    }

    public function test_second_project_gets_offset_ports(): void
    {
        // alpha already registered (slot 0) via a prior run in the shared SAIL_HOME registry.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\n");
        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])->assertSuccessful();

        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=beta\n");
        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('FORWARD_DB_PORT=3316', $env);   // 3306 + slot1*10
        $this->assertStringContainsString('FORWARD_REDIS_PORT=6389', $env); // 6379 + slot1*10
    }

    public function test_local_mode_clears_sail_files(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_IP=172.20.0.11\nSAIL_FILES=docker-compose.yml:/x/alpha.yml\n");

        $this->artisan('sail:network', ['--mode' => 'local'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertMatchesRegularExpression('/^SAIL_FILES=\s*("")?\s*$/m', $env);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/NetworkLanSharedTest.php`
Expected: FAIL — override/SAIL_FILES/ports not written.

- [ ] **Step 3: Extend `applyLanConfig()` and add `forwardPortMap()`**

In `src/Console/Concerns/InteractsWithDockerComposeServices.php`, add `use Laravel\Sail\Networking\SailHome;`, `use Laravel\Sail\Networking\SharedProxyStack;`, `use Laravel\Sail\Networking\HostRegistry;` at the top. After computing `$values` and BEFORE writing them in `applyLanConfig()`, insert:

```php
        $home = new SailHome;
        $home->ensureDirectories();
        $project = $this->resolveProjectName();

        // Per-project override that disables the standalone proxy and joins the shared network.
        $overridePath = $home->overridesDir().'/'.$project.'.yml';
        file_put_contents($overridePath, (new SharedProxyStack($ip, $home->certsDir()))->projectOverride());
        $values['SAIL_FILES'] = 'docker-compose.yml:'.$overridePath;

        // Per-project raw-TCP port offsets so services don't collide on the shared IP.
        $slot = (new HostRegistry($home->registryPath()))->slotFor($project);
        $present = $this->composeServiceNames();
        foreach ($this->forwardPortMap() as $service => [$var, $base]) {
            if (in_array($service, $present, true)) {
                $values[$var] = (string) HostRegistry::port($base, $slot);
            }
        }
```

Add these two helpers to the trait:

```php
/**
 * @return array<string, array{0: string, 1: int}>
 */
protected function forwardPortMap(): array
{
    return [
        'mysql' => ['FORWARD_DB_PORT', 3306],
        'pgsql' => ['FORWARD_DB_PORT', 5432],
        'mariadb' => ['FORWARD_DB_PORT', 3306],
        'redis' => ['FORWARD_REDIS_PORT', 6379],
        'valkey' => ['FORWARD_VALKEY_PORT', 6379],
        'mailpit' => ['FORWARD_MAILPIT_DASHBOARD_PORT', 8025],
    ];
}

/**
 * @return array<int, string>
 */
protected function composeServiceNames(): array
{
    $path = $this->composePath();
    if (! $path || ! is_file($path)) {
        return [];
    }

    $parsed = \Symfony\Component\Yaml\Yaml::parseFile($path);

    return array_keys($parsed['services'] ?? []);
}
```

(`composePath()` already exists on the trait — it returns the detected compose file path.)

- [ ] **Step 4: Clear `SAIL_FILES` + release slot in the local branch**

In `src/Console/NetworkCommand.php`'s `local` branch, after setting `COMPOSE_PROFILES`, also clear `SAIL_FILES` and release the registry slot. `NetworkCommand` already uses the trait (Plan 1), so reuse its `resolveProjectName()`:

```php
            $writer->set('SAIL_FILES', '');
            (new \Laravel\Sail\Networking\HostRegistry((new \Laravel\Sail\Networking\SailHome)->registryPath()))
                ->release($this->resolveProjectName());
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/NetworkLanSharedTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Full suite + phpstan + commit**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse src` → all pass, `[OK] No errors`.

```bash
git add src/Console/Concerns/InteractsWithDockerComposeServices.php src/Console/NetworkCommand.php tests/Feature/NetworkLanSharedTest.php
git commit -m "feat(network): lan mode writes shared-proxy override, SAIL_FILES, port offsets"
```

---

### Task 5: Shared certs dir in `bin/sail-setup` (lan mode)

In lan mode the shared proxy mounts `<SAIL_HOME>/certs`, so `sail-setup` must write the mkcert cert there (still per-domain) instead of the project-local vendor dir.

**Files:**
- Modify: `bin/sail-setup` (`generate_certificates()` `CERT_FOLDER`, and the top var block)

**Interfaces:** consumes `.env` `SAIL_NETWORK_MODE`, `SAIL_HOME` (or default `$HOME/.config/sail`); no PHP interface.

- [ ] **Step 1: Read `generate_certificates()` and the top var block**

Run: `sed -n '1,12p;169,198p' bin/sail-setup`.

- [ ] **Step 2: Compute the cert folder by mode**

At the top var block of `bin/sail-setup` (after the existing `SAIL_NETWORK_MODE` read added in Plan 2a), add:

```bash
SAIL_HOME_DIR=${SAIL_HOME:-"$HOME/.config/sail"}
if [ "$SAIL_NETWORK_MODE" = "local" ]; then
    CERT_TARGET="vendor/reyemtech/sail/certs"
else
    CERT_TARGET="$SAIL_HOME_DIR/certs"
    mkdir -p "$CERT_TARGET"
fi
```

In `generate_certificates()`, replace the hard-coded `CERT_FOLDER="vendor/reyemtech/sail/certs"` with `CERT_FOLDER="$CERT_TARGET"`. Leave the rest of the function (mkcert invocation, per-domain filenames, rootCA copy) unchanged. In lan mode the `LINK_FOLDERS` rsync/`cp` into the runtime image is not needed (the shared proxy reads the shared dir directly) — guard that copy loop with `if [ "$SAIL_NETWORK_MODE" = "local" ]; then ... fi`.

- [ ] **Step 3: Lint + verify**

Run: `bash -n bin/sail-setup` → valid.
Run: `shellcheck bin/sail-setup` → no new findings vs baseline (compare with `git stash`/`git stash pop`).

- [ ] **Step 4: Commit (with manual-verification note in body)**

```bash
git add bin/sail-setup
git commit -m "feat(network): write lan-mode certs to the shared SAIL_HOME certs dir

In lan mode certs go to \$SAIL_HOME/certs (mounted by the shared proxy)
instead of the project vendor dir; local mode unchanged.

Manual verification: local mode writes vendor/reyemtech/sail/certs and syncs
to runtime as before; lan mode writes \$HOME/.config/sail/certs/<domain>.crt
and skips the runtime copy."
```

---

### Task 6: `bin/sail` — start the shared proxy + network before `up` in lan mode

**Files:**
- Modify: `bin/sail` (the `up` branch, after the auto-heal block from Plan 2a)

**Interfaces:** consumes `.env` `SAIL_NETWORK_MODE`; no PHP interface.

- [ ] **Step 1: Read the `up` branch**

Run: `sed -n '299,365p' bin/sail`.

- [ ] **Step 2: Add the lan bootstrap**

After the auto-heal block (which runs `php artisan sail:network` when `SAIL_BIND_IP` is absent) and before the setup block, add:

```bash
    # In lan mode, ensure the shared network + shared proxy are up before starting the project...
    SAIL_MODE_UP=$(grep -E '^SAIL_NETWORK_MODE=' .env 2>/dev/null | cut -d '=' -f2 | tr -d '"')
    if [ "${SAIL_MODE_UP:-local}" = "lan" ]; then
        docker network create sail-shared >/dev/null 2>&1 || true
        php artisan sail:proxy up >/dev/null 2>&1 || true
    fi
```

- [ ] **Step 3: Lint + verify**

Run: `bash -n bin/sail` → valid.
Run: `shellcheck bin/sail` → no new findings vs baseline.

- [ ] **Step 4: Commit (with manual-verification note in body)**

```bash
git add bin/sail
git commit -m "feat(network): sail up bootstraps shared network + proxy in lan mode

Manual verification: local mode does nothing new; lan mode creates the
sail-shared network (idempotent) and runs 'sail:proxy up' before the
project's compose up."
```

---

### Task 7: Document multi-project LAN in the README

**Files:**
- Modify: `README.md` (extend the "Running on a LAN server" section)

- [ ] **Step 1: Update the section**

Replace the single-project caveat note at the end of the existing "Running on a LAN server" section with multi-project guidance:

```markdown
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
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs(network): document multi-project LAN mode (shared proxy + port offsets)"
```

---

### Task 8: `sail:network --status` shows lan mode, domain, and assigned ports

Make `--status` useful for the multi-project workflow (the README references it for assigned ports).

**Files:**
- Modify: `src/Console/NetworkCommand.php` (`--status` branch)
- Test: `tests/Feature/NetworkCommandTest.php` (add a case)

**Interfaces:** consumes `.env` values written by Task 4.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/NetworkCommandTest.php`:

```php
    public function test_status_reports_lan_domain_and_forward_ports(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_NETWORK_MODE=lan\nSAIL_DOMAIN=alpha.192-168-1-50.nip.io\nFORWARD_DB_PORT=3316\n");

        $this->artisan('sail:network', ['--status' => true])
            ->expectsOutputToContain('lan')
            ->expectsOutputToContain('alpha.192-168-1-50.nip.io')
            ->assertSuccessful();
    }
```

- [ ] **Step 2: Run to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/NetworkCommandTest.php --filter test_status_reports_lan`
Expected: FAIL (domain row uses config, not the lan `.env` value / no ports shown).

- [ ] **Step 3: Make the `--status` branch read from `.env`**

The Plan 1 `--status` branch reads the mode from `config()` and the domain from `env()`, which don't reflect a project's actual `.env` at runtime as reliably as reading the file. Replace the mode/domain reads with direct `.env` parsing and append the forward-port rows. The `--status` branch becomes:

```php
        if ($this->option('status')) {
            $envPath = $this->laravel->basePath('.env');
            $contents = is_file($envPath) ? file_get_contents($envPath) : '';

            $read = function (string $key, string $default) use ($contents) {
                return preg_match('/^'.$key.'=(.*)$/m', $contents, $m) ? trim($m[1], " \"'") : $default;
            };

            $this->components->twoColumnDetail('Mode', $read('SAIL_NETWORK_MODE', (string) config('sail.network.mode', 'local')));
            $this->components->twoColumnDetail('Bind IP', $read('SAIL_BIND_IP', (string) config('sail.network.bind_ip', '172.20.0.10')));
            $this->components->twoColumnDetail('Domain', $read('SAIL_DOMAIN', (string) config('sail.domain')));

            if (preg_match_all('/^(FORWARD_\w+)=(.*)$/m', $contents, $ms, PREG_SET_ORDER)) {
                foreach ($ms as $match) {
                    $this->components->twoColumnDetail($match[1], trim($match[2], " \"'"));
                }
            }

            return self::SUCCESS;
        }
```

(This replaces the entire existing `if ($this->option('status')) { ... }` block from Plan 1.)

- [ ] **Step 4: Run test + suite + phpstan + commit**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse src` → all pass.

```bash
git add src/Console/NetworkCommand.php tests/Feature/NetworkCommandTest.php
git commit -m "feat(network): sail:network --status shows lan domain + assigned ports"
```

---

### Task 9: Manual validation on real hardware (maintainer)

This task is NOT automatable and does not produce a commit; it is the acceptance gate for the runtime behavior the tests cannot cover. Record results in the PR.

- [ ] Two projects (A, B) on one Linux LAN server; `sail artisan sail:network --mode=lan` in each; `sail up` in each.
- [ ] From a second device: both `https://a.<ip>.nip.io` and `https://b.<ip>.nip.io` load their apps (correct routing by domain through the one shared proxy).
- [ ] Both databases reachable from the second device on their distinct assigned ports (`sail:network --status`).
- [ ] After importing `~/.config/sail/certs/mkcert-rootCA.pem`, no TLS warnings on the client.
- [ ] `sail artisan sail:network --mode=local` in project A returns it to per-project-proxy local mode and it still works locally.
- [ ] `local`-mode projects on the box are unaffected throughout.

---

## What comes after this plan

- **Plan 2c:** mDNS (`.local`) resolver — an `avahi-publish` sidecar as an alternative to nip.io.
- **Plan 3:** `lan-direct` per-project IP-alias mode; plain-HTTP LAN option.
- Follow-ups carried from earlier plans: `HostRegistry::save()` atomicity/locking (now actually exercised concurrently — worth doing here or early in 2c); auto-heal failure diagnosability.

## Self-Review

- **Spec coverage:** host-state paths (Task 1); shared proxy + override rendering (Task 2); `sail:proxy` lifecycle (Task 3); lan wiring — override, `SAIL_FILES`, port offsets, local revert (Task 4); shared certs (Task 5); `sail up` bootstrap (Task 6); docs (Task 7); status visibility (Task 8); manual acceptance (Task 9). `compose.stub` deliberately untouched (override-based) → local mode unchanged.
- **Placeholder scan:** every code/bash step has complete content; bash/docker steps carry shellcheck + manual-verification notes; the un-automatable runtime acceptance is isolated in Task 9 rather than pretended-tested.
- **Type/name consistency:** `SailHome` path methods (Task 1) used in Tasks 3–6; `SharedProxyStack::proxyCompose/projectOverride` (Task 2) consumed in Tasks 3–4; `HostRegistry::slotFor/port` (Plan 1) used in Task 4; `forwardPortMap()`/`composeServiceNames()` defined and used in Task 4; `SAIL_FILES`/`sail-shared`/`SAIL_HOME`/`<SAIL_HOME>/certs` consistent across PHP (Task 4) and bash (Tasks 5–6); `sail:proxy` command name consistent across Task 3, Task 6, and the README (Task 7).
