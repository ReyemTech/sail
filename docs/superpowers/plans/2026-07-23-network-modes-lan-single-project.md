# Network Modes — LAN Single-Project (Plan 2a of 3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a single Sail project run on a LAN server (`sail up`) be reached from other devices (laptop/phone) — web app, Vite HMR, and DB — by binding to the host's LAN IP and using a `nip.io` domain that resolves from any device, with a trusted mkcert certificate.

**Architecture:** Build on Plan 1's foundation (`SAIL_BIND_IP`, `sail:network`, `config/sail.network.*`). Add LAN-IP detection, a `nip.io` domain builder, and a `lan` path in `sail:network`/`sail:install` that writes LAN-appropriate `.env` values. Make the existing host-setup scripts (`bin/sail-setup`, `bin/sail`) mode-aware: in `lan` mode they skip the loopback-alias and `/etc/hosts` steps (correct only for a host-local IP) but still generate the mkcert cert for the domain. Migrate the remaining `SAIL_IP` references in `bin/` to `SAIL_BIND_IP`. `local` mode stays the untouched default.

**Tech Stack:** PHP 8.x (Laravel package, Orchestra Testbench, PHPUnit), Bash (`bin/sail`, `bin/sail-setup`), `mirazmac/dotenvwriter`, Docker Compose, `nginxproxy/nginx-proxy`, mkcert.

## Global Constraints

- Namespace `Laravel\Sail\` (from `src/`); tests `Laravel\Sail\Tests\` (from `tests/`).
- PHPStan level 0 must pass on `src`: `vendor/bin/phpstan analyse src`. Do not add PHPStan baseline entries to silence new errors; fix them.
- StyleCI Laravel preset: 4-space PHP indentation, LF endings. YAML 2-space.
- PHP tests run via `vendor/bin/phpunit` (default suite = Feature + Integration + Unit; currently 95 green, 21 skipped).
- Bash changes must pass `shellcheck bin/sail bin/sail-setup` with no new warnings, and every bash task ends with a documented manual-verification note (bash logic is not unit-tested here).
- `local` mode is the default and MUST stay behaviorally identical: `SAIL_NETWORK_MODE` unset ⇒ `local`; a fresh `local` install produces the same `.env`/compose as today, and `sail up` in `local` mode still creates the loopback alias + `/etc/hosts` entry + cert exactly as before.
- No new Composer dependencies.
- `nip.io` domain format is dash-encoded: `<project>.<a-b-c-d>.nip.io` (e.g. `myproj.192-168-1-50.nip.io`).
- This is Plan 2a of 3. OUT OF SCOPE (do not implement): host-level shared nginx-proxy, per-project port offsets / `HostRegistry` wiring into compose, mDNS/`.local` resolver, `lan-direct` per-project IP-alias mode, plain-HTTP LAN mode, `COMPOSE_PROFILES`-gated extra services. Those are Plans 2b/3.

## File Structure

- `src/Networking/HostIpDetector.php` (new) — detect the host LAN IP; command execution injected for testability.
- `src/Networking/LanEnvironment.php` (new) — pure computation of `lan`-mode `.env` values (domain, APP_URL, VITE URL) from a project + IP + resolver.
- `src/Console/NetworkCommand.php` (modify) — add `--mode`, `--ip`, `--domain`; apply/revert lan config.
- `src/Console/InstallCommand.php` (modify) — add `--mode`; apply lan config after install.
- `bin/sail-setup` (modify) — read `SAIL_BIND_IP`/`SAIL_NETWORK_MODE`; skip alias + hosts in `lan`; keep cert generation.
- `bin/sail` (modify) — migrate `SAIL_IP`→`SAIL_BIND_IP`; mode-aware `NEEDS_SETUP`; auto-heal via `sail:network`.
- `README.md` (modify) — LAN mode usage + cert trust.
- Tests: `tests/Unit/Networking/HostIpDetectorTest.php`, `tests/Unit/Networking/LanEnvironmentTest.php`, `tests/Feature/NetworkLanModeTest.php`.

---

### Task 1: `HostIpDetector` — detect the host LAN IP

**Files:**
- Create: `src/Networking/HostIpDetector.php`
- Test: `tests/Unit/Networking/HostIpDetectorTest.php`

**Interfaces:**
- Produces:
  - `HostIpDetector::__construct(?callable $runner = null)` — `$runner` takes `(string $command): string` and returns stdout; defaults to a `shell_exec` wrapper. Injected in tests.
  - `detect(string $os = PHP_OS_FAMILY): ?string` — returns the first plausible LAN IPv4, or `null` if none found. On `'Darwin'` uses `ipconfig getifaddr en0`; otherwise uses `hostname -I` and returns the first address that is not loopback (`127.`), not link-local (`169.254.`), and not in the Docker bridge range (`172.16.`–`172.31.`).
  - `isPlausibleLanIp(string $ip): bool` — the filter predicate (public for testing).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Networking/HostIpDetectorTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\HostIpDetector;
use Laravel\Sail\Tests\TestCase;

class HostIpDetectorTest extends TestCase
{
    public function test_detects_first_lan_ip_from_hostname_output(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => "172.20.0.1 192.168.1.50 10.0.0.4\n");
        $this->assertSame('192.168.1.50', $detector->detect('Linux'));
    }

    public function test_skips_loopback_linklocal_and_docker_ranges(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => "127.0.0.1 169.254.1.1 172.18.0.1 10.1.2.3\n");
        $this->assertSame('10.1.2.3', $detector->detect('Linux'));
    }

    public function test_returns_null_when_no_plausible_ip(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => "127.0.0.1 172.20.0.1\n");
        $this->assertNull($detector->detect('Linux'));
    }

    public function test_uses_ipconfig_on_macos(): void
    {
        $detector = new HostIpDetector(function ($cmd) {
            return str_contains($cmd, 'ipconfig getifaddr') ? "192.168.7.7\n" : '';
        });
        $this->assertSame('192.168.7.7', $detector->detect('Darwin'));
    }

    public function test_plausibility_predicate(): void
    {
        $detector = new HostIpDetector(fn ($cmd) => '');
        $this->assertTrue($detector->isPlausibleLanIp('192.168.0.1'));
        $this->assertTrue($detector->isPlausibleLanIp('10.0.0.1'));
        $this->assertFalse($detector->isPlausibleLanIp('127.0.0.1'));
        $this->assertFalse($detector->isPlausibleLanIp('169.254.0.1'));
        $this->assertFalse($detector->isPlausibleLanIp('172.20.0.10'));
        $this->assertFalse($detector->isPlausibleLanIp('not-an-ip'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Networking/HostIpDetectorTest.php`
