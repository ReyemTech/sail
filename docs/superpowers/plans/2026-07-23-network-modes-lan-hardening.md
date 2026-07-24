# Network Modes — LAN Hardening (Plan 2b-hardening) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Close the robustness gaps the Plan 2b whole-branch review deferred, so multi-project `lan` mode survives real-world use before hardware validation: a missing/stale `SAIL_FILES` override no longer bricks `sail up`, `sail-setup` stops re-running every lan `up`, and `HostRegistry` slot allocation is safe under concurrent access.

**Architecture:** Small, targeted fixes on the Plan 2b surface. Make `applyLanConfig()` idempotent by reusing the stored `SAIL_BIND_IP` (so regenerating the lan config never drifts the IP/domain), add a `bin/sail` self-heal that regenerates a missing lan override before the global `SAIL_FILES` check bails, point the lan `NEEDS_SETUP` cert check at the shared certs dir, and make `HostRegistry::save()` atomic.

**Tech Stack:** PHP 8.x (Laravel package, Testbench, PHPUnit), Bash (`bin/sail`), `mirazmac/dotenvwriter`.

## Global Constraints

- Namespace `Laravel\Sail\` (from `src/`); tests `Laravel\Sail\Tests\` (from `tests/`).
- PHPStan level 0 clean on `src` (no baseline entries added).
- StyleCI 4-space PHP, LF.
- PHP tests via `vendor/bin/phpunit` (currently 120 green, 21 skipped).
- Bash: `bash -n bin/sail` valid; `shellcheck bin/sail` NO new findings vs baseline; each bash task carries a manual-verification note.
- `local` mode MUST stay byte-identical.
- No new Composer dependencies.
- Names/paths already established: shared network `sail-shared`; host state `SAIL_HOME` else `$HOME/.config/sail`; shared certs `$SAIL_HOME/certs`; `SailHome`/`HostRegistry`/`applyLanConfig` from Plans 1/2a/2b.

## File Structure

- `src/Console/Concerns/InteractsWithDockerComposeServices.php` (modify) — `applyLanConfig()` reuses stored `SAIL_BIND_IP`.
- `src/Networking/HostRegistry.php` (modify) — atomic `save()`.
- `bin/sail` (modify) — regenerate missing lan override before the `SAIL_FILES` loop; lan `NEEDS_SETUP` cert check uses the shared certs dir.
- Tests: `tests/Feature/NetworkLanSharedTest.php` (extend), `tests/Unit/Networking/HostRegistryTest.php` (extend).

---

### Task 1: `applyLanConfig()` reuses the stored `SAIL_BIND_IP` (idempotent re-config)

So that re-running `sail:network --mode=lan` (including the auto-heal in Task 2) reuses the project's existing bind IP/domain instead of re-detecting a possibly-different LAN IP.

**Files:**
- Modify: `src/Console/Concerns/InteractsWithDockerComposeServices.php` (`applyLanConfig()`, the `$ip` resolution line)
- Test: `tests/Feature/NetworkLanSharedTest.php` (add a case)

**Interfaces:**
- Consumes: `HostIpDetector` (Plan 2a).
- Produces: `applyLanConfig()` resolves the bind IP as: explicit `$ip` arg → existing `SAIL_BIND_IP` in `.env` → `HostIpDetector::detect()`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/NetworkLanSharedTest.php`:

```php
    public function test_lan_reuses_existing_bind_ip_when_no_ip_given(): void
    {
        // .env already has a bind IP from a prior lan run; no --ip passed this time.
        File::put($this->base.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=alpha\nSAIL_BIND_IP=192.168.9.9\n");

        $this->artisan('sail:network', ['--mode' => 'lan'])->assertSuccessful();

        $env = File::get($this->base.'/.env');
        // The stored IP is reused (not re-detected), so the domain is built from it.
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.9.9"', $env);
        $this->assertStringContainsString('alpha.192-168-9-9.nip.io', $env);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Feature/NetworkLanSharedTest.php --filter test_lan_reuses_existing_bind_ip`
Expected: FAIL — without `--ip`, `applyLanConfig` calls `HostIpDetector::detect()` (real host IP or null), not the stored `192.168.9.9`.

- [ ] **Step 3: Reuse the stored bind IP**

In `applyLanConfig()`, the current bind-IP resolution is:

```php
        $ip = $ip ?: (new HostIpDetector)->detect();
```

Replace it with a chain that prefers an existing `.env` `SAIL_BIND_IP`:

```php
        if (! $ip) {
            $envPath = base_path('.env');
            if (is_file($envPath) && preg_match('/^SAIL_BIND_IP=(.*)$/m', file_get_contents($envPath), $m)) {
                $existing = trim($m[1], " \"'");
                $ip = $existing !== '' ? $existing : null;
            }
        }

        $ip = $ip ?: (new HostIpDetector)->detect();
```

