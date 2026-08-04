# Sail LAN Boost Guideline Design

## Goal

Ship project-agnostic LAN shared-proxy guidance with `reyemtech/sail` so Laravel Boost can compose it into consuming applications without each application maintaining a duplicate `.ai/guidelines` file.

## Package Guideline

Extend `resources/boost/guidelines/core.blade.php` with a dedicated LAN networking section while preserving all existing image-build and Helm guidance. Do not add a second package guideline file.

This single-file structure is required by current Laravel Boost behavior. Live inspection of `GuidelineComposer::getThirdPartyGuidelines()` showed that it loops over sorted package guideline files but stores each result with `$guidelines->put($package, $guideline)`. Because every file for a package uses the same package key, each later file replaces the previous one; only the last sorted file survives. A separate `lan-networking.blade.php` therefore removes Sail's existing core build guidance from composed output rather than composing beside it.

Match the existing guideline style:

- Start with a Blade comment identifying the ReyemTech Sail Boost guideline.
- Use a concise Markdown title.
- Prefer terse, imperative bullets and short diagnostic steps.
- Keep all examples project-agnostic.

The LAN section must:

- State up front that `ERR_SSL_UNRECOGNIZED_NAME_ALERT` in this failure mode is not a certificate problem.
- Explain that LAN shared-proxy mode puts projects behind one `nginx-proxy` on one `SAIL_BIND_IP`, requiring a unique `SAIL_SUBNET` and unique published ports per project.
- Explain that Sail records a slot in `~/.config/sail/registry.json` and calculates allocated ports as `base + slot * 10`.
- Name the services covered by `forwardPortMap()`: MySQL, PostgreSQL, MariaDB, Redis, Valkey, and Mailpit.
- State that Sail does not currently allocate `SAIL_SUBNET`, `VITE_PORT`, or MinIO, Gotenberg, and Typesense ports.
- Show the complete failure chain from a port or subnet collision through a container stuck in `created`, missing `VIRTUAL_HOST`, missing generated vhost, and rejected SNI.
- Prescribe bottom-up diagnosis with `docker compose ps -a`, `docker inspect <container> --format '{{.State.Error}}'`, proxy configuration inspection, and certificate inspection only after those checks.
- Warn that `docker logs` is empty when a container never started and that the actionable error exists only in `.State.Error`.
- Explain that `Pool overlaps with other one on this address space` means another project holds the selected `SAIL_SUBNET`.
- Verify HTTPS with `curl --cacert ~/.config/sail/certs/mkcert-rootCA.pem`, interpreting `tls=0` as successful certificate verification.
- Explain that a `502` immediately after `up -d` usually means the application is still booting and should be retried.

Do not copy the source application's slot number, concrete subnet, concrete Vite port, or Redis/Horizon note.

## README

Add a short Laravel Boost subsection near the LAN networking documentation. State that the package ships third-party Boost guidelines and that discovery is opt-in at `boost:install` or when running `boost:update --discover`.

Clarify that applications which previously declined third-party guidelines, or installed Boost before a guideline was added, do not inherit it automatically and must rerun discovery.

Warn that destructive `boost:update` behavior has been observed in a consuming application. Tell users to back up `CLAUDE.md` and compare its Git diff before and after discovery so unrelated package sections are not silently lost. Do not claim a root cause because it remains undetermined.

## Verification And Cleanup

Run the package test suite and static analysis:

```bash
composer test
vendor/bin/phpstan analyse src
```

For end-to-end verification in `apps/laravel`:

1. Preserve the current `CLAUDE.md` and record its Git diff before discovery.
2. Run Boost discovery using `boost:install` or `boost:update --discover`.
3. Confirm the single composed `reyemtech/sail` section contains both the existing `# ReyemTech Sail` build guidance and the new `# Sail LAN Networking` subsection.
4. Compare `CLAUDE.md` before and after and require proof that no unrelated sections were removed.
5. If discovery drops unrelated content, restore the file and do not treat the end-to-end check as successful.
6. After safe composition is proven, remove `.ai/guidelines/sail-lan-networking.md` and only the hand-inserted `=== .ai/sail-lan-networking rules ===` block from `CLAUDE.md`.

## Scope

This change documents current behavior only. It does not change subnet allocation, add services to `forwardPortMap()`, change Boost's package-keyed composition, or fix the broader destructive update behavior. The snapshot-and-diff safeguard remains required because discovery has also been observed dropping unrelated guidance in a consuming application.
