<?php

namespace Laravel\Sail\Tests\Feature;

use Illuminate\Support\Facades\File;
use Laravel\Sail\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Regression tests for https://github.com/ReyemTech/sail/issues/20 —
 * sail:helm regeneration must never destroy a consumer-owned values.yaml:
 * committed values (global.tag) win, YAML comments survive (including the
 * `# x-release-please-version` marker), and opinionated stub defaults are
 * not injected into maps the consumer already owns.
 */
class HelmValuesMergeTest extends TestCase
{
    protected string $testBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testBasePath = sys_get_temp_dir().'/sail-helm-values-test-'.uniqid();
        File::makeDirectory($this->testBasePath, 0755, true);
        $this->app->setBasePath($this->testBasePath);
        chdir($this->testBasePath);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBasePath)) {
            File::deleteDirectory($this->testBasePath);
        }

        parent::tearDown();
    }

    protected function valuesPath(): string
    {
        return $this->testBasePath.'/helm/values.yaml';
    }

    /**
     * Write a consumer-owned Helm chart mirroring the issue #20 report:
     * a release-managed tag with the release-please marker comment, a
     * multi-line operational comment block, and customized ingress
     * annotations without the stub's certresolver default.
     */
    protected function writeConsumerChart(): void
    {
        File::makeDirectory($this->testBasePath.'/helm', 0755, true);

        File::put($this->testBasePath.'/helm/Chart.yaml', <<<'YAML'
apiVersion: v2
name: consumerapp
description: consumerapp Helm Chart
type: application
version: 2.35.1
appVersion: 2.35.1
YAML);

        File::put($this->valuesPath(), <<<'YAML'
# ------------------------------------------------------------------------------
# Consumer-managed Helm values. The tag below is bumped by release automation.
# ------------------------------------------------------------------------------
global:
  tag: 2.35.1 # x-release-please-version
  pullPolicy: IfNotPresent
  imagePullSecret: ghcr-auth
name: consumerapp
web:
  enabled: true
  replicaCount: 2
  image:
    repository: ghcr.io/consumer/consumerapp-web
worker:
  enabled: true
  horizon: true
  replicaCount: 1
  image:
    repository: ghcr.io/consumer/consumerapp-worker
secret:
  enabled: true
  path: secret/laravel/consumerapp
  store: vault-backend
logging:
  mode: both
resources:
  requests:
    cpu: 100m
    memory: 256Mi
  limits:
    cpu: "2"
    memory: 1Gi
ingress:
  enabled: true
  className: "traefik"
  annotations:
    "kubernetes.io/ingress.class": "traefik"
    "traefik.ingress.kubernetes.io/router.entrypoints": "websecure"
  hosts:
    - host: consumerapp.example.com
      paths:
        - path: /
          pathType: Prefix
  tls: []
# Snapshot verification is disabled until the backup job is fixed.
# Do not enable without checking OPS-1234 first.
verifySnapshots:
  enabled: false
YAML);
    }

    protected function regenerate(bool $noVersionUpdate = true): void
    {
        $this->artisan('sail:helm', $noVersionUpdate ? ['--no-version-update' => true] : [])
            ->assertExitCode(0);
    }

    public function test_no_version_update_preserves_committed_tag_and_marker(): void
    {
        $this->writeConsumerChart();
        $this->regenerate();

        $text = File::get($this->valuesPath());
        $values = Yaml::parse($text);

        $this->assertSame('2.35.1', (string) $values['global']['tag'], 'global.tag must not be reset to the local build version');
        $this->assertStringContainsString('tag: 2.35.1 # x-release-please-version', $text, 'the release-please marker comment must survive regeneration');
    }

    public function test_comments_survive_regeneration(): void
    {
        $this->writeConsumerChart();
        $this->regenerate();

        $text = File::get($this->valuesPath());

        $this->assertStringContainsString('# Consumer-managed Helm values. The tag below is bumped by release automation.', $text);
        $this->assertStringContainsString('# Snapshot verification is disabled until the backup job is fixed.', $text);
        $this->assertStringContainsString('# Do not enable without checking OPS-1234 first.', $text);
    }

    public function test_stub_defaults_are_not_injected_into_consumer_owned_annotations(): void
    {
        $this->writeConsumerChart();
        $this->regenerate();

        $text = File::get($this->valuesPath());
        $values = Yaml::parse($text);

        $this->assertStringNotContainsString('certresolver', $text, 'opinionated ingress annotation defaults must not be injected');
        $this->assertArrayNotHasKey('traefik.ingress.kubernetes.io/router.tls.certresolver', $values['ingress']['annotations']);
        $this->assertCount(2, $values['ingress']['annotations'], 'consumer-owned annotations map must be left untouched');
    }

    public function test_missing_stub_keys_are_added_without_touching_existing_values(): void
    {
        $this->writeConsumerChart();
        $this->regenerate();

        $values = Yaml::parse(File::get($this->valuesPath()));

        // Missing top-level block appended from the stub.
        $this->assertTrue($values['scheduler']['enabled'] ?? false, 'missing top-level stub keys must still be merged in');

        // Missing nested key inserted into an existing block.
        $this->assertArrayHasKey('maxArchives', $values['logging'], 'missing nested stub keys must be inserted into existing blocks');

        // Existing values in the same block win.
        $this->assertSame('both', $values['logging']['mode']);
        $this->assertSame(2, $values['web']['replicaCount']);
        $this->assertSame('2', (string) $values['resources']['limits']['cpu']);
    }

    public function test_existing_values_win_over_local_config(): void
    {
        $this->writeConsumerChart();
        $this->regenerate();

        $values = Yaml::parse(File::get($this->valuesPath()));

        $this->assertSame('consumerapp', $values['name']);
        $this->assertSame('ghcr.io/consumer/consumerapp-web', $values['web']['image']['repository']);
        $this->assertSame('ghcr.io/consumer/consumerapp-worker', $values['worker']['image']['repository']);
        $this->assertSame('consumerapp.example.com', $values['ingress']['hosts'][0]['host']);
        $this->assertSame('secret/laravel/consumerapp', $values['secret']['path']);
    }

    public function test_regeneration_is_idempotent(): void
    {
        $this->writeConsumerChart();

        $this->regenerate();
        $firstPass = File::get($this->valuesPath());

        $this->regenerate();
        $secondPass = File::get($this->valuesPath());

        $this->assertSame($firstPass, $secondPass, 'a second regeneration must not modify values.yaml again');
    }

    public function test_version_update_rewrites_tag_but_keeps_marker_comment(): void
    {
        $this->writeConsumerChart();
        $this->regenerate(noVersionUpdate: false);

        $text = File::get($this->valuesPath());
        $values = Yaml::parse($text);

        $this->assertSame('1.0.0', (string) $values['global']['tag'], 'an explicit version update must set global.tag');
        $this->assertStringContainsString('tag: 1.0.0 # x-release-please-version', $text, 'the release-please marker comment must survive a tag update');
    }

    public function test_fresh_values_file_is_fully_generated(): void
    {
        $this->regenerate(noVersionUpdate: false);

        $this->assertFileExists($this->valuesPath());
        $values = Yaml::parse(File::get($this->valuesPath()));

        $this->assertSame('TestApp', $values['name']);
        $this->assertSame('1.0.0', (string) $values['global']['tag']);
        $this->assertSame('test.local', $values['ingress']['hosts'][0]['host']);
    }

    public function test_merged_file_remains_valid_yaml(): void
    {
        $this->writeConsumerChart();
        $this->regenerate();

        $values = Yaml::parse(File::get($this->valuesPath()));

        $this->assertIsArray($values);
        $this->assertSame('2.35.1', (string) $values['global']['tag']);
    }
}