(The `RuntimeException` when `$ip` is still falsy stays unchanged below this.)

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Feature/NetworkLanSharedTest.php`
Expected: PASS (existing cases + the new one).

- [ ] **Step 5: Full suite + phpstan + commit**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse src` → all pass, `[OK] No errors`.

```bash
git add src/Console/Concerns/InteractsWithDockerComposeServices.php tests/Feature/NetworkLanSharedTest.php
git commit -m "fix(network): applyLanConfig reuses stored SAIL_BIND_IP (idempotent re-config)"
```

---

### Task 2: `bin/sail` regenerates a missing lan override before the `SAIL_FILES` check

`bin/sail`'s global `SAIL_FILES` loop exits 1 if any listed file is missing. In lan mode `SAIL_FILES` points at an absolute override path under `$SAIL_HOME/overrides`; if that file is gone (dir cleaned, `.env` copied to another machine), every `sail` command bricks. Regenerate it first.

**Files:**
- Modify: `bin/sail` (add a heal block after `.env` is loaded — around line 137 — and BEFORE the `SAIL_FILES` processing loop at ~line 194)

**Interfaces:** consumes `.env` `SAIL_NETWORK_MODE`, `SAIL_FILES`; runs `php artisan sail:network --mode=lan` (host-side), which is now idempotent (Task 1).

- [ ] **Step 1: Read the region**

Run: `sed -n '133,210p' bin/sail` to see the `.env` load, the `SAIL_FILES` var, and the loop.

- [ ] **Step 2: Add the override self-heal before the SAIL_FILES loop**

Immediately before the `if [ -n "$SAIL_FILES" ]; then` loop (~line 194), add:

```bash
# In lan mode, regenerate a missing SAIL_FILES override before the check below bails...
if [ -f .env ] && [ -n "${SAIL_FILES:-}" ]; then
    SAIL_MODE_HEAL=$(grep -E '^SAIL_NETWORK_MODE=' .env 2>/dev/null | cut -d '=' -f2 | tr -d '"')
    if [ "${SAIL_MODE_HEAL:-local}" = "lan" ]; then
        IFS=':' read -ra SAIL_FILE_LIST <<< "$SAIL_FILES"
        for F in "${SAIL_FILE_LIST[@]}"; do
            if [ ! -f "$F" ]; then
                echo "${YELLOW}Regenerating missing LAN override ($F)...${NC}" >&2
                php artisan sail:network --mode=lan >/dev/null 2>&1 || true
                break
            fi
        done
    fi
fi
```

(This runs `sail:network --mode=lan` once if any listed file is missing; Task 1 makes that idempotent so the bind IP/domain/slot are preserved. The subsequent loop then finds the regenerated override.)

- [ ] **Step 3: Lint + verify**

Run: `bash -n bin/sail` → valid.
Run: `shellcheck bin/sail` → no new findings vs baseline (`git stash`/`git stash pop` compare).

- [ ] **Step 4: Commit (manual-verification note in body)**

```bash
git add bin/sail
git commit -m "fix(network): regenerate a missing lan SAIL_FILES override before bailing

Manual verification: local mode (SAIL_FILES empty) skips the block; lan mode
with a present override does nothing; lan mode with a deleted override
re-runs sail:network --mode=lan (idempotent) then proceeds."
```

---

### Task 3: lan `NEEDS_SETUP` cert check uses the shared certs dir

In lan mode certs live in `$SAIL_HOME/certs`, not `$RUNTIME_DIR/certs`, so the existing per-domain check (`[ ! -f "$RUNTIME_DIR/certs/$DOMAIN.crt" ]`) is always true → `sail-setup` re-runs every lan `up`. Point the check at the right dir per mode.

**Files:**
- Modify: `bin/sail` (the `NEEDS_SETUP` cert checks in the `up` branch, ~lines 320-332)

**Interfaces:** consumes `.env` `SAIL_NETWORK_MODE`, `SAIL_HOME`.

- [ ] **Step 1: Read the NEEDS_SETUP block**

Run: `sed -n '318,345p' bin/sail`.

- [ ] **Step 2: Compute the cert dir by mode and use it in the per-domain check**

In the `NEEDS_SETUP` block, after `SAIL_MODE` is read (added in Plan 2b Task 5/6), compute the cert dir and use it for the per-domain check. Replace the existing per-domain cert check line:

```bash
        elif [ -n "$DOMAIN" ] && [ ! -f "$RUNTIME_DIR/certs/$DOMAIN.crt" ]; then
            NEEDS_SETUP=1
```

with:

```bash
        elif [ -n "$DOMAIN" ] && [ ! -f "$CERT_CHECK_DIR/$DOMAIN.crt" ]; then
            NEEDS_SETUP=1
```

and define `CERT_CHECK_DIR` just before the cert checks (after `SAIL_MODE` is known):

