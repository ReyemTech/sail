<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Console\InstallCommand;
use Laravel\Sail\Tests\TestCase;

/**
 * A test double for InstallCommand that:
 *
 *  - Pins the LAN IP passed to applyLanConfig() so the resulting nip.io
 *    domain is deterministic regardless of the host/CI machine's real
 *    network interfaces (HostIpDetector has no other injection seam from
 *    InstallCommand, which — unlike NetworkCommand — has no --ip option).
 *  - Neutralizes prepareInstallation() (which shells out to sail-setup /
 *    docker) but, crucially, first RECORDS a snapshot of the .env file at
 *    the moment it is invoked. If FIX 2's reordering regresses (i.e.
 *    prepareInstallation runs before applyLanConfig again), the recorded
 *    snapshot will be missing the lan values, and the assertions will fail.
 */
class TestInstallCommand extends InstallCommand
{
    public ?string $envAtPrepareInstallation = null;

    protected function applyLanConfig(?string $ip = null, ?string $domain = null): array
    {
        // Pin a fixed, plausible LAN IP so the nip.io domain is deterministic
        // in any environment (CI or local), instead of relying on whatever
        // HostIpDetector discovers on the running machine.
        return parent::applyLanConfig($ip ?? '192.168.50.77', $domain);
    }

    protected function prepareInstallation($services)
    {
        $this->envAtPrepareInstallation = file_get_contents(base_path('.env'));

        // no-op: skip runSetup()/docker calls for testability
    }
}

class InstallLanModeTest extends TestCase
{
    protected string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBasePath = sys_get_temp_dir().'/sail-install-lan-test-'.uniqid();
        File::makeDirectory($this->testBasePath, 0755, true);

        $this->app->setBasePath($this->testBasePath);
        chdir($this->testBasePath);

        $this->createTestLaravelStructure();

        // Register the test double under the "sail:install" name so
        // $this->artisan('sail:install', ...) resolves to it instead of the
        // real InstallCommand (which would shell out to docker/sail-setup).
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(new TestInstallCommand());
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBasePath)) {
            File::deleteDirectory($this->testBasePath);
        }

        parent::tearDown();
    }

    protected function createTestLaravelStructure(): void
    {
        File::put($this->testBasePath.'/composer.json', json_encode([
            'name' => 'test/app',
            'require' => [
                'php' => '^8.0',
            ],
        ], JSON_PRETTY_PRINT));

        File::makeDirectory($this->testBasePath.'/config', 0755, true);
        File::put($this->testBasePath.'/config/app.php', "<?php return ['name' => 'TestApp'];");

        File::put($this->testBasePath.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");

        File::makeDirectory($this->testBasePath.'/vendor/reyemtech/sail/runtimes/8.x', 0755, true);
    }

    public function test_install_with_lan_mode_applies_lan_config_before_prepare_installation(): void
    {
        $this->artisan('sail:install', [
            '--mode' => 'lan',
            '--with' => 'none',
            '--no-interaction' => true,
        ])
            ->expectsQuestion('What is the name of the Project?', 'myproj')
            ->expectsQuestion('What IP Address should be used for the container?', '172.20.0.10')
            ->expectsQuestion('What domain should be used for the container?', 'myproj.test')
            ->assertSuccessful();

        /** @var TestInstallCommand $command */
        $command = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all()['sail:install'];

        $this->assertNotNull(
            $command->envAtPrepareInstallation,
            'prepareInstallation() should have been invoked and recorded a .env snapshot.'
        );

        // The snapshot was captured the moment prepareInstallation() ran. If
        // it already contains the lan network mode and a nip.io domain, lan
        // config must have been applied BEFORE setup — proving the fix's
        // ordering.
        $this->assertStringContainsString(
            'SAIL_NETWORK_MODE=lan',
            $command->envAtPrepareInstallation,
            'SAIL_NETWORK_MODE=lan must already be present in .env by the time prepareInstallation() (which runs sail-setup) executes.'
        );

        $this->assertMatchesRegularExpression(
            '/SAIL_DOMAIN="[^"]*\.nip\.io"/',
            $command->envAtPrepareInstallation,
            'SAIL_DOMAIN must already be a nip.io domain by the time prepareInstallation() executes, so sail-setup certs the correct domain.'
        );

        // Final .env (post-command) should reflect the same, confirming
        // nothing later reverted the lan config.
        $env = File::get($this->testBasePath.'/.env');
        $this->assertStringContainsString('SAIL_NETWORK_MODE=lan', $env);
        $this->assertStringContainsString('SAIL_BIND_IP="192.168.50.77"', $env);
        $this->assertStringContainsString('SAIL_DOMAIN="myproj.192-168-50-77.nip.io"', $env);
    }

    public function test_install_without_lan_mode_does_not_apply_lan_config(): void
    {
        $this->artisan('sail:install', [
            '--with' => 'none',
            '--no-interaction' => true,
        ])
            ->expectsQuestion('What is the name of the Project?', 'myproj')
            ->expectsQuestion('What IP Address should be used for the container?', '172.20.0.10')
            ->expectsQuestion('What domain should be used for the container?', 'myproj.test')
            ->assertSuccessful();

        /** @var TestInstallCommand $command */
        $command = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all()['sail:install'];

        $this->assertNotNull($command->envAtPrepareInstallation);
        $this->assertStringNotContainsString('SAIL_NETWORK_MODE=lan', $command->envAtPrepareInstallation);

        $env = File::get($this->testBasePath.'/.env');
        $this->assertStringNotContainsString('SAIL_NETWORK_MODE=lan', $env);
    }
}
