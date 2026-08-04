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
            'When `ERR_SSL_UNRECOGNIZED_NAME_ALERT` occurs in LAN shared-proxy mode and the app container is stuck in `created`, remember that this documented collision chain is not a certificate problem; check port and subnet collisions before changing certificates or mkcert.',
            'SAIL_BIND_IP',
            'SAIL_SUBNET',
            '~/.config/sail/registry.json',
            'base + slot * 10',
            'mysql',
            'pgsql',
            'mariadb',
            'redis',
            'valkey',
            'mailpit',
            'VITE_PORT',
            'minio',
            'gotenberg',
            'typesense',
            'docker network inspect $(docker network ls -q) --format \'{{.Name}} {{range .IPAM.Config}}{{.Subnet}}{{end}}\'',
            'docker ps --format \'{{.Names}}\t{{.Ports}}\'',
            'docker compose ps -a',
            "docker inspect <container> --format '{{.State.Error}}'",
            'docker logs',
            'Pool overlaps with other one on this address space',
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
