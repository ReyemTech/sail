<?php

namespace Laravel\Sail\Tests\Unit\Networking;

use Laravel\Sail\Networking\HostRegistry;
use Laravel\Sail\Tests\TestCase;

class HostRegistryTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/sail-registry-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        @unlink($this->path.'.lock');
        parent::tearDown();
    }

    public function test_first_project_gets_slot_zero(): void
    {
        $registry = new HostRegistry($this->path);
        $this->assertSame(0, $registry->slotFor('alpha'));
    }

    public function test_slot_assignment_is_idempotent(): void
    {
        $registry = new HostRegistry($this->path);
        $this->assertSame(0, $registry->slotFor('alpha'));
        $this->assertSame(0, $registry->slotFor('alpha'));
    }

    public function test_second_project_gets_next_slot(): void
    {
        $registry = new HostRegistry($this->path);
        $registry->slotFor('alpha');
        $this->assertSame(1, $registry->slotFor('beta'));
    }

    public function test_release_frees_slot_for_reuse(): void
    {
        $registry = new HostRegistry($this->path);
        $registry->slotFor('alpha'); // 0
        $registry->slotFor('beta');  // 1
        $registry->release('alpha');
        $this->assertSame(0, $registry->slotFor('gamma')); // lowest free reused
    }

    public function test_allocation_persists_across_instances(): void
    {
        (new HostRegistry($this->path))->slotFor('alpha'); // 0
        $reloaded = new HostRegistry($this->path);
        $this->assertSame(1, $reloaded->slotFor('beta'));
    }

    public function test_prune_removes_missing_projects(): void
    {
        $registry = new HostRegistry($this->path);
        $registry->slotFor('alpha'); // 0
        $registry->slotFor('beta');  // 1
        $registry->prune(['beta']);
        $this->assertSame(['beta' => 1], $registry->projects());
    }

    public function test_port_helper_applies_slot_stride(): void
    {
        $this->assertSame(3306, HostRegistry::port(3306, 0));
        $this->assertSame(3316, HostRegistry::port(3306, 1));
        $this->assertSame(3326, HostRegistry::port(3306, 2));
    }

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

    public function test_slot_for_reloads_committed_state_from_disk(): void
    {
        $early = new HostRegistry($this->path);              // loads an empty snapshot
        (new HostRegistry($this->path))->slotFor('alpha');   // a different instance commits alpha=0

        // Without reload-under-lock, $early would hand out slot 0 from its stale snapshot.
        $this->assertSame(1, $early->slotFor('beta'));
    }
}
