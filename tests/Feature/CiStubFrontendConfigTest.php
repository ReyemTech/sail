<?php

namespace Laravel\Sail\Tests\Feature;

use Laravel\Sail\Tests\TestCase;

/**
 * Every generated pipeline must carry the frontend build-time wiring, otherwise the
 * values exist locally and vanish in CI — which is the failure this feature exists
 * to prevent. The GitHub Actions stub matters most: it calls `docker buildx bake`
 * directly and never goes through sail:build, so nothing else can supply them.
 */
class CiStubFrontendConfigTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function stubProvider(): array
    {
        return [
            'github-actions' => ['github-actions-build.yml.stub'],
            'gitlab-ci' => ['gitlab-ci.yml.stub'],
            'azure-pipelines' => ['azure-pipelines.yml.stub'],
            'circleci' => ['circleci-config.yml.stub'],
            'aws-codebuild' => ['buildspec.yml.stub'],
            'travis-ci' => ['travis-ci.yml.stub'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stubProvider')]
    public function test_the_stub_wires_every_frontend_build_variable(string $stub): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/ci/'.$stub);

        $this->assertNotFalse($contents, "stubs/ci/{$stub} must exist");

        foreach (['VITE_SENTRY_DSN', 'VITE_SENTRY_RELEASE', 'SENTRY_ORG', 'SENTRY_PROJECT', 'SENTRY_AUTH_TOKEN'] as $name) {
            $this->assertStringContainsString($name, $contents, "stubs/ci/{$stub} must wire {$name}");
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('stubProvider')]
    public function test_the_stub_never_hardcodes_a_token(string $stub): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/ci/'.$stub);

        $this->assertDoesNotMatchRegularExpression(
            '/SENTRY_AUTH_TOKEN\s*[:=]\s*["\']?sntry/i',
            $contents,
            "stubs/ci/{$stub} must read the token from the provider, never inline it"
        );
    }

    public function test_the_github_stub_binds_the_release_to_the_released_version(): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/ci/github-actions-build.yml.stub');

        // This stub bypasses sail:build, so Sail's version-derived default for the
        // release name cannot apply — the workflow has to supply it.
        $this->assertStringContainsString(
            'VITE_SENTRY_RELEASE: ${{ needs.release-please.outputs.version }}',
            $contents
        );
    }

    public function test_the_github_stub_reads_the_token_from_secrets_not_vars(): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/ci/github-actions-build.yml.stub');

        $this->assertStringContainsString('SENTRY_AUTH_TOKEN: ${{ secrets.SENTRY_AUTH_TOKEN }}', $contents);
        $this->assertStringNotContainsString('vars.SENTRY_AUTH_TOKEN', $contents);
    }

    public function test_the_azure_stub_defines_defaults_for_its_macro_references(): void
    {
        $contents = file_get_contents(__DIR__.'/../../stubs/ci/azure-pipelines.yml.stub');

        // Azure leaves an undefined $(NAME) as literal text, which would be
        // forwarded to the build as a bogus value. Empty defaults prevent that.
        foreach (['VITE_SENTRY_DSN', 'SENTRY_ORG', 'SENTRY_PROJECT', 'SENTRY_AUTH_TOKEN'] as $name) {
            $this->assertMatchesRegularExpression(
                '/^  '.$name.": ''$/m",
                $contents,
                "azure-pipelines stub must default {$name} to empty"
            );
        }
    }
}
