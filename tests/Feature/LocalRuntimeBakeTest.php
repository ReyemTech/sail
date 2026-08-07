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

    public function test_the_base_image_reads_the_ca_from_the_certificate_context(): void
    {
        $dockerfile = file_get_contents(__DIR__.'/../../runtimes/8.x/Dockerfile.base');

        $this->assertNotFalse($dockerfile);
        $this->assertStringContainsString(
            'COPY --from=certs mkcert-rootCA.pem /usr/local/share/ca-certificates/mkcert-rootCA.crt',
            $dockerfile
        );
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
}
