<?php

namespace Laravel\Sail\Tests\Feature;

use Laravel\Sail\Tests\Stubs\BuildProbeCommand;
use Laravel\Sail\Tests\TestCase;

/**
 * Frontend build-time configuration: the values a bundler must inline while the
 * image is being built, and the auth token that must never be visible.
 */
class BuildFrontendConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app[\Illuminate\Contracts\Console\Kernel::class]->registerCommand(
            $this->app->make(BuildProbeCommand::class)
        );

        BuildProbeCommand::$captured = null;
    }

    /**
     * @param  array<string, string>  $args
     * @param  array<string, string>  $secrets
     */
    protected function build(array $args = [], array $secrets = []): void
    {
        config()->set('sail.build.args', $args);
        config()->set('sail.build.secrets', $secrets);

        $this->artisan('sail-test:probe')->assertSuccessful();
    }

    public function test_an_unconfigured_project_builds_exactly_as_before(): void
    {
        $this->build();

        $command = BuildProbeCommand::bakeCommand();

        foreach (['VITE_SENTRY_DSN', 'VITE_SENTRY_RELEASE', 'SENTRY_ORG', 'SENTRY_PROJECT', 'SENTRY_AUTH_TOKEN'] as $name) {
            $this->assertStringNotContainsString($name, $command);
        }

        $this->assertSame([], BuildProbeCommand::env());
    }

    public function test_it_forwards_configured_args_to_the_bake_command(): void
    {
        $this->build([
            'VITE_SENTRY_DSN' => 'https://abc@o1.ingest.sentry.io/2',
            'VITE_SENTRY_RELEASE' => '9.9.9',
            'SENTRY_ORG' => 'reyemtech',
            'SENTRY_PROJECT' => 'gta-events-react',
        ]);

        $command = BuildProbeCommand::bakeCommand();

        $this->assertStringContainsString("VITE_SENTRY_DSN='https://abc@o1.ingest.sentry.io/2'", $command);
        $this->assertStringContainsString("VITE_SENTRY_RELEASE='9.9.9'", $command);
        $this->assertStringContainsString("SENTRY_ORG='reyemtech'", $command);
        $this->assertStringContainsString("SENTRY_PROJECT='gta-events-react'", $command);
    }

    public function test_lan_builds_allow_reading_the_sail_home_certificate_context(): void
    {
        config()->set('sail.network.mode', 'lan');
        putenv('SAIL_HOME=/tmp/sail-home-cert-context');

        try {
            $this->build();

            $this->assertStringContainsString(
                "--allow=fs.read='/tmp/sail-home-cert-context/certs'",
                BuildProbeCommand::bakeCommand()
            );
        } finally {
            putenv('SAIL_HOME');
        }
    }

    public function test_build_uses_a_php_compatible_alpine_default(): void
    {
        config()->set('sail.build.php_version', '8.5');
        config()->set('sail.build.alpine_version', null);

        $this->build();

        $command = BuildProbeCommand::bakeCommand();
        $this->assertStringContainsString("PHP_VERSION='8.5'", $command);
        $this->assertStringContainsString("ALPINE_VERSION='3.24'", $command);
    }

    public function test_the_auth_token_never_reaches_the_command_line(): void
    {
        $this->build(
            ['SENTRY_ORG' => 'reyemtech', 'SENTRY_PROJECT' => 'app'],
            ['SENTRY_AUTH_TOKEN' => 'sntrys_supersecret']
        );

        $this->assertStringNotContainsString('sntrys_supersecret', BuildProbeCommand::bakeCommand());
        $this->assertStringNotContainsString('SENTRY_AUTH_TOKEN', BuildProbeCommand::bakeCommand());

        // It still has to reach the build — through the process environment, which
        // is what the type=env secret in docker-bake.hcl reads.
        $this->assertSame(['SENTRY_AUTH_TOKEN' => 'sntrys_supersecret'], BuildProbeCommand::env());
    }

    public function test_the_auth_token_is_not_printed_to_the_terminal(): void
    {
        config()->set('sail.build.args', []);
        config()->set('sail.build.secrets', ['SENTRY_AUTH_TOKEN' => 'sntrys_supersecret']);

        $this->artisan('sail-test:probe')
            ->doesntExpectOutputToContain('sntrys_supersecret')
            ->assertSuccessful();
    }

    public function test_the_release_defaults_to_the_version_being_built(): void
    {
        config()->set('sail.build.version', '3.5.3');

        $this->build(['VITE_SENTRY_DSN' => 'https://abc@o1.ingest.sentry.io/2']);

        $this->assertStringContainsString("VITE_SENTRY_RELEASE='3.5.3'", BuildProbeCommand::bakeCommand());
    }

    public function test_an_explicit_release_wins_over_the_version(): void
    {
        config()->set('sail.build.version', '3.5.3');

        $this->build([
            'VITE_SENTRY_DSN' => 'https://abc@o1.ingest.sentry.io/2',
            'VITE_SENTRY_RELEASE' => 'nightly',
        ]);

        $this->assertStringContainsString("VITE_SENTRY_RELEASE='nightly'", BuildProbeCommand::bakeCommand());
        $this->assertStringNotContainsString("VITE_SENTRY_RELEASE='3.5.3'", BuildProbeCommand::bakeCommand());
    }

    public function test_no_release_is_added_without_a_dsn(): void
    {
        config()->set('sail.build.version', '3.5.3');

        $this->build(['SENTRY_ORG' => 'reyemtech']);

        $this->assertStringNotContainsString('VITE_SENTRY_RELEASE', BuildProbeCommand::bakeCommand());
    }

    public function test_sail_own_keys_survive_a_colliding_config_entry(): void
    {
        config()->set('sail.build.version', '3.5.3');

        $this->build(['VERSION' => 'hijacked', 'APP_NAME' => 'hijacked']);

        $command = BuildProbeCommand::bakeCommand();

        $this->assertStringNotContainsString('hijacked', $command);
        $this->assertStringContainsString("VERSION='3.5.3'", $command);
    }

    public function test_empty_and_non_scalar_values_are_dropped(): void
    {
        $this->build([
            'SENTRY_ORG' => '',
            'SENTRY_PROJECT' => null,
            'VITE_SENTRY_DSN' => ['not', 'scalar'],
        ]);

        $command = BuildProbeCommand::bakeCommand();

        $this->assertStringNotContainsString('SENTRY_ORG', $command);
        $this->assertStringNotContainsString('SENTRY_PROJECT', $command);
        $this->assertStringNotContainsString('VITE_SENTRY_DSN', $command);
    }

    public function test_values_are_shell_quoted(): void
    {
        $this->build(['VITE_SENTRY_RELEASE' => 'release with spaces; rm -rf /']);

        $this->assertStringContainsString(
            "VITE_SENTRY_RELEASE='release with spaces; rm -rf /'",
            BuildProbeCommand::bakeCommand()
        );
    }

    public function test_it_warns_when_the_token_has_no_org_or_project(): void
    {
        config()->set('sail.build.args', []);
        config()->set('sail.build.secrets', ['SENTRY_AUTH_TOKEN' => 'sntrys_supersecret']);

        $this->artisan('sail-test:probe')
            ->expectsOutputToContain('SENTRY_ORG and SENTRY_PROJECT')
            ->assertSuccessful();
    }

    public function test_it_does_not_warn_when_org_and_project_are_present(): void
    {
        config()->set('sail.build.args', ['SENTRY_ORG' => 'reyemtech', 'SENTRY_PROJECT' => 'app']);
        config()->set('sail.build.secrets', ['SENTRY_AUTH_TOKEN' => 'sntrys_supersecret']);

        $this->artisan('sail-test:probe')
            ->doesntExpectOutputToContain('Sourcemap upload will fail')
            ->assertSuccessful();
    }

    public function test_the_shipped_config_reads_the_documented_env_variables(): void
    {
        $config = require __DIR__.'/../../config/sail.php';

        $this->assertArrayHasKey('args', $config['build']);
        $this->assertArrayHasKey('secrets', $config['build']);

        // Unset environment => nothing forwarded, which is what keeps existing
        // projects building unchanged.
        $this->assertSame([], $config['build']['args']);
        $this->assertSame([], $config['build']['secrets']);
    }

    public function test_the_shipped_config_picks_up_the_process_environment(): void
    {
        // This is what makes one mechanism serve both local .env files and CI
        // runners, which expose variables through the process environment only.
        putenv('VITE_SENTRY_DSN=https://ci@o1.ingest.sentry.io/2');
        putenv('SENTRY_AUTH_TOKEN=sntrys_from_ci');

        try {
            $config = require __DIR__.'/../../config/sail.php';

            $this->assertSame('https://ci@o1.ingest.sentry.io/2', $config['build']['args']['VITE_SENTRY_DSN']);
            $this->assertSame('sntrys_from_ci', $config['build']['secrets']['SENTRY_AUTH_TOKEN']);
        } finally {
            putenv('VITE_SENTRY_DSN');
            putenv('SENTRY_AUTH_TOKEN');
        }
    }
}
