<?php

namespace Laravel\Sail\Console\Concerns;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Sail\Networking\SailHome;
use MirazMac\DotEnv\Writer;
use Symfony\Component\Process\Process;

trait InteractsWithDocker
{
    use InteractsWithDockerPrompts;
    use InteractsWithDockerRegistry;

    /**
     * The available enviroments that may be interacted with.
     *
     * @var array<string>
     */
    protected $environments = [
        'local',
        'production',
    ];

    /**
     * The available architectures that may be interacted with.
     *
     * @var array<string>
     */
    protected $archs = [
        'linux/amd64',
        'linux/arm64',
        'linux/arm/v7',
        'linux/arm/v6',
        'linux/ppc64le',
        'linux/s390x',
    ];

    /**
     * The default platforms to be used.
     *
     * @var array<string>
     */
    protected $defaultArchs = [
        'linux/amd64',
        'linux/arm64',
    ];

    /**
     * The default enviroments to interacted with.
     *
     * @var array<string>
     */
    protected $defaultEnvs = [
        'production',
    ];

    /**
     * The available repositories that may be interacted with.
     *
     * @var array<string>
     */
    protected $repositories = [
        'ghcr.io' => 'GitHub Container Registry',
        'registry.gitlab.com' => 'GitLab Container Registry',
        'azurecr' => 'Azure Container Registry',
        'docker.io' => 'Docker Hub',
        'gcr' => 'Google Container Registry',
        'ecr' => 'Amazon Elastic Container Registry',
        'quay.io' => 'Quay.io',
        'harbor' => 'Harbor',
        'none' => 'None - Local Build',
    ];

    /**
     * Indicates if the repository should be used.
     */
    protected bool $useRepository = false;

    /**
     * Indicates if the images should be pushed to the repository.
     */
    protected bool $push = false;

    /**
     * Indicates if the configuration should be reused.
     */
    protected bool $reuseConfig = false;

    /**
     * Indicates if CLI validation failed.
     */
    protected bool $validationFailed = false;

    /**
     * The name of the organization to be used.
     */
    protected ?string $organization = null;

    /**
     * The domains to be used for deployment.
     *
     * @var array<string>|null
     */
    protected ?array $deploymentDomains = null;

    /**
     * Indicates if node_modules/ should be removed from the final image.
     */
    protected ?bool $removeNodeModules = null;

    /**
     * Build the Docker images.
     *
     * @return int Exit code (0 for success, non-zero for failure)
     */
    protected function buildDockerImages(string $environment, array $archs, string $repository): int
    {
        // Check if login is required before building/pushing
        if ($this->useRepository && $this->push && $repository !== 'none') {
            $this->output->writeln('');
            $this->output->write('  <fg=blue>Checking registry authentication...</>');

            if (! $this->checkRegistryLogin($repository)) {
                $this->output->writeln(' <fg=yellow>Not logged in</>');
                $this->output->writeln('');
                $this->components->warn('⚠️  Not logged in to registry: '.$repository);
                if (! $this->loginToRegistry($repository)) {
                    $this->output->writeln('');
                    $this->components->error('Failed to login to registry. Build aborted.');
                    $this->output->writeln('');

                    return 1;
                }
            } else {
                $this->output->writeln(' <fg=green>✓ Logged in</>');
            }
        }

        $this->output->writeln('');
        $this->components->info('🐳 Building Docker Images...');
        $this->output->writeln(' <fg=blue>=> Environment:</> '.$environment);
        $this->output->writeln(' <fg=blue>=> Architectures:</>');
        foreach ($archs as $arch) {
            $this->output->writeln('    <fg=green>-</> '.$arch);
        }
        $this->output->writeln('');

        // $commands = $this->buildCommands($archs, $environment, $repository);

        $removeVendorNodeModules = $this->removeNodeModules ?? config('sail.build.remove_node_modules', true);

        $args = [
            'ARCHS' => $archs,
            'PUSH' => $this->push,
            'APP_NAME' => Str::slug(Config('app.name')),
            'VERSION' => config('sail.build.version', '1.0.0'),
            'APP_DIR' => realpath('.'),
            'RUNTIME_DIR' => realpath(InstalledVersions::getInstallPath('reyemtech/sail').'/runtimes/8.x'),
            'ORG' => $this->organization,
            'REMOVE_NODE_MODULES' => $removeVendorNodeModules ? 'true' : 'false',
            'PHP_VERSION' => $this->resolveBuildPhpVersion(),
        ];

        $args['ALPINE_VERSION'] = (string) (config('sail.build.alpine_version')
            ?: (version_compare($args['PHP_VERSION'], '8.5', '>=') ? '3.24' : '3.21'));

        if (config('sail.network.mode') === 'lan') {
            $args['CERTS_DIR'] = (new SailHome)->certsDir();
        }

        if ($this->useRepository) {
            $args['REGISTRY'] = $repository;
        }

        // Frontend build-time configuration is merged underneath Sail's own keys, so
        // a project that happens to configure a colliding name cannot clobber
        // VERSION, ARCHS or any other value Sail derives itself.
        $args = array_merge($this->frontendBuildArgs($args['VERSION']), $args);

        $secrets = $this->frontendBuildSecrets();

        $this->warnAboutIncompleteSentryConfig($args, $secrets);

        $commands = [];
        $path = realpath(InstalledVersions::getInstallPath('reyemtech/sail'));
        if (! is_dir("{$path}/certs")) {
            $commands[] = "{$path}/bin/sail-setup";
        }
        $commands[] = $this->createBakeCommand($args);

        return $this->runCommands($commands, $secrets);
    }

