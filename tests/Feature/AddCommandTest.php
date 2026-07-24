<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Console\AddCommand;
use Laravel\Sail\Tests\TestCase;

/**
 * A test double for AddCommand that neutralizes the parts of
 * prepareInstallation() that shell out to docker / sail-setup, so the
 * command can be exercised end-to-end through the console kernel without
 * requiring a real Docker environment.
 */
class TestAddCommand extends AddCommand
{
    protected function prepareInstallation($services)
    {
        // no-op: skip runSetup()/docker calls for testability
    }
}

class AddCommandTest extends TestCase
{
    protected string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBasePath = sys_get_temp_dir().'/sail-add-test-'.uniqid();
        File::makeDirectory($this->testBasePath, 0755, true);

        $this->app->setBasePath($this->testBasePath);
        chdir($this->testBasePath);

        $this->createTestLaravelStructure();

        // Register the test double under the "sail:add" name so
        // $this->artisan('sail:add', ...) resolves to it instead of the
        // real AddCommand (which would shell out to docker/sail-setup).
        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(new TestAddCommand());
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

        File::put($this->testBasePath.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\nSAIL_IP=172.20.0.11\n");

        File::makeDirectory($this->testBasePath.'/vendor/reyemtech/sail/runtimes/8.x', 0755, true);

        // Seed a pre-existing docker-compose.yml (as a real Sail install
        // would have) so buildDockerCompose() rewrites it in place instead
        // of falling back to a fresh compose.yaml.
        File::copy(__DIR__.'/../../stubs/compose.stub', $this->testBasePath.'/docker-compose.yml');
    }

    public function test_sail_add_passes_resolved_project_name_to_compose_and_env(): void
    {
        $this->artisan('sail:add', ['services' => 'redis'])
            ->assertSuccessful();

        $env = file_get_contents($this->testBasePath.'/.env');
        $this->assertStringContainsString('SAIL_BIND_IP="172.20.0.11"', $env, 'ensureBindIp should seed SAIL_BIND_IP from the existing SAIL_IP, not the .10 default');

        $compose = file_get_contents($this->testBasePath.'/docker-compose.yml');
        $this->assertStringContainsString('name: myproj', $compose, 'buildDockerCompose must receive the resolved project name, not default to "laravel"');
    }

    private function makeResolveProjectNameCommand()
    {
        $command = new class extends \Illuminate\Console\Command {
            use \Laravel\Sail\Console\Concerns\InteractsWithDockerComposeServices;
        };
        $command->setLaravel($this->app);

        return $command;
    }

    private function callResolveProjectName($command): string
    {
        $method = new \ReflectionMethod($command, 'resolveProjectName');
        $method->setAccessible(true);

        return $method->invoke($command);
    }

    public function test_resolve_project_name_returns_sail_project_from_env(): void
    {
        File::put($this->testBasePath.'/.env', "APP_NAME=TestApp\nSAIL_PROJECT=myproj\n");

        $this->assertSame('myproj', $this->callResolveProjectName($this->makeResolveProjectNameCommand()));
    }

    public function test_resolve_project_name_falls_back_to_directory_name_when_env_has_no_sail_project(): void
    {
        File::put($this->testBasePath.'/.env', "APP_NAME=TestApp\n");

        $this->assertSame(basename(getcwd()), $this->callResolveProjectName($this->makeResolveProjectNameCommand()));
    }
}
