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