    protected function resolveBuildPhpVersion(): string
    {
        $configured = config('sail.build.php_version');
        if (is_scalar($configured) && $configured !== '') {
            return $this->majorMinorPhpVersion((string) $configured) ?? '8.4';
        }

        $composer = $this->composerPlatformVersion(base_path('composer.json'), 'config.platform.php');
        if ($composer !== null) {
            return $composer;
        }

        return $this->composerPlatformVersion(base_path('composer.lock'), 'platform-overrides.php') ?? '8.4';
    }

    protected function composerPlatformVersion(string $path, string $key): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return null;
        }

        $value = data_get($data, $key);

        return is_string($value) ? $this->majorMinorPhpVersion($value) : null;
    }

    protected function majorMinorPhpVersion(string $version): ?string
    {
        return preg_match('/^(\d+\.\d+)(?:\.\d+)?$/', $version, $matches) ? $matches[1] : null;
    }

    /**
     * The frontend build-time arguments forwarded to the asset build.
     *
     * @param  string|null  $version  The version being built, used as the default Sentry release.
     * @return array<string, string>
     */
    protected function frontendBuildArgs($version = null): array
    {
        $args = $this->nonEmptyConfigValues('sail.build.args');

        // A release name is only meaningful once a DSN is configured. Defaulting it
        // to the version being built keeps the Sentry release in step with the image
        // tag, and picks up --build-version and --bump, which a config-time env()
        // read could not see.
        if (isset($args['VITE_SENTRY_DSN']) && ! isset($args['VITE_SENTRY_RELEASE']) && ! empty($version)) {
            $args['VITE_SENTRY_RELEASE'] = (string) $version;
        }

        return $args;
    }

    /**
     * The build-time secrets handed to the build process through its environment.
     *
     * @return array<string, string>
     */
    protected function frontendBuildSecrets(): array
    {
        return $this->nonEmptyConfigValues('sail.build.secrets');
    }

    /**
     * Read a config map, discarding anything that is not a non-empty scalar.
     *
     * Values reach the build as environment variables, so anything that cannot be
     * expressed as a string is dropped rather than coerced into something odd.
     *
     * @return array<string, string>
     */
    protected function nonEmptyConfigValues(string $key): array
    {
        $values = config($key, []);

        if (! is_array($values)) {
            return [];
        }

        $result = [];

        foreach ($values as $name => $value) {
            if (! is_string($name) || ! is_scalar($value)) {
                continue;
            }

            $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

            if ($value !== '') {
                $result[$name] = $value;
            }
        }

        return $result;
    }

    /**
     * Warn when a Sentry auth token is present without the org and project it needs.
     *
     * The Sentry bundler plugin activates on the token alone and then fails the
     * upload with an opaque error if it cannot resolve an org or project. This is
     * only a warning: the project's own bundler config may supply both.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, string>  $secrets
     */
    protected function warnAboutIncompleteSentryConfig(array $args, array $secrets): void
    {
        if (! isset($secrets['SENTRY_AUTH_TOKEN'])) {
            return;
        }

        $missing = array_values(array_filter(
            ['SENTRY_ORG', 'SENTRY_PROJECT'],
            fn ($key) => ! isset($args[$key])
        ));

        if ($missing === []) {
            return;
        }

        $this->components->warn(
            '⚠️  SENTRY_AUTH_TOKEN is set but '.implode(' and ', $missing).
            ' '.(count($missing) === 1 ? 'is' : 'are').' not. Sourcemap upload will fail '.
            'unless your bundler config supplies '.(count($missing) === 1 ? 'it' : 'them').'.'
        );
    }

    protected function createBakeCommand(array $args)
    {
        $bakeCommand = '';
        foreach ($args as $key => $value) {
            if (is_array($value)) {
                $value = implode(',', $value);
            } elseif (is_bool($value)) {
                // HCL requires "true" or "false" as strings for boolean variables
                $value = $value ? 'true' : 'false';
            }

            // Values are configurable (a Sentry DSN, a release name derived from git),
            // so they are quoted rather than trusted to be shell-safe.
            $bakeCommand .= ' '.$key.'='.escapeshellarg((string) $value);
        }

        $bakeCommand .= ' docker buildx bake ';
        if (isset($args['CERTS_DIR'])) {
            $bakeCommand .= ' --allow=fs.read='.escapeshellarg($args['CERTS_DIR']);
        }
        $bakeCommand .= ' -f '.realpath(InstalledVersions::getInstallPath('reyemtech/sail').'/runtimes/8.x/docker-bake.hcl');

        // $bakeCommand .= ' --print ';

        $bakeCommand .= $this->useRepository ? ' default' : ' app';

        $this->output->writeln(' <fg=blue>=> Build Command:</>');
        $this->output->writeln('    <fg=green>→</> '.$bakeCommand);
        $this->output->writeln('');
        $this->components->warn('⏳ This may take several minutes. Building in progress...');
        $this->output->writeln('');

        return $bakeCommand;
    }

    /**
     * Build the Docker images.
     *
     * @param  string  $environment
     * @param  array  $archs
     * @param  string  $repository
     * @return array
     */
    protected function buildCommands($archs, $environment, $repository)
    {
        $path = InstalledVersions::getInstallPath('reyemtech/sail');
        $dockerpath = $path.'/runtimes/8.x';

        if (! is_dir("{$path}/certs")) {
            $cmds[] = "{$path}/bin/sail-setup";
        }

        $baseCmd = "docker buildx build --build-context mainapp=. --build-context runtime={$dockerpath} -f {$dockerpath}/Dockerfile";
        $platformCmd = $baseCmd.' --platform '.implode(',', $archs);

        if ($this->push) {
            $baseCmd .= ' --push ';
        }

        if ($environment === 'production') {
            if ($this->push) {
                $platformCmd .= ' --push ';
            }
            $cmds[] = $platformCmd.' --target production '.$this->buildTags('web', $environment, $repository).' .';
            $cmds[] = $platformCmd.' --target worker'.$this->buildTags('worker', $environment, $repository).' .';
            if (! $this->push) {
                $cmds[] = "{$baseCmd} --target app --load ".$this->buildTags('web', $environment, $repository).' .';
                $cmds[] = "{$baseCmd} --target worker --load ".$this->buildTags('worker', $environment, $repository).' .';
            }
        } else {
            $cmds[] = $platformCmd.' --target app --load '.$this->buildTags('local', $environment, $repository).' .';
        }

        $this->output->writeln(' <fg=blue>=> Commands:</>');
        foreach ($cmds as $command) {
            $this->output->writeln('    <fg=green>-</> '.$command);
        }

        return $cmds;
    }

    protected function getConfig(bool $forceReuse = false)
    {
        $config = config('sail.build');
        if ($config['environments']) {
            $config['environments'] = explode(',', $config['environments']);
            $config['architectures'] = explode(',', $config['architectures']);
            $this->deploymentDomains = explode(',', config('sail.deploy.domains'));
            $this->output->writeln('');
            $this->output->writeln('<info>Previous build configuration found</info>');
            $this->output->writeln('');
            $this->output->writeln('<fg=yellow>==></> <fg=green>Environments:</>');
            foreach ($config['environments'] as $environment) {
                $this->output->writeln('    <fg=green>-</> '.$environment);
            }
            $this->output->writeln('<fg=yellow>==></> <fg=green>Architectures:</>');
            foreach ($config['architectures'] as $arch) {
                $this->output->writeln('    <fg=green>-</> '.$arch);
            }
            $this->output->writeln('<fg=yellow>==></> <fg=green>Domains:</>');
            foreach ($this->deploymentDomains as $domain) {
                $this->output->writeln('    <fg=green>-</> '.$domain);
            }
            $this->output->writeln('<fg=yellow>==></> <fg=green>Repository:</> '.$config['repository']);
            $this->output->writeln('<fg=yellow>==></> <fg=green>Organization:</> '.$config['organization']);
            $this->output->writeln('<fg=yellow>==></> <fg=green>Push:</> '.($config['push'] ? '<bg=green;fg-black> true </>' : '<bg=red;fg=black> false </>'));
            $this->output->writeln('<fg=yellow>==></> <fg=green>Version:</> '.$config['version']);
            $this->output->writeln('<fg=yellow>==></> <fg=green>Remove node_modules:</> '.($config['remove_node_modules'] ?? true ? '<bg=green;fg-black> true </>' : '<bg=red;fg=black> false </>'));

            if ($config['repository'] && $config['repository'] !== 'none') {
                $this->useRepository = true;
            }

            if ($config['push']) {
                $this->push = true;
            }

            if ($config['organization']) {
                $this->organization = $config['organization'];
            }

            if (isset($config['remove_node_modules'])) {
                $this->removeNodeModules = (bool) $config['remove_node_modules'];
            }

            $this->reuseConfig = $forceReuse ? true : $this->reuseConfig;
            if (! $forceReuse) {
                if (function_exists('\Laravel\Prompts\confirm')) {
                    $this->reuseConfig = \Laravel\Prompts\confirm(
                        label: 'Would you like to build this configuration again?',
                        default: true,
                    );
                } else {
                    $this->reuseConfig = $this->confirm('Would you like to build this configuration again?', true);
                }
            }

            if ($this->reuseConfig) {
                $config['version'] = $this->getVersionChoice();
            }
        }

        return $this->reuseConfig ? $config : null;
    }

    protected function getVersionChoice()
    {
        $options = ['patch', 'minor', 'major', 'no'];
        $version = config('sail.build.version');

        if ($version === null) {
            return;
        }

        if (function_exists('\Laravel\Prompts\confirm')) {
            $increment = \Laravel\Prompts\select(
                label: 'Increment version?',
                options: $options,
                default: 'no',
            );
        } else {
            $increment = $this->choice('Increment version?', $options, 0, null, false);
        }

        if ($increment !== 'no') {
            $version = $this->bumpVersion($version, $increment);
            $this->output->writeln('<fg=yellow>==></> <fg=green>New Version:</> '.$version);
            $this->output->writeln('');
        }

        return $version;
    }

    /**
     * Bump the version number.
     */
    public function bumpVersion(string $version, string $type = 'patch'): string
    {
        [$major, $minor, $patch] = explode('.', $version);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $new = "{$major}.{$minor}.{$patch}";

        $writer = new Writer(base_path('.env'));
        $writer->set('SAIL_BUILD_VERSION', $new);
        $writer->write();

        Config::set('sail.build.version', $new);

        return $new;
    }

    protected function writeConfig($environments, $architectures, $repository)
    {
        $removeVendorNodeModules = $this->removeNodeModules ?? config('sail.build.remove_node_modules', true);

        $config = [];
        $config['environments'] = implode(',', $environments);
        $config['architectures'] = implode(',', $architectures);
        $config['repository'] = $repository;
        $config['push'] = $this->push;
        $config['organization'] = $this->organization;
        $config['version'] = config('sail.build.version', '1.0.0');
        $config['remove_node_modules'] = $removeVendorNodeModules;

        $writer = new Writer(base_path('.env'));
        $writer->set('SAIL_BUILD_ENVIRONMENT', $config['environments'] ?? '');
        $writer->set('SAIL_BUILD_ARCHITECTURES', $config['architectures'] ?? '');
        $writer->set('SAIL_BUILD_REPOSITORY', $config['repository'] ?? '');
        $writer->set('SAIL_BUILD_PUSH', $config['push'] ?? 'false');
        $writer->set('SAIL_BUILD_ORGANIZATION', $config['organization'] ?? '');
        $writer->set('SAIL_BUILD_VERSION', $config['version'] ?? '1.0.0');
        $writer->set('SAIL_BUILD_REMOVE_NODE_MODULES', $config['remove_node_modules'] ? 'true' : 'false');
        $writer->set('SAIL_DEPLOY_DOMAINS', implode(',', $this->deploymentDomains) ?? '');
        $writer->set('VITE_DEV_SERVER_URL', 'https://'.config('sail.domain').'/vite');
        $writer->write();

        Config::set('sail.build.environments', $config['environments'] ?? '');
        Config::set('sail.build.architectures', $config['architectures'] ?? '');
        Config::set('sail.build.repository', $config['repository']) ?? '';
        Config::set('sail.build.push', $config['push'] ? true : false);
        Config::set('sail.build.organization', $config['organization'] ?? '');
        Config::set('sail.build.version', $config['version'] ?? '1.0.0');
        Config::set('sail.build.remove_node_modules', $config['remove_node_modules']);
        Config::set('sail.deploy.domains', implode(',', $this->deploymentDomains) ?? '');
    }

    /**
     * Build the Docker tags.
     *
     * @param  string  $name
     * @param  string  $environment
     * @param  string  $repository
     * @return string
     */
    protected function buildTags($name, $environment, $repository)
    {
        $version = config('sail.build.version', '1.0.0');

        $tag = $this->useRepository && $repository !== 'none' ? $repository.'/' : '';
        $tag .= $this->useRepository && $repository !== 'none' && $this->organization ? $this->organization.'/' : '';
        $tag .= Str::slug(Config('app.name')).'-'.$name;

        $tags[] = "$tag:$environment";
        if ($environment === 'production') {
            $tags[] = "$tag:latest";
            $tags[] = "$tag:$version";
        }

        return ' --tag '.implode(' --tag ', $tags);
    }

    /**
     * Run the given commands.
     *
     * Values in $env are merged over the inherited environment by Symfony, never
     * replacing it. This is how build secrets reach the build without appearing in
     * the command string that is echoed to the terminal and to CI logs.
     *
     * @param  array  $commands
     * @param  array<string, string>  $env
     * @return int
     */
    protected function runCommands($commands, array $env = [])
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, $env ?: null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        $startTime = time();
        $lastProgress = 0;

        $exitCode = $process->run(function ($type, $line) use (&$lastProgress, $startTime) {
            $elapsed = time() - $startTime;

            // Show progress indicator every 5 seconds
            if ($elapsed - $lastProgress >= 5) {
                $minutes = floor($elapsed / 60);
                $seconds = $elapsed % 60;
                $this->output->write(sprintf("\r  <fg=cyan>⏳ Building... (%dm %ds)</>", $minutes, $seconds));
                $lastProgress = $elapsed;
            }

            // Show important build output (build steps and actual errors)
            if (str_starts_with(trim($line), '#') || $this->isDockerBuildError($line)) {
                $this->output->writeln('');
                $this->output->write('    '.$line);
            }
        });

        // The process exit code is the definitive signal for build success/failure
        return $exitCode;
    }

    /**
     * Determine if a build output line contains an actual Docker/buildx error.
     */
    protected function isDockerBuildError(string $line): bool
    {
        $trimmed = trim($line);

        // Match lines that start with "ERROR" (Docker buildx error prefix)
        if (preg_match('/^ERROR\b/i', $trimmed)) {
            return true;
        }

        // Match specific Docker buildx failure patterns
        $patterns = [
            'failed to solve',
            'failed to parse',
            'failed to compute',
            'failed to create',
            'failed to connect',
            'Invalid value',
        ];

        foreach ($patterns as $pattern) {
            if (stripos($trimmed, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
