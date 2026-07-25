# Sentry build-time configuration via Sail config

**Date:** 2026-07-25
**Status:** Approved, ready for planning
**Follows:** PR #33 (`feat/sentry-build-args`) — wired bake → Dockerfile. This spec wires config → bake.

## Problem

PR #33 taught `Dockerfile.app-build` and `runtimes/8.x/docker-bake.hcl` to accept
`VITE_SENTRY_DSN`, `VITE_SENTRY_RELEASE`, `SENTRY_ORG`, `SENTRY_PROJECT` as build args and
`SENTRY_AUTH_TOKEN` as a BuildKit secret. Nothing supplies those values.

`InteractsWithDocker::build()` assembles a fixed `$args` array (`ARCHS`, `PUSH`, `APP_NAME`,
`VERSION`, `APP_DIR`, `RUNTIME_DIR`, `ORG`, `REMOVE_NODE_MODULES`) which
`createBakeCommand()` renders as a `KEY=value ... docker buildx bake` prefix. The Sentry keys
are absent. They reach the build only if a developer happens to have exported them in the
shell, which is undiscoverable and does not survive `.env`-only configuration.

The generated CI pipelines are split across two invocation paths, and both need covering:

| Stub | Build invocation |
| --- | --- |
| `github-actions-build.yml.stub` | `docker buildx bake` directly — bypasses artisan |
| `gitlab-ci`, `azure-pipelines`, `circleci-config`, `buildspec`, `travis-ci` | `php artisan sail:build` |

Docker Bake populates `variable` blocks from the process environment, so the GitHub Actions
path needs only environment wiring in the generated YAML; the other five inherit whatever
`sail:build` does.

## Non-goals

- No new artisan command. This is build configuration and belongs with the existing build config.
- No interactive prompts and no `.env` writing via `dotenvwriter`. Values are read, never persisted.
- No open-ended user-defined build-arg map. Bake resolves `args` through declared HCL `variable`
  blocks and the Dockerfile needs a matching `ARG`, so an arbitrary key cannot work without also
  generating HCL. The supported set is the four Sentry keys plus the token.

## Design

### 1. Configuration

`config/sail.php` gains two keys under `build`:

```php
'args' => array_filter([
    'VITE_SENTRY_DSN'     => env('VITE_SENTRY_DSN'),
    'VITE_SENTRY_RELEASE' => env('VITE_SENTRY_RELEASE', env('SAIL_BUILD_VERSION')),
    'SENTRY_ORG'          => env('SENTRY_ORG'),
    'SENTRY_PROJECT'      => env('SENTRY_PROJECT'),
], fn ($value) => $value !== null && $value !== ''),

'secrets' => array_filter([
    'SENTRY_AUTH_TOKEN' => env('SENTRY_AUTH_TOKEN'),
], fn ($value) => $value !== null && $value !== ''),
```

Rationale for the key names: these are the names the Sentry SDK and `@sentry/vite-plugin`
already use, so a consuming app that has configured Sentry at all already has them in `.env`.
Introducing `SAIL_BUILD_SENTRY_*` aliases would mean two names for one value.

`VITE_SENTRY_RELEASE` falls back to `SAIL_BUILD_VERSION` so the release tag tracks the image
version without separate configuration. It does **not** fall back to `config('sail.build.version')`,
because the config value may have been bumped in-process by `sail:build --bump` after config
resolution; reading the raw env keeps the two independent and predictable.

`array_filter` at the config layer means an unset value is absent from the array rather than
present-and-empty. Downstream code then never has to distinguish the two.

Both keys read through `env()`, which resolves from `$_ENV`/`$_SERVER` when no `.env` entry
exists. A CI runner that exposes `SENTRY_ORG` as a pipeline variable therefore works with no
`.env` file present — this is what makes one mechanism serve local and CI.

### 2. Forwarding args (`InteractsWithDocker::build()`)

Merge the configured args into the existing `$args` array before `createBakeCommand()`:

```php
$args = array_merge($args, config('sail.build.args', []));
```

Because empty values were already filtered out, an app with no Sentry configuration produces a
byte-identical bake command to today. Sail's own keys are listed first so a stray identically
named config entry cannot clobber `VERSION` or `ARCHS` — the merge order is deliberate and
worth a comment.

### 3. Forwarding the secret without leaking it

`createBakeCommand()` writes the fully rendered command to stdout under `=> Build Command:`.
Putting `SENTRY_AUTH_TOKEN=…` in that prefix would print the token into the terminal and into
every CI log that runs `sail:build`. The token must never enter the command string.

Instead, pass it through the child process environment:

- `InteractsWithDocker::runCommands()` gains an optional second parameter `array $env = []`,
  forwarded as the third argument to `Process::fromShellCommandline()`. Symfony merges that
  array over the inherited environment rather than replacing it, so all existing behaviour is
  preserved when the array is empty.
- `build()` passes `config('sail.build.secrets', [])`.

The `secret = ["type=env,id=sentry_auth_token,env=SENTRY_AUTH_TOKEN"]` block added by PR #33
then reads the value from the child environment. `required=false` in the Dockerfile keeps the
no-token path working unchanged.

Note `InteractsWithDockerComposeServices` declares its own unrelated `runCommands()`; only the
`InteractsWithDocker` copy changes, and it has exactly one caller.

