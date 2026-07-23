<?php

namespace Laravel\Sail\Console\Concerns;

use Laravel\Sail\Networking\HostIpDetector;
use Laravel\Sail\Networking\HostRegistry;
use Laravel\Sail\Networking\LanEnvironment;
use Laravel\Sail\Networking\SailHome;
use Laravel\Sail\Networking\SharedProxyStack;
use MirazMac\DotEnv\Writer;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithDockerComposeServices
{
    /**
     * Possible names for the compose file according to the spec.
     *
     * @var array<string>
     */
    protected $composePaths = [
        'compose.yaml',
        'compose.yml',
        'docker-compose.yaml',
        'docker-compose.yml',
    ];

    /**
     * The available services that may be installed.
     *
     * @var array<string>
     */
    protected $services = [
        'mysql',
        'pgsql',
        'mariadb',
        'mongodb',
        'redis',
        'valkey',
        'memcached',
        'meilisearch',
        'typesense',
        'minio',
        'rustfs',
        'mailpit',
        'rabbitmq',
        'selenium',
        'soketi',
        'gotenberg',
    ];

    /**
     * The default services used when the user chooses non-interactive mode.
     *
     * @var string[]
     */
    protected $defaultServices = ['mysql', 'redis', 'minio', 'mailpit'];

    /**
     * Gather the desired Sail services using an interactive prompt.
     *
     * @return array
     */
    protected function gatherServicesInteractively()
    {
        $services = array_unique(array_merge($this->defaultServices, $this->services));

        sort($services);
        if (function_exists('\Laravel\Prompts\multiselect')) {
            return \Laravel\Prompts\multiselect(
                label: 'Which services would you like to install?',
                options: $services,
                default: $this->defaultServices,
                scroll: sizeof($services) > 20 ? 15 : sizeof($services),
            );
        }

        return $this->choice('Which services would you like to install?', $services, 0, null, true);
    }

    /**
     * Get the project name to be used for the container.
     *
     * @return string
     */
    protected function getProjectName()
    {
        $directoryName = basename(getcwd());
        if (function_exists('\Laravel\Prompts\text')) {
            return \Laravel\Prompts\text(
                label: 'What is the name of the Project?',
                default: $directoryName,
                required: true,
            );
        }
        return $this->ask(
            'What is the name of the Project?',
            $directoryName
        );
    }

    /**
     * Resolve the project name without prompting: prefer the SAIL_PROJECT
     * entry already in .env, else the current directory name.
     */
    protected function resolveProjectName(): string
    {
        $envPath = base_path('.env');

        if (is_file($envPath) && preg_match('/^SAIL_PROJECT=(.*)$/m', file_get_contents($envPath), $m)) {
            $value = trim($m[1], " \"'");
            if ($value !== '') {
                return $value;
            }
        }

        return basename(getcwd());
    }

    /**
     * Get the domain name to be used for the container.
     *
     * @param  string  $project
     * @return string
     */
    protected function getDomainName($project)
    {
        if (function_exists('\Laravel\Prompts\text')) {
            return \Laravel\Prompts\text(
                label: 'What domain should be used for the container?',
                default: $project . '.test',
                required: true,
            );
        }
        return $this->ask(
            'What domain should be used for the container?',
            $project . '.test'
        );
    }

    /**
     * Get the IP address to be used for the container.
     *
     * @return string
     */
    protected function getIpAddress()
    {
        if (function_exists('\Laravel\Prompts\text')) {
            return \Laravel\Prompts\text(
                label: 'What IP Address should be used for the container?',
                default: '172.20.0.10',
                required: true,
            );
        }
        return $this->ask(
            'What IP Address should be used for the container?',
            '172.20.0.10'
        );
    }

    /**
     * Write the project environment variables to the .env file.
     *
     * @param  string  $project
     * @param  string  $ip
     * @return bool
     */
    protected function writePorjectEnv(string $project, string $ip, string $domain): bool
    {
        $subnet = substr($ip, 0, strrpos($ip, '.')) . '.0/24';

        $writer = new Writer(base_path('.env'));
        $writer->set('SAIL_IP', $ip);
        $writer->set('SAIL_BIND_IP', $ip);
        $writer->set('SAIL_SUBNET', $subnet);
        $writer->set('SAIL_PROJECT', $project);
        // $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $writer->set('SAIL_DOMAIN', $domain);
        $writer->set('APP_URL', 'https://' . $domain);
        $writer->set('WWWGROUP', '1000');
        $writer->set('WWWUSER', '1000');
        $writer->set('PHP_VERSION', $this->option('php'));

        return $writer->write();
    }

    /**
     * Ensure the .env defines SAIL_BIND_IP, seeding it from an existing
     * SAIL_IP alias (or the configured default) when absent. Idempotent.
     *
     * @return array{ip: string, seeded: bool}
     */
    protected function ensureBindIp(): array
    {
        $envPath = base_path('.env');

        if (! is_file($envPath)) {
            return ['ip' => (string) config('sail.network.bind_ip', '172.20.0.10'), 'seeded' => false];
        }

        $contents = file_get_contents($envPath);

        // Already defined — never overwrite an existing bind IP.
        if (preg_match('/^SAIL_BIND_IP=(.*)$/m', $contents, $m)) {
            return ['ip' => trim($m[1], " \"'"), 'seeded' => false];
        }

        // Prefer an existing SAIL_IP alias so upgraded projects keep their
        // per-project bind address; fall back to the configured default.
        if (preg_match('/^SAIL_IP=(.*)$/m', $contents, $m)) {
            $ip = trim($m[1], " \"'");
        } else {
            $ip = (string) config('sail.network.bind_ip', '172.20.0.10');
        }

        $writer = new Writer($envPath);
        $writer->set('SAIL_BIND_IP', $ip);
        $writer->write();

        return ['ip' => $ip, 'seeded' => true];
    }

    /**
     * Apply LAN-mode networking values to .env (bind IP, nip.io domain, URLs,
     * profile). Never touches SAIL_SUBNET. Returns the written map.
     *
     * @return array<string, string>
     */
    protected function applyLanConfig(?string $ip = null, ?string $domain = null): array
    {
        $ipWasExplicit = $ip !== null;

        if (! $ip) {
            $envPath = base_path('.env');
            if (is_file($envPath) && preg_match('/^SAIL_BIND_IP=(.*)$/m', file_get_contents($envPath), $m)) {
                $existing = trim($m[1], " \"'");
                $ip = $existing !== '' ? $existing : null;
            }
        }

        $ip = $ip ?: (new HostIpDetector)->detect();

        if (! $ip) {
            throw new \RuntimeException('Could not detect a LAN IP address. Pass one explicitly with --ip=<address>.');
        }

        // Only nip.io is supported in this release; mDNS arrives in Plan 2b.
        $resolver = 'nip';

        $project = $this->resolveProjectName();

        $values = (new LanEnvironment($project, $ip, $resolver))->values();

        if ($domain) {
            $values['SAIL_DOMAIN'] = $domain;
            $values['APP_URL'] = 'https://'.$domain;
            $values['VITE_DEV_SERVER_URL'] = 'https://'.$domain.'/vite';
        } elseif (! $ipWasExplicit) {
            // Reusing the stored bind IP with no explicit domain: preserve the existing
            // domain (custom or auto) so a heal/re-run never drifts it.
            $envPath = base_path('.env');
            if (is_file($envPath) && preg_match('/^SAIL_DOMAIN=(.*)$/m', file_get_contents($envPath), $dm)) {
                $stored = trim($dm[1], " \"'");
                if ($stored !== '') {
                    $values['SAIL_DOMAIN'] = $stored;
                    $values['APP_URL'] = 'https://'.$stored;
                    $values['VITE_DEV_SERVER_URL'] = 'https://'.$stored.'/vite';
                }
            }
        }

        $home = new SailHome;
        $home->ensureDirectories();

        // Per-project override that disables the standalone proxy and joins the shared network.
        $overridePath = $home->overridesDir().'/'.$project.'.yml';
        file_put_contents($overridePath, (new SharedProxyStack($ip, $home->certsDir()))->projectOverride());
        $values['SAIL_FILES'] = basename($this->composePath()).':'.$overridePath;

        // Per-project raw-TCP port offsets so services don't collide on the shared IP.
        $slot = (new HostRegistry($home->registryPath()))->slotFor($project);
        $present = $this->composeServiceNames();
        foreach ($this->forwardPortMap() as $service => [$var, $base]) {
            if (in_array($service, $present, true)) {
                $values[$var] = (string) HostRegistry::port($base, $slot);
            }
        }

        $writer = new Writer(base_path('.env'));
        foreach ($values as $key => $value) {
            $writer->set($key, $value);
        }
        $writer->write();

        return $values;
    }

    /**
     * Map of raw-TCP services to the .env forward-port variable and base port
     * that must be unique per project when sharing a single LAN IP.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    protected function forwardPortMap(): array
    {
        return [
            'mysql' => ['FORWARD_DB_PORT', 3306],
            'pgsql' => ['FORWARD_DB_PORT', 5432],
            'mariadb' => ['FORWARD_DB_PORT', 3306],
            'redis' => ['FORWARD_REDIS_PORT', 6379],
            'valkey' => ['FORWARD_VALKEY_PORT', 6379],
            'mailpit' => ['FORWARD_MAILPIT_DASHBOARD_PORT', 8025],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function composeServiceNames(): array
    {
        $path = $this->composePath();

        if (! $path || ! is_file($path)) {
            return [];
        }

        $parsed = Yaml::parseFile($path);

        return array_keys($parsed['services'] ?? []);
    }

    /**
     * Build the Docker Compose file.
     *
     * @param  array  $services
     * @param  string  $project
     * @return void
     */
    protected function buildDockerCompose(array $services, string $project = 'laravel')
    {
        $composePath = $this->composePath();

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents(__DIR__ . '/../../../stubs/compose.stub'));

        $compose['name'] = $project;

        // Prepare the installation of the "mariadb-client" package if the MariaDB service is used...
        if (in_array('mariadb', $services)) {
            $compose['services']['laravel']['build']['args']['MYSQL_CLIENT'] = 'mariadb-client';
        }

        // Adds the new services as dependencies of the laravel service...
        if (! array_key_exists('laravel', $compose['services'])) {
            $this->warn('Couldn\'t find the laravel service. Make sure you add [' . implode(',', $services) . '] to the depends_on config.');
        } else {
            $compose['services']['laravel']['depends_on'] = collect($compose['services']['laravel']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        // Add the services to the compose.yaml...
        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile(__DIR__ . "/../../../stubs/{$service}.stub")[$service];
            });

        // Merge volumes...
        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey', 'meilisearch', 'typesense', 'minio', 'rustfs', 'rabbitmq']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["sail-{$service}"] = ['driver' => 'local'];
            });

        // If the list of volumes is empty, we can remove it...
        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        $yaml = Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP);

        $yaml = str_replace('{{PHP_VERSION}}', $this->hasOption('php') ? $this->option('php') : '8.5', $yaml);

        file_put_contents($composePath, $yaml);
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param  array  $services
     * @return void
     */
    protected function replaceEnvVariables(string $project, array $services)
    {
        $environment = file_get_contents($this->laravel->basePath('.env'));

        if (
            in_array('mysql', $services) ||
            in_array('mariadb', $services) ||
            in_array('pgsql', $services)
        ) {
            $defaults = [
                '# DB_HOST=127.0.0.1',
                '# DB_PORT=3306',
                '# DB_DATABASE=laravel',
                '# DB_USERNAME=root',
                '# DB_PASSWORD=',
            ];

            foreach ($defaults as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }
        }

        if (in_array('mysql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            $environment = str_replace('/DB_HOST=.*/', "DB_HOST=mysql", $environment);
        } elseif (in_array('pgsql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = str_replace('/DB_HOST=.*/', "DB_HOST=pgsql", $environment);
            $environment = str_replace('DB_PORT=3306', "DB_PORT=5432", $environment);
        } elseif (in_array('mariadb', $services)) {
            if ($this->laravel->config->has('database.connections.mariadb')) {
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            }

            $environment = preg_replace('/DB_HOST=.*/', "DB_HOST=mariadb", $environment);
        }

        $environment = preg_replace('/DB_USERNAME=.*/', "DB_USERNAME=sail", $environment);
        $environment = preg_replace("/DB_PASSWORD=(.*)/", "DB_PASSWORD=password", $environment);

        if (in_array('memcached', $services)) {
            $environment = str_replace('/MEMCACHED_HOST=.*/', 'MEMCACHED_HOST=memcached', $environment);
        }

        if (in_array('redis', $services)) {
            $environment = str_replace('/REDIS_HOST=.*/', 'REDIS_HOST=redis', $environment);
        }

        if (in_array('valkey', $services)) {
            $environment = str_replace('/REDIS_HOST=.*/', 'REDIS_HOST=valkey', $environment);
        }

        if (in_array('mongodb', $services)) {
            $environment .= "\nMONGODB_URI=mongodb://mongodb:27017";
            $environment .= "\nMONGODB_DATABASE=laravel";
        }

        if (in_array('meilisearch', $services)) {
            $environment .= "\nSCOUT_DRIVER=meilisearch";
            $environment .= "\nMEILISEARCH_HOST=http://meilisearch:7700\n";
            $environment .= "\nMEILISEARCH_NO_ANALYTICS=false\n";
        }

        if (in_array('typesense', $services)) {
            $environment .= "\nSCOUT_DRIVER=typesense";
            $environment .= "\nTYPESENSE_HOST=typesense";
            $environment .= "\nTYPESENSE_PORT=8108";
            $environment .= "\nTYPESENSE_PROTOCOL=http";
            $environment .= "\nTYPESENSE_API_KEY=xyz\n";
        }

        if (in_array('minio', $services)) {
            $environment = preg_replace("/^AWS_ACCESS_KEY_ID=(.*)/m", "AWS_ACCESS_KEY_ID=sail", $environment);
            $environment = preg_replace("/^AWS_SECRET_ACCESS_KEY=(.*)/m", "AWS_SECRET_ACCESS_KEY=minio123", $environment);
            $environment = preg_replace("/^AWS_DEFAULT_REGION=(.*)/m", "AWS_DEFAULT_REGION=us-east-1", $environment);
            $environment = preg_replace("/^AWS_BUCKET=(.*)/m", "AWS_BUCKET={$project}", $environment);
            $environment = preg_replace("/^AWS_ENDPOINT=(.*)/m", "AWS_ENDPOINT=" . preg_replace('/^http:/', 'https:', config('app.url')) . ":9000", $environment);
            $environment = preg_replace("/^AWS_USE_PATH_STYLE_ENDPOINT=(.*)/m", "AWS_USE_PATH_STYLE_ENDPOINT=true", $environment);
        }

        if (in_array('soketi', $services)) {
            $environment = preg_replace("/^BROADCAST_DRIVER=(.*)/m", "BROADCAST_DRIVER=pusher", $environment);
            $environment = preg_replace("/^PUSHER_APP_ID=(.*)/m", "PUSHER_APP_ID=app-id", $environment);
            $environment = preg_replace("/^PUSHER_APP_KEY=(.*)/m", "PUSHER_APP_KEY=app-key", $environment);
            $environment = preg_replace("/^PUSHER_APP_SECRET=(.*)/m", "PUSHER_APP_SECRET=app-secret", $environment);
            $environment = preg_replace("/^PUSHER_HOST=(.*)/m", "PUSHER_HOST=soketi", $environment);
            $environment = preg_replace("/^PUSHER_PORT=(.*)/m", "PUSHER_PORT=6001", $environment);
            $environment = preg_replace("/^PUSHER_SCHEME=(.*)/m", "PUSHER_SCHEME=http", $environment);
            $environment = preg_replace("/^VITE_PUSHER_HOST=(.*)/m", "VITE_PUSHER_HOST=localhost", $environment);
        }

        if (in_array('mailpit', $services)) {
            $environment = preg_replace("/^MAIL_MAILER=(.*)/m", "MAIL_MAILER=smtp", $environment);
            $environment = preg_replace("/^MAIL_HOST=(.*)/m", "MAIL_HOST=mailpit", $environment);
            $environment = preg_replace("/^MAIL_PORT=(.*)/m", "MAIL_PORT=1025", $environment);
        }

        if (in_array('rabbitmq', $services)) {
            $environment = str_replace('RABBITMQ_HOST=127.0.0.1', 'RABBITMQ_HOST=rabbitmq', $environment);
        }

        if (in_array('gotenberg', $services)) {
            $environment .= "\nGOTENBERG_URL=http://gotenberg:3000\n";
        }

        $environment = str_replace('# PHP_CLI_SERVER_WORKERS=4', 'PHP_CLI_SERVER_WORKERS=4', $environment);

        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    /**
     * Configure PHPUnit to use the dedicated testing database.
     *
     * @return void
     */
    protected function configurePhpUnit()
    {
        if (! file_exists($path = $this->laravel->basePath('phpunit.xml'))) {
            $path = $this->laravel->basePath('phpunit.xml.dist');

            if (! file_exists($path)) {
                return;
            }
        }

        $phpunit = file_get_contents($path);

        $phpunit = preg_replace('/^.*DB_CONNECTION.*\n/m', '', $phpunit);
        $phpunit = str_replace(
            [
                '<!-- <env name="DB_DATABASE" value=":memory:"/> -->',
                '<env name="DB_DATABASE" value=":memory:"/>',
            ],
            '<env name="DB_DATABASE" value="testing"/>',
            $phpunit
        );

        file_put_contents($this->laravel->basePath('phpunit.xml'), $phpunit);
    }

    /**
     * Install the devcontainer.json configuration file.
     *
     * @return void
     */
    protected function installDevContainer()
    {
        if (! is_dir($this->laravel->basePath('.devcontainer'))) {
            mkdir($this->laravel->basePath('.devcontainer'), 0755, true);
        }

        file_put_contents(
            $this->laravel->basePath('.devcontainer/devcontainer.json'),
            file_get_contents(__DIR__ . '/../../../stubs/devcontainer.stub')
        );

        $environment = file_get_contents($this->laravel->basePath('.env'));

        $environment .= "\nWWWGROUP=1000";
        $environment .= "\nWWWUSER=1000\n";

        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    /**
     * Prepare the installation by pulling and building any necessary images.
     *
     * @param  array  $services
     * @return void
     */
    protected function prepareInstallation($services)
    {
        $this->runSetup();

        // Ensure docker is installed...
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            return;
        }

        if (count($services) > 0) {
            $this->runCommands([
                './vendor/bin/sail pull ' . implode(' ', $services),
            ]);
        }

        $this->runCommands([
            './vendor/bin/sail build',
        ]);
    }

    /**
     * Run the setup command.
     *
     * @return string
     */
    protected function runSetup()
    {
        $this->runCommands([
            './vendor/bin/sail-setup',
        ]);
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @return int
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> ' . $e->getMessage() . PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    ' . $line);
        });
    }

    /**
     * Get the path to an existing Compose file or fall back to a default of `compose.yaml`.
     *
     * @return string
     */
    protected function composePath()
    {
        return collect($this->composePaths)
            ->map(fn ($path) => $this->laravel->basePath($path))
            ->first(fn ($path) => file_exists($path), $this->laravel->basePath('compose.yaml'));
    }
}
