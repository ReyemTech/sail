<?php

namespace Laravel\Sail\Tests\Feature;

use Laravel\Sail\Tests\TestCase;

class LocalRuntimeBakeTest extends TestCase
{
    public function test_bake_accepts_an_alpine_override_and_a_separate_certificate_context(): void
    {
        $bake = file_get_contents(__DIR__.'/../../runtimes/8.x/docker-bake.hcl');

        $this->assertNotFalse($bake);
        $this->assertStringContainsString('variable "ALPINE_VERSION"', $bake);
        $this->assertStringContainsString('"certs" = "${CERTS_DIR}"', $bake);
        $this->assertStringContainsString('ALPINE_VERSION = "${ALPINE_VERSION}"', $bake);
    }

    public function test_the_base_image_optionally_reads_the_ca_from_the_certificate_context(): void
    {
        $dockerfile = file_get_contents(__DIR__.'/../../runtimes/8.x/Dockerfile.base');

        $this->assertNotFalse($dockerfile);
        $this->assertStringContainsString(
            'COPY --from=certs . /tmp/sail-certs/',
            $dockerfile
        );
        $this->assertStringContainsString('if [ -f /tmp/sail-certs/mkcert-rootCA.pem ]', $dockerfile);
        $this->assertFileExists(__DIR__.'/../../runtimes/8.x/certs/.gitkeep');
    }

    public function test_the_shared_runtime_installs_opcache_only_for_php_versions_that_need_it(): void
    {
        $dockerfile = file_get_contents(__DIR__.'/../../runtimes/8.x/Dockerfile.base');

        $this->assertNotFalse($dockerfile);
        $this->assertStringNotContainsString('    php${VERSION}-opcache \\', $dockerfile);
        $this->assertStringContainsString('if [ "${VERSION}" != "85" ]; then apk add --no-cache "php${VERSION}-opcache"; fi', $dockerfile);
    }

    public function test_local_up_forwards_php_and_compose_alpine_arguments_and_stops_on_bake_failure(): void
    {
        $sail = file_get_contents(__DIR__.'/../../bin/sail');

        $this->assertNotFalse($sail);
        $this->assertStringContainsString('config --format json', $sail);
        $this->assertStringContainsString('"CERTS_DIR=$BUILD_CERTS_DIR"', $sail);
        $this->assertStringContainsString('"PHP_VERSION=$PHP_VERSION"', $sail);
        $this->assertStringContainsString('exit "$BAKE_EXIT"', $sail);
    }

    public function test_the_local_runtime_clears_caches_on_boot_rather_than_building_them(): void
    {
        // Caching config and routes in a development container makes a restart
        // silently revert the app to whatever .env held at boot, and cached
        // routes skip the routing closure in bootstrap/app.php altogether.
        $prepare = file_get_contents(__DIR__.'/../../runtimes/8.x/s6/local/laravel-prepare/up');

        $this->assertNotFalse($prepare);
        $this->assertStringContainsString('php artisan optimize:clear', $prepare);
        $this->assertDoesNotMatchRegularExpression('/artisan optimize\s*}/', $prepare);
    }

    public function test_the_production_runtime_still_builds_its_caches(): void
    {
        $prepare = file_get_contents(__DIR__.'/../../runtimes/8.x/s6/app/laravel-prepare/up');

        $this->assertNotFalse($prepare);
        $this->assertMatchesRegularExpression('/artisan optimize\s*}/', $prepare);
    }
}
