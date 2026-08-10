<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\DarwinMdnsPublisher;
use Laravel\Sail\Tests\TestCase;

class DarwinMdnsPublisherTest extends TestCase
{
    public function test_it_writes_and_bootstraps_a_per_project_launch_agent(): void
    {
        $directory = sys_get_temp_dir().'/sail-launchagents-'.uniqid();
        $commands = [];
        $publisher = new DarwinMdnsPublisher($directory, function (string $command) use (&$commands): void {
            $commands[] = $command;
        });

        $publisher->publish('Ledger App', 'ledger.local', '192.168.1.50');

        $path = $publisher->path('Ledger App');
        $this->assertFileExists($path);
        $plist = file_get_contents($path);
        $this->assertStringContainsString('com.reyemtech.sail.mdns.ledger-app', $plist);
        $this->assertStringContainsString('<string>ledger.local</string>', $plist);
        $this->assertStringContainsString('<string>192.168.1.50</string>', $plist);
        $this->assertStringContainsString('launchctl bootstrap', implode("\n", $commands));

        $publisher->stop('Ledger App');
        $this->assertFileDoesNotExist($path);
        (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($directory);
    }
}