Expected: FAIL — `Class "Laravel\Sail\Networking\HostIpDetector" not found`.

- [ ] **Step 3: Implement `HostIpDetector`**

Create `src/Networking/HostIpDetector.php`:

```php
<?php

namespace Laravel\Sail\Networking;

class HostIpDetector
{
    /** @var callable(string):string */
    private $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? fn (string $command): string => (string) shell_exec($command);
    }

    public function detect(string $os = PHP_OS_FAMILY): ?string
    {
        if ($os === 'Darwin') {
            $ip = trim(($this->runner)('ipconfig getifaddr en0'));

            return $this->isPlausibleLanIp($ip) ? $ip : null;
        }

        $candidates = preg_split('/\s+/', trim(($this->runner)('hostname -I'))) ?: [];

        foreach ($candidates as $candidate) {
            if ($this->isPlausibleLanIp($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isPlausibleLanIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return false;
        }

        // Docker bridge range 172.16.0.0 – 172.31.255.255.
        if (preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip)) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Networking/HostIpDetectorTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Static analysis**

Run: `vendor/bin/phpstan analyse src`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Networking/HostIpDetector.php tests/Unit/Networking/HostIpDetectorTest.php
git commit -m "feat(network): add HostIpDetector for LAN IP detection"
```

---

### Task 2: `LanEnvironment` — compute lan-mode `.env` values

**Files:**
- Create: `src/Networking/LanEnvironment.php`
- Test: `tests/Unit/Networking/LanEnvironmentTest.php`

