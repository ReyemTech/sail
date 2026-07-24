<?php

namespace Laravel\Sail\Tests\Feature;

use Laravel\Sail\Tests\TestCase;

class NetworkConfigTest extends TestCase
{
    public function test_network_config_exposes_defaults(): void
    {
        $config = require __DIR__.'/../../config/sail.php';

        $this->assertArrayHasKey('network', $config);
        $this->assertSame('local', $config['network']['mode']);
        $this->assertSame('mdns', $config['network']['resolver']);
        $this->assertFalse($config['network']['tls']);
        $this->assertSame('172.20.0.10', $config['network']['bind_ip']);
        $this->assertSame('172.20.0.0/24', $config['network']['subnet']);
        $this->assertNull($config['network']['registry_path']);
    }
}