```bash
        SAIL_HOME_DIR=${SAIL_HOME:-"$HOME/.config/sail"}
        if [ "$SAIL_MODE" = "lan" ]; then
            CERT_CHECK_DIR="$SAIL_HOME_DIR/certs"
        else
            CERT_CHECK_DIR="$RUNTIME_DIR/certs"
        fi
```

Leave the `[ ! -d "$RUNTIME_DIR/certs" ]` and `mkcert-rootCA.pem` checks as-is (they gate the very first setup and are fine to keep; in lan mode the per-domain check above is the meaningful one).

- [ ] **Step 3: Lint + verify**

Run: `bash -n bin/sail` → valid.
Run: `shellcheck bin/sail` → no new findings vs baseline.

- [ ] **Step 4: Commit (manual-verification note in body)**

```bash
git add bin/sail
git commit -m "fix(network): lan NEEDS_SETUP checks the shared certs dir (stop re-firing)

Manual verification: local mode checks \$RUNTIME_DIR/certs as before; lan mode
checks \$SAIL_HOME/certs/<domain>.crt, so a second 'sail up' with the cert
already present no longer re-runs sail-setup."
```

---

### Task 4: Atomic `HostRegistry::save()`

Concurrent first-time `sail:network`/`sail up` across projects sharing the registry could interleave a truncate+write and hand out a duplicate slot (→ colliding forward ports). Write to a temp file and atomically rename.

**Files:**
- Modify: `src/Networking/HostRegistry.php` (`save()`)
- Test: `tests/Unit/Networking/HostRegistryTest.php` (add a case)

**Interfaces:** unchanged public API; `save()` becomes atomic.

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/Networking/HostRegistryTest.php`:

```php
    public function test_save_writes_atomically_without_leaving_temp_files(): void
    {
        $registry = new HostRegistry($this->path);
        $registry->slotFor('alpha');
        $registry->slotFor('beta');

        // The registry file exists and parses; no leftover temp sibling for THIS path.
        $this->assertFileExists($this->path);
        $this->assertIsArray(json_decode((string) file_get_contents($this->path), true));
        $this->assertSame([], glob($this->path.'.*.tmp'));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Networking/HostRegistryTest.php --filter test_save_writes_atomically`
Expected: This is a regression guard rather than a strict RED — atomicity can't be RED-tested without concurrency. It asserts the final file is present, valid, and leaves no `<path>.*.tmp` sibling. It should PASS after Step 3's atomic rewrite; if it errors before Step 3 (method still direct-writes), that's fine — the guard's purpose is to prevent a future non-atomic impl from leaking temp files.

- [ ] **Step 3: Make `save()` atomic**

Replace the body of `save()` in `src/Networking/HostRegistry.php`:

```php
    private function save(): void
    {
        $dir = dirname($this->path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tmp = $this->path.'.'.getmypid().'.tmp';
        file_put_contents($tmp, json_encode($this->slots, JSON_PRETTY_PRINT).PHP_EOL, LOCK_EX);
        rename($tmp, $this->path);
    }
```

(`rename()` over the same filesystem is atomic; `LOCK_EX` guards the temp write. `getmypid()` keeps concurrent writers' temp files distinct.)

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Networking/HostRegistryTest.php`
Expected: PASS (all cases; no leftover `.tmp`).

- [ ] **Step 5: Full suite + phpstan + commit**

Run: `vendor/bin/phpunit && vendor/bin/phpstan analyse src` → all pass.

```bash
git add src/Networking/HostRegistry.php tests/Unit/Networking/HostRegistryTest.php
git commit -m "fix(network): atomic HostRegistry::save() (temp file + rename)"
```

---

## What comes after this plan

- **Plan 2c:** mDNS (`.local`) resolver — `avahi-publish` sidecar as an alternative to nip.io.
- **Plan 3:** `lan-direct` per-project IP-alias mode; plain-HTTP LAN option.
- Remaining documented minors (non-blocking): `sail-setup` overwrites generic `public.crt`/`private.key` in the shared certs dir (last-writer-wins, harmless — nginx-proxy uses per-domain certs); the shared-proxy Docker calls (`ProxyCommand`, `bin/sail` network-create) hardcode `docker` rather than honoring `SAIL_DOCKER_BINARY` (podman).

## Self-Review

- **Coverage:** idempotent re-config (Task 1) is the precondition that makes the override self-heal (Task 2) safe; the re-fire fix (Task 3) and atomic save (Task 4) are independent. All four map to recorded Plan 2b review follow-ups; the two remaining minors are explicitly deferred above.
- **Placeholders:** every step has complete code; bash steps carry manual-verification notes.
- **Consistency:** `SAIL_BIND_IP`/`SAIL_NETWORK_MODE`/`SAIL_HOME`/`$SAIL_HOME/certs`/`sail:network --mode=lan` names match the existing PHP + bash from Plans 1/2a/2b; `HostRegistry::save()` signature unchanged.