**Interfaces:**
- Consumes: nothing from other tasks (pure).
- Produces:
  - `LanEnvironment::__construct(string $project, string $bindIp, string $resolver = 'nip')`
  - `domain(): string` — for `resolver='nip'`: `<project>.<dash-ip>.nip.io` (IP dots → dashes); the project segment is lowercased and non-alphanumerics collapse to `-`. Throws `InvalidArgumentException` for an unsupported resolver (only `nip` supported in this plan).
  - `values(): array` — `['SAIL_BIND_IP' => $bindIp, 'SAIL_DOMAIN' => domain(), 'APP_URL' => 'https://'.domain(), 'VITE_DEV_SERVER_URL' => 'https://'.domain().'/vite', 'SAIL_NETWORK_MODE' => 'lan', 'SAIL_RESOLVER' => $resolver, 'COMPOSE_PROFILES' => 'lan']`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Networking/LanEnvironmentTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use InvalidArgumentException;
use Laravel\Sail\Networking\LanEnvironment;
use Laravel\Sail\Tests\TestCase;

class LanEnvironmentTest extends TestCase
{
    public function test_builds_nip_io_domain_with_dashed_ip(): void
    {
        $env = new LanEnvironment('myproj', '192.168.1.50');
        $this->assertSame('myproj.192-168-1-50.nip.io', $env->domain());
    }

    public function test_slugifies_project_segment(): void
    {
        $env = new LanEnvironment('My App_v2', '10.0.0.4');
        $this->assertSame('my-app-v2.10-0-0-4.nip.io', $env->domain());
    }

    public function test_values_wire_url_domain_mode_and_profile(): void
    {
        $values = (new LanEnvironment('myproj', '192.168.1.50'))->values();

        $this->assertSame('192.168.1.50', $values['SAIL_BIND_IP']);
        $this->assertSame('myproj.192-168-1-50.nip.io', $values['SAIL_DOMAIN']);
        $this->assertSame('https://myproj.192-168-1-50.nip.io', $values['APP_URL']);
        $this->assertSame('https://myproj.192-168-1-50.nip.io/vite', $values['VITE_DEV_SERVER_URL']);
        $this->assertSame('lan', $values['SAIL_NETWORK_MODE']);
        $this->assertSame('nip', $values['SAIL_RESOLVER']);
        $this->assertSame('lan', $values['COMPOSE_PROFILES']);
    }

    public function test_rejects_unsupported_resolver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LanEnvironment('myproj', '192.168.1.50', 'mdns'))->domain();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Networking/LanEnvironmentTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `LanEnvironment`**

Create `src/Networking/LanEnvironment.php`:

```php
<?php

namespace Laravel\Sail\Networking;

use InvalidArgumentException;

class LanEnvironment
{
    public function __construct(
        private string $project,
        private string $bindIp,
        private string $resolver = 'nip'
    ) {
    }

    public function domain(): string
    {
        if ($this->resolver !== 'nip') {
            throw new InvalidArgumentException("Unsupported resolver [{$this->resolver}]; only 'nip' is supported in this release.");
        }

        return $this->slug($this->project).'.'.str_replace('.', '-', $this->bindIp).'.nip.io';
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        $domain = $this->domain();

        return [
            'SAIL_BIND_IP' => $this->bindIp,
            'SAIL_DOMAIN' => $domain,
            'APP_URL' => 'https://'.$domain,
            'VITE_DEV_SERVER_URL' => 'https://'.$domain.'/vite',
            'SAIL_NETWORK_MODE' => 'lan',
            'SAIL_RESOLVER' => $this->resolver,
            'COMPOSE_PROFILES' => 'lan',
        ];
    }

    private function slug(string $value): string
    {
        $slug = strtolower($value);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Networking/LanEnvironmentTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Static analysis**

Run: `vendor/bin/phpstan analyse src`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add src/Networking/LanEnvironment.php tests/Unit/Networking/LanEnvironmentTest.php
git commit -m "feat(network): add LanEnvironment nip.io value computation"
```

---

### Task 3: `sail:network --mode=lan` and `sail:install --mode=lan`

Wire Tasks 1–2 into the commands. `sail:network --mode=lan` detects (or accepts `--ip`) the LAN IP, computes lan values (or accepts `--domain` override for the domain only), and writes them to `.env` without touching `SAIL_SUBNET`. `--mode=local` reverts `SAIL_BIND_IP` to the existing `SAIL_IP` (or the config default) and sets `COMPOSE_PROFILES=`/`SAIL_NETWORK_MODE=local`. `sail:install --mode=lan` applies the same lan config after the normal install.