### 4. CI stubs

All six stubs emit the wiring unconditionally, with values sourced from the provider's own
secret/variable mechanism and empty when unconfigured.

- `github-actions-build.yml.stub` — add to the `env:` block of the *Build and push images*
  step: `VITE_SENTRY_DSN`, `SENTRY_ORG`, `SENTRY_PROJECT` from `${{ vars.* }}`,
  `SENTRY_AUTH_TOKEN` from `${{ secrets.SENTRY_AUTH_TOKEN }}`, and
  `VITE_SENTRY_RELEASE: ${{ needs.release-please.outputs.version }}`. Bake reads them from the
  environment directly; no artisan involvement.
- The five artisan-based stubs — export the same five variables in the build step before
  `php artisan sail:build`, using each provider's secret syntax (GitLab CI/CD variables, Azure
  pipeline variables, CircleCI context/env, CodeBuild `secrets-manager` / env, Travis encrypted
  env).

An unconfigured pipeline passes empty strings, which the config-layer `array_filter` discards.

### 5. Delete `runtimes/8.5/docker-bake.hcl`

Codex's review comment on PR #33 asks for the Sentry wiring to be mirrored into
`runtimes/8.5/docker-bake.hcl`. That file is dead:

- `runtimes/8.5/` contains only this file — no Dockerfiles. `RUNTIME_DIR` points at `../8.x`.
- `InteractsWithDocker.php:194` hardcodes `-f .../runtimes/8.x/docker-bake.hcl`. No source file,
  stub, or doc references the 8.5 bake file. `PublishCommand` rewrites only compose paths for
  the local-dev runtime directories.
- It is an unmaintained copy from commit `4ea2ccf` that has already drifted three other ways:
  missing `REMOVE_NODE_MODULES`, missing the `PHP_VERSION`/`PHP_VERSION_NUM` base args, and a
  different `platforms` expression.

PHP version is already a bake variable in the 8.x file, so consolidating loses nothing. Delete
it and update the CLAUDE.md line that claims "PHP 8.x and 8.5 supported" for runtimes.

## Data flow

```
.env / CI environment variable
  → env() in config/sail.php
    → config('sail.build.args')    → $args → createBakeCommand() prefix → bake variable → target args → Dockerfile ARG → npm run build
    → config('sail.build.secrets') → Process env (never printed) → bake type=env secret → --mount=type=secret → npm run build

GitHub Actions only:
  repo secret / variable → step env: → bake variable (direct, no artisan)
```

## Error handling

There is no failure mode to handle. Every value is optional and absent-by-default; a missing
value reproduces today's behaviour exactly. Sail deliberately does not validate DSN format or
verify the token — that is Sentry's job, and a build-time check would add a network dependency
to `sail:build`.

One guard is worth adding: if `SENTRY_AUTH_TOKEN` is set but `SENTRY_ORG` or `SENTRY_PROJECT`
is not, `@sentry/vite-plugin` will attempt an upload and fail the build with an opaque error.
`sail:build` emits a warning line in that case and continues. It does not abort — the app's
`vite.config.js` may supply org/project itself.

## Testing

Feature tests under `tests/Feature`, following existing command-test patterns:

1. **No Sentry config → unchanged command.** Assert the rendered bake command is identical to
   the pre-change baseline and that no secret env is passed.
2. **Args forwarded.** With the four values set, assert each appears as a `KEY=value` prefix in
   the rendered command.
3. **Token never printed.** With `SENTRY_AUTH_TOKEN` set, assert the string does not appear in
   the command string or in captured command output, and that it *is* present in the env array
   handed to `runCommands()`.
4. **Release falls back to build version.** With `SAIL_BUILD_VERSION` set and
   `VITE_SENTRY_RELEASE` unset, assert `VITE_SENTRY_RELEASE` resolves to the build version.
5. **Sail keys win the merge.** With a config arg named `VERSION`, assert Sail's own value survives.
6. **Orphan-token warning.** Token set, org unset → warning emitted, exit code unchanged.
7. **CI stub generation.** For each of the six providers, assert `sail:ci` output contains the
   Sentry environment wiring.

Verification beyond tests: `docker buildx bake --print app-build` with and without the values
set, confirming forwarded args and the resolved `type=env` secret — the same check used to
verify PR #33.

## Files touched

| File | Change |
| --- | --- |
| `config/sail.php` | Add `build.args`, `build.secrets` |
| `src/Console/Concerns/InteractsWithDocker.php` | Merge args; `runCommands()` gains `array $env = []`; orphan-token warning |
| `stubs/ci/github-actions-build.yml.stub` | Sentry env on the direct-bake step |
| `stubs/ci/{gitlab-ci,azure-pipelines,circleci-config,buildspec,travis-ci}.*.stub` | Export Sentry env before `sail:build` |
| `runtimes/8.5/docker-bake.hcl` | Delete |
| `CLAUDE.md` | Correct the runtimes line |
| `README.md` | Document the five variables under build configuration |
| `tests/Feature/…` | Tests above |

## Backward compatibility

Fully backward compatible. Every value is optional; with none set the bake command, the child
environment, and the built image are unchanged. Existing generated CI pipelines keep working —
regenerating with `sail:ci` is only needed to pick up the new wiring.
