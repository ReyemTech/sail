<?php

namespace Laravel\Sail\Tests\Unit\Boost;

use FilesystemIterator;
use Illuminate\Support\Facades\Blade;
use Laravel\Sail\Tests\TestCase;

class CoreGuidelineTest extends TestCase
{
    private string $guideline;

    protected function setUp(): void
    {
        parent::setUp();

        $path = dirname(__DIR__, 3).'/resources/boost/guidelines/core.blade.php';

        $this->assertFileExists($path);
        $this->guideline = Blade::render((string) file_get_contents($path));
    }

    public function test_it_preserves_core_guidance_and_documents_the_lan_collision_contract(): void
    {
        foreach ([
            '# ReyemTech Sail',
            'sail:build',
            'accept `reyemtech/sail` when prompted',
            '`php artisan boost:update --discover`',
            'back up `CLAUDE.md`',
            'diff `CLAUDE.md` against the backup',
            'restore `CLAUDE.md` from the backup if any existing section disappears',
            'When `ERR_SSL_UNRECOGNIZED_NAME_ALERT` occurs in LAN shared-proxy mode and either Compose failed to create the network or the app container is stuck in `created`, remember that this documented collision chain is not a certificate problem; check port and subnet collisions before changing certificates or mkcert.',
            'SAIL_BIND_IP',
            'SAIL_SUBNET',
            '~/.config/sail/registry.json',
            'base + slot * 10',
            'mysql',
            'pgsql',
            'mariadb',
            'redis',
            'valkey',
            'only the Mailpit dashboard through `FORWARD_MAILPIT_DASHBOARD_PORT`',
            'Mailpit SMTP through `FORWARD_MAILPIT_PORT`',
            'VITE_PORT',
            'minio',
            'gotenberg',
            'typesense',
            'docker network inspect $(docker network ls -q) --format \'{{.Name}} {{range .IPAM.Config}}{{.Subnet}}{{end}}\'',
            'docker ps --format \'{{.Names}}\t{{.Ports}}\'',
            'subnet overlaps -> Compose fails to create the network before the app container exists',
            'Run `docker compose up -d` and read its output first for network-creation failures',
            'docker compose ps -a',
            "docker inspect <container> --format '{{.State.Error}}'",
            'Use `.State.Error` for container-start failures only',
            'docker logs',
            'overlap with any existing Docker network, including broader or narrower subnets',
            '~/.config/sail/certs/mkcert-rootCA.pem',
            'tls=0',
            '502',
        ] as $required) {
            $this->assertStringContainsString($required, $this->guideline);
        }
    }

    public function test_it_does_not_embed_the_source_app_configuration(): void
    {
        foreach ([
            'slot 3',
            '172.21.0.0/24',
            'VITE_PORT=5203',
            'Horizon',
            'another project already holding that `SAIL_SUBNET`',
        ] as $projectSpecific) {
            $this->assertStringNotContainsString($projectSpecific, $this->guideline);
        }
    }

    public function test_it_uses_a_single_package_guideline_file(): void
    {
        $directory = dirname(__DIR__, 3).'/resources/boost/guidelines';
        $files = [];

        foreach (new FilesystemIterator($directory) as $file) {
            if ($file->isFile()) {
                $files[] = $file->getFilename();
            }
        }

        sort($files);

        $this->assertSame(['core.blade.php'], $files);
    }
}