**Files:**
- Modify: `src/Console/NetworkCommand.php` (signature `:15`, `handle()` `:24-54`)
- Modify: `src/Console/InstallCommand.php` (signature `:21-24`, `handle()` `:38-85`)
- Add: shared method `applyLanConfig()` on `src/Console/Concerns/InteractsWithDockerComposeServices.php`
- Test: `tests/Feature/NetworkLanModeTest.php` (create)

**Interfaces:**
- Consumes: `HostIpDetector` (Task 1), `LanEnvironment` (Task 2), `ensureBindIp()`/`resolveProjectName()` (Plan 1 trait).
- Produces: trait method `applyLanConfig(?string $ip = null, ?string $domain = null): array` — resolves the bind IP (`$ip` ?: `HostIpDetector::detect()`), builds `LanEnvironment` values, applies a `$domain` override to `SAIL_DOMAIN`/`APP_URL`/`VITE_DEV_SERVER_URL` when given, writes all values to `.env` via `Writer` (never `SAIL_SUBNET`), and returns the written map. Throws `\RuntimeException` if no bind IP could be determined.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/NetworkLanModeTest.php`:

```php
<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;

class NetworkLanModeTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir().'/sail-lan-'.uniqid();
        File::makeDirectory($this->base, 0755, true);
        $this->app->setBasePath($this->base);
        chdir($this->base);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->base);
        parent::tearDown();
    }

    public function test_lan_mode_writes_bind_ip_domain_url_and_profile(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\nSAIL_SUBNET=172.20.0.0/24\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.1.50"', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="myproj.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('APP_URL="https://myproj.192-168-1-50.nip.io"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE="lan"', $env);
        // Subnet must be left untouched (not derived from the LAN IP).
        $this->assertStringContainsString('SAIL_SUBNET=172.20.0.0/24', $env);
        $this->assertStringNotContainsString('192.168.1.0/24', $env);
    }

    public function test_lan_mode_accepts_domain_override(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");

        $this->artisan('sail:network', ['--mode' => 'lan', '--ip' => '192.168.1.50', '--domain' => 'dev.example.lan'])
            ->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_DOMAIN="dev.example.lan"', $env);
        $this->assertStringContainsString('APP_URL="https://dev.example.lan"', $env);
    }

    public function test_local_mode_reverts_bind_ip_and_profile(): void
    {
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_IP=172.20.0.11\nSAIL_BIND_IP=192.168.1.50\nSAIL_NETWORK_MODE=lan\nCOMPOSE_PROFILES=lan\n");

        $this->artisan('sail:network', ['--mode' => 'local'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.11"', $env);
        $this->assertStringContainsString('SAIL_NETWORK_MODE="local"', $env);
        $this->assertStringContainsString('COMPOSE_PROFILES=""', $env);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/NetworkLanModeTest.php`
Expected: FAIL — `sail:network` does not accept `--mode` (unknown option) / lan not applied.

- [ ] **Step 3: Add `applyLanConfig()` to the trait**

In `src/Console/Concerns/InteractsWithDockerComposeServices.php`, add `use Laravel\Sail\Networking\HostIpDetector;` and `use Laravel\Sail\Networking\LanEnvironment;` at the top, and add this method near `ensureBindIp()`:

```php
/**
 * Apply LAN-mode networking values to .env (bind IP, nip.io domain, URLs,
 * profile). Never touches SAIL_SUBNET. Returns the written map.
 *
 * @return array<string, string>
 */
protected function applyLanConfig(?string $ip = null, ?string $domain = null): array
{
    $ip = $ip ?: (new HostIpDetector)->detect();

    if (! $ip) {
        throw new \RuntimeException('Could not detect a LAN IP address. Pass one explicitly with --ip=<address>.');
    }

    // Only nip.io is supported in this release; mDNS arrives in Plan 2b.
    $resolver = 'nip';

    $values = (new LanEnvironment($this->resolveProjectName(), $ip, $resolver))->values();

    if ($domain) {
        $values['SAIL_DOMAIN'] = $domain;
        $values['APP_URL'] = 'https://'.$domain;
        $values['VITE_DEV_SERVER_URL'] = 'https://'.$domain.'/vite';
    }

    $writer = new Writer(base_path('.env'));
    foreach ($values as $key => $value) {
        $writer->set($key, $value);
    }
    $writer->write();

    return $values;
}
```

- [ ] **Step 4: Extend `NetworkCommand`**

In `src/Console/NetworkCommand.php`, change the signature to add the options and make it use the trait. Replace the signature line:

```php
    protected $signature = 'sail:network
                {--status : Only report the current networking state}
                {--mode= : Set networking mode: local or lan}
                {--ip= : LAN IP to bind to (lan mode; auto-detected if omitted)}
                {--domain= : Override the domain (lan mode)}';
```

Ensure the class uses the trait (add `use Concerns\InteractsWithDockerComposeServices;` in the class body if not already present from Plan 1's fix). Then, in `handle()`, after the `--status` branch and the `.env`-missing guard, insert mode handling before the existing local seed logic:

```php
        $mode = $this->option('mode');

        if ($mode === 'lan') {
            $values = $this->applyLanConfig($this->option('ip'), $this->option('domain'));
            $this->components->info("LAN mode configured: {$values['SAIL_DOMAIN']} -> {$values['SAIL_BIND_IP']}");

            return self::SUCCESS;
        }

        if ($mode === 'local') {
            $writer = new \MirazMac\DotEnv\Writer(base_path('.env'));
            $contents = file_get_contents(base_path('.env'));
            $bindIp = preg_match('/^SAIL_IP=(.*)$/m', $contents, $m)
                ? trim($m[1], " \"'")
                : (string) config('sail.network.bind_ip', '172.20.0.10');
            $writer->set('SAIL_BIND_IP', $bindIp);
            $writer->set('SAIL_NETWORK_MODE', 'local');
            $writer->set('COMPOSE_PROFILES', '');
            $writer->write();
            $this->components->info("Local mode restored: SAIL_BIND_IP={$bindIp}");

            return self::SUCCESS;
        }
```

(Keep the existing no-`--mode` seed behavior from Plan 1 as the fallthrough.)

- [ ] **Step 5: Add `--mode` to `sail:install`**

In `src/Console/InstallCommand.php`, add to the `$signature` (after the `--php` line):

```php
                {--mode= : Networking mode after install: local (default) or lan}
```

In `handle()`, after `$this->prepareInstallation($services);` and before the final info output, add:

```php
        if ($this->option('mode') === 'lan') {
            $this->applyLanConfig();
            $this->components->info('LAN mode configured. Import the mkcert root CA on client devices to trust the certificate.');
        }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/NetworkLanModeTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Full suite + static analysis**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse src`
Expected: all PASS; PHPStan `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add src/Console/NetworkCommand.php src/Console/InstallCommand.php src/Console/Concerns/InteractsWithDockerComposeServices.php tests/Feature/NetworkLanModeTest.php
git commit -m "feat(network): sail:network/sail:install --mode=lan (nip.io + LAN bind)"
```

---

### Task 4: Make `bin/sail-setup` mode-aware and `SAIL_BIND_IP`-based

In `lan` mode the loopback IP alias and `/etc/hosts` entry are wrong (the bind IP is a real LAN address, and the nip.io domain resolves globally), so skip them; still generate the mkcert cert for the domain. Migrate the script from `SAIL_IP` to `SAIL_BIND_IP` (falling back to `SAIL_IP` for un-migrated `.env` files).

**Files:**
- Modify: `bin/sail-setup:4-5` (var reads), `:88-102` (`add_hosts_entry`), `:206-210` (call sequence)

**Interfaces:**
- Consumes: `.env` keys `SAIL_BIND_IP`, `SAIL_IP` (fallback), `SAIL_NETWORK_MODE`, `APP_URL`.
- Produces: mode-aware host setup (no PHP interface).

- [ ] **Step 1: Read the current script sections**

Run: `sed -n '1,10p;60,102p;164,216p' bin/sail-setup` and read `create_ip_alias`, `add_hosts_entry`, `generate_certificates`, and the bottom call sequence so the edits below land in the right place.

- [ ] **Step 2: Migrate variable reads and add mode**

Replace the two read lines at `bin/sail-setup:4-5`:

```bash
DOMAIN=$(grep -E '^APP_URL=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
SAIL_IP=$(grep -E '^SAIL_IP=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
```

with:

```bash
DOMAIN=$(grep -E '^APP_URL=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
SAIL_IP=$(grep -E '^SAIL_BIND_IP=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
if [ -z "$SAIL_IP" ]; then
    SAIL_IP=$(grep -E '^SAIL_IP=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
fi
SAIL_NETWORK_MODE=$(grep -E '^SAIL_NETWORK_MODE=' .env | cut -d '=' -f2 | tr -d '"')
SAIL_NETWORK_MODE=${SAIL_NETWORK_MODE:-local}
```

- [ ] **Step 3: Gate the host-local steps on `local` mode**

Find the bottom call sequence (`bin/sail-setup:206-210`) that calls `add_hosts_entry` and wrap the host-local part. Replace the call to `add_hosts_entry` with:

```bash
if [ "$SAIL_NETWORK_MODE" = "local" ]; then
    add_hosts_entry
else
    echo "${GREEN}LAN mode: skipping loopback alias and /etc/hosts (binding ${SAIL_IP}, domain ${DOMAIN}).${NC}" >&2
fi
```

Leave `generate_certificates` running in both modes (the cert must cover the domain regardless of mode).

- [ ] **Step 4: Lint**

Run: `shellcheck bin/sail-setup`
Expected: no new warnings versus the pre-change baseline (run `git stash && shellcheck bin/sail-setup; git stash pop` to compare if unsure).

- [ ] **Step 5: Manual verification note (record in the commit body)**

Because bash logic is not unit-tested, document this manual check in the commit message body:
- `local` mode: `SAIL_NETWORK_MODE` unset → `add_hosts_entry` still runs (loopback alias + `/etc/hosts` unchanged from before).
- `lan` mode: with `SAIL_NETWORK_MODE=lan`, `SAIL_BIND_IP=192.168.1.50`, `APP_URL=https://p.192-168-1-50.nip.io` → the alias/hosts step is skipped and only the cert is generated for the nip.io domain.

- [ ] **Step 6: Commit**

```bash
git add bin/sail-setup
git commit -m "feat(network): make sail-setup mode-aware (skip host-local steps in lan)

Reads SAIL_BIND_IP (falls back to SAIL_IP). In lan mode skips the loopback
alias and /etc/hosts entry; still generates the mkcert cert for the domain.

Manual verification: local mode unchanged (alias+hosts run); lan mode skips
alias+hosts and generates a cert for the nip.io domain."
```

---

### Task 5: Migrate `bin/sail` to `SAIL_BIND_IP` + add `up` auto-heal

Make the `up` path use `SAIL_BIND_IP` (fallback `SAIL_IP`), skip the alias/hosts `NEEDS_SETUP` checks in `lan` mode, and auto-run `php artisan sail:network` when `.env` has no `SAIL_BIND_IP` yet (fresh clone / upgrade).

**Files:**
- Modify: `bin/sail:299-349` (the `up` branch: var reads `:317-320`, `NEEDS_SETUP` checks `:321-337`, setup call `:345-348`)

**Interfaces:**
- Consumes: `.env` keys `SAIL_BIND_IP`, `SAIL_IP` (fallback), `SAIL_NETWORK_MODE`.
- Produces: mode-aware pre-`up` setup + auto-heal (no PHP interface).

- [ ] **Step 1: Read the current `up` branch**

Run: `sed -n '299,360p' bin/sail` and read the `NEEDS_SETUP` computation and the setup invocation so edits land correctly.

- [ ] **Step 2: Add auto-heal before the setup block**

At the start of the `up` branch's setup section (`bin/sail:310`, immediately inside `elif [ "$1" == "up" ]` before the existing `if [ -z "${SAIL_SKIP_SETUP:-}" ] ...`), add:

```bash
    # Auto-heal: ensure per-machine networking is configured (fresh clone / upgrade)...
    # sail:network is a host-side command (writes .env) and must run on the host
    # BEFORE containers start — never via `sail artisan`, which needs a running container.
    if [ -f .env ] && ! grep -q '^SAIL_BIND_IP=' .env; then
        echo "${YELLOW}Configuring Sail networking (sail:network)...${NC}" >&2
        php artisan sail:network >/dev/null 2>&1 || true
    fi
```

- [ ] **Step 3: Migrate `NEEDS_SETUP` var reads and mode-gate the alias/hosts checks**

In the `NEEDS_SETUP` block, change the `SAIL_IP` read (`bin/sail:320`) to prefer `SAIL_BIND_IP`:

```bash
        SAIL_IP=$(grep -E '^SAIL_BIND_IP=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
        if [ -z "$SAIL_IP" ]; then
            SAIL_IP=$(grep -E '^SAIL_IP=' .env | cut -d '=' -f2 | sed 's|http[s]*://||' | cut -d '/' -f1 | tr -d '"')
        fi
        SAIL_MODE=$(grep -E '^SAIL_NETWORK_MODE=' .env | cut -d '=' -f2 | tr -d '"')
        SAIL_MODE=${SAIL_MODE:-local}
```

Then wrap the two host-local `NEEDS_SETUP` checks (the `/etc/hosts` grep at `:323` and the loopback-alias check at `:329-337`) so they only run in `local` mode — in `lan` mode only the cert-existence check should drive `NEEDS_SETUP`. Concretely, guard those two checks with `if [ "$SAIL_MODE" = "local" ]; then ... fi` so they cannot force setup in `lan` mode.

- [ ] **Step 4: Lint**

Run: `shellcheck bin/sail`
Expected: no new warnings versus baseline.

- [ ] **Step 5: Manual verification note (record in commit body)**

- `local` mode: `up` still detects missing hosts entry / loopback alias / certs and runs `sail-setup` as before.
- `lan` mode: `up` does not try to (re)create a loopback alias or hosts entry; it still runs `sail-setup` if the cert is missing.
- Fresh `.env` without `SAIL_BIND_IP`: `up` runs `sail:network` first, then proceeds.

- [ ] **Step 6: Commit**

```bash
git add bin/sail
git commit -m "feat(network): sail up auto-heals networking; mode-aware setup checks

Reads SAIL_BIND_IP (fallback SAIL_IP); in lan mode skips the loopback/hosts
NEEDS_SETUP checks; auto-runs 'artisan sail:network' when SAIL_BIND_IP is
absent. Manual verification: local unchanged; lan skips alias/hosts; fresh
.env triggers sail:network."
```

---

### Task 6: Document LAN mode in the README

**Files:**
- Modify: `README.md` (add a "LAN mode" section)

**Interfaces:** none (docs).

- [ ] **Step 1: Add the section**

Add a new `## Running on a LAN server` section to `README.md` documenting:

```markdown
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

> One project per LAN IP for now. Multiple simultaneous LAN projects on one
> host (shared proxy + mDNS) are coming in a later release.
```

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs(network): document LAN mode usage and cert trust"
```

---

## What comes after this plan

- **Plan 2b:** host-level shared nginx-proxy, per-project port offsets from `HostRegistry`, `COMPOSE_PROFILES`-gated services, and the mDNS/`.local` resolver — enabling multiple simultaneous LAN projects on one host.
- **Plan 3:** `lan-direct` per-project IP-alias mode and plain-HTTP option.

## Self-Review

- **Spec coverage (of the LAN single-project slice):** LAN IP detection (Task 1) ✓; nip.io domain + URL/Vite/APP_URL wiring (Task 2, applied in Task 3) ✓; `sail:network`/`sail:install --mode=lan` writing `.env` without corrupting `SAIL_SUBNET` (Task 3) ✓; `bin/` mode-awareness + `SAIL_IP`→`SAIL_BIND_IP` migration + skip host-local steps in lan (Tasks 4–5) ✓; `sail up` auto-heal (Task 5) ✓; docs + cert-trust guidance (Task 6) ✓. Multi-project/shared-proxy/mDNS explicitly deferred per Global Constraints.
- **Placeholder scan:** every code/bash step contains complete content; no TBD/TODO; commands have expected output; bash tasks carry explicit manual-verification notes in lieu of unit tests.
- **Type/name consistency:** `HostIpDetector::detect/isPlausibleLanIp` (Task 1) used verbatim in Task 3's `applyLanConfig`; `LanEnvironment::domain/values` keys (Task 2) match the assertions in Task 3's feature test and the `.env` keys written by `applyLanConfig`; `SAIL_BIND_IP`/`SAIL_NETWORK_MODE`/`COMPOSE_PROFILES`/`SAIL_DOMAIN`/`VITE_DEV_SERVER_URL` names are consistent across PHP (Tasks 2–3) and bash (Tasks 4–5); `resolveProjectName()`/`ensureBindIp()`/`Writer` reused from Plan 1.
