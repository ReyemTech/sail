<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'sail:ci')]
class CiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:ci
                            {--provider= : CI provider (github-actions, circleci, travis)}
                            {--branch= : Default branch name (main or master)}
                            {--overwrite : Overwrite existing CI configuration files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add CI/CD configuration files for automated Docker builds';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->output->writeln('');
        $this->components->info('🚀 Setting up CI/CD for Docker builds...');
        $this->output->writeln('');

        $provider = $this->option('provider');

        if (! $provider) {
            $providers = [
                'github-actions' => 'GitHub Actions',
                'gitlab-ci' => 'GitLab CI/CD',
                'azure-devops' => 'Azure DevOps Pipelines',
                'circleci' => 'CircleCI',
                'aws-codebuild' => 'AWS CodeBuild',
                'travis' => 'Travis CI',
            ];

            if (function_exists('\Laravel\Prompts\select')) {
                $provider = \Laravel\Prompts\select(
                    label: 'Which CI provider would you like to use?',
                    options: $providers,
                    default: 'github-actions',
                );
            } else {
                $provider = $this->choice('Which CI provider would you like to use?', $providers, 0);
            }
        }

        $provider = strtolower($provider);

        $validProviders = ['github-actions', 'gitlab-ci', 'azure-devops', 'circleci', 'aws-codebuild', 'travis'];
        if (! in_array($provider, $validProviders)) {
            $this->components->error('Invalid CI provider. Valid options: '.implode(', ', $validProviders));

            return 1;
        }

        $overwrite = $this->option('overwrite');

        return match ($provider) {
            'github-actions' => $this->setupGitHubActions($overwrite),
            'gitlab-ci' => $this->setupGitLabCI($overwrite),
            'azure-devops' => $this->setupAzureDevOps($overwrite),
            'circleci' => $this->setupCircleCI($overwrite),
            'aws-codebuild' => $this->setupAWSCodeBuild($overwrite),
            'travis' => $this->setupTravisCI($overwrite),
            default => 1,
        };
    }

    /**
     * Setup GitHub Actions workflow.
     */
    protected function setupGitHubActions(bool $overwrite): int
    {
        $workflowDir = base_path('.github/workflows');
        $workflowFile = $workflowDir.'/ci.yml';

        if (file_exists($workflowFile) && ! $overwrite) {
            $this->components->error('GitHub Actions workflow already exists at: '.$workflowFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        if (! is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        $stubPath = __DIR__.'/../../stubs/ci/github-actions-build.yml.stub';
        $stub = file_get_contents($stubPath);

        $appName = Str::slug(config('app.name', 'laravel'));
        $repository = config('sail.build.repository', 'ghcr.io');
        $organization = config('sail.build.organization', 'my-org');
        $phpVersion = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;
        $branch = $this->option('branch') ?: $this->detectDefaultBranch();
        $dbConnection = config('database.default', 'mysql');

        // Core placeholders
        $stub = str_replace('__APP_NAME__', $appName, $stub);
        $stub = str_replace('__REPOSITORY__', $repository, $stub);
        $stub = str_replace('__ORGANIZATION__', $organization, $stub);
        $stub = str_replace('__PHP_VERSION__', $phpVersion, $stub);
        $stub = str_replace('__BRANCH__', $branch, $stub);

        // Database-specific blocks
        if ($dbConnection === 'pgsql') {
            $stub = str_replace('__DB_SERVICE__', $this->postgresService(), $stub);
            $stub = str_replace('__DB_EXTENSIONS__', 'pdo_pgsql', $stub);
            $stub = str_replace('__DB_ENV__', $this->postgresEnv(), $stub);
        } else {
            $stub = str_replace('__DB_SERVICE__', $this->mysqlService(), $stub);
            $stub = str_replace('__DB_EXTENSIONS__', 'pdo_mysql', $stub);
            $stub = str_replace('__DB_ENV__', $this->mysqlEnv(), $stub);
        }

        file_put_contents($workflowFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created GitHub Actions workflow: '.$workflowFile);
        $this->output->writeln('');
        $this->components->info('📝 Configuration detected:');
        $this->output->writeln("     PHP: {$phpVersion}");
        $this->output->writeln("     Database: {$dbConnection}");
        $this->output->writeln("     Branch: {$branch}");
        $this->output->writeln("     Registry: {$repository}/{$organization}/{$appName}");
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add release-please config files if not present:');
        $this->output->writeln('     - release-please-config.json');
        $this->output->writeln('     - .release-please-manifest.json');
        $this->output->writeln('  2. Ensure GITHUB_TOKEN has packages:write permission');
        $this->output->writeln('  3. Review and customize the workflow as needed');
        $this->output->writeln('');

        return 0;
    }

    protected function detectDefaultBranch(): string
    {
        $gitDir = base_path('.git');
        if (is_dir($gitDir)) {
            $head = @file_get_contents($gitDir.'/HEAD');
            if ($head && preg_match('#refs/heads/(\S+)#', $head, $matches)) {
                return $matches[1];
            }
        }

        return 'main';
    }

    protected function mysqlService(): string
    {
        return <<<'YAML'
      mysql:
        image: mysql:8
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
YAML;
    }

    protected function postgresService(): string
    {
        return <<<'YAML'
      postgres:
        image: postgres:16
        env:
          POSTGRES_USER: postgres
          POSTGRES_PASSWORD: password
          POSTGRES_DB: testing
        ports:
          - 5432:5432
        options: --health-cmd="pg_isready" --health-interval=10s --health-timeout=5s --health-retries=3
YAML;
    }

    protected function mysqlEnv(): string
    {
        return <<<'YAML'
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
YAML;
    }

    protected function postgresEnv(): string
    {
        return <<<'YAML'
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_PORT: 5432
          DB_DATABASE: testing
          DB_USERNAME: postgres
          DB_PASSWORD: password
YAML;
    }

    /**
     * Setup CircleCI configuration.
     */
    protected function setupCircleCI(bool $overwrite): int
    {
        $configFile = base_path('.circleci/config.yml');

        if (file_exists($configFile) && ! $overwrite) {
            $this->components->error('CircleCI config already exists at: '.$configFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        if (! is_dir(dirname($configFile))) {
            mkdir(dirname($configFile), 0755, true);
        }

        $stubPath = __DIR__.'/../../stubs/ci/circleci-config.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('__APP_NAME__', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('__REPOSITORY__', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('__ORGANIZATION__', config('sail.build.organization', 'my-org'), $stub);

        file_put_contents($configFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created CircleCI config: '.$configFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following environment variables in CircleCI project settings:');
        $this->output->writeln('     - REGISTRY_USERNAME (or GHCR_IO_USERNAME for GitHub Container Registry)');
        $this->output->writeln('     - REGISTRY_PASSWORD (or GHCR_IO_PASSWORD for GitHub Container Registry)');
        $this->output->writeln('  2. Customize the config file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup Travis CI configuration.
     */
    protected function setupTravisCI(bool $overwrite): int
    {
        $configFile = base_path('.travis.yml');

        if (file_exists($configFile) && ! $overwrite) {
            $this->components->error('Travis CI config already exists at: '.$configFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        $stubPath = __DIR__.'/../../stubs/ci/travis-ci.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('__APP_NAME__', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('__REPOSITORY__', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('__ORGANIZATION__', config('sail.build.organization', 'my-org'), $stub);

        file_put_contents($configFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created Travis CI config: '.$configFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following environment variables in Travis CI project settings:');
        $this->output->writeln('     - REGISTRY_USERNAME (or GHCR_IO_USERNAME for GitHub Container Registry)');
        $this->output->writeln('     - REGISTRY_PASSWORD (or GHCR_IO_PASSWORD for GitHub Container Registry)');
        $this->output->writeln('  2. Customize the config file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup GitLab CI configuration.
     */
    protected function setupGitLabCI(bool $overwrite): int
    {
        $configFile = base_path('.gitlab-ci.yml');

        if (file_exists($configFile) && ! $overwrite) {
            $this->components->error('GitLab CI config already exists at: '.$configFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        $stubPath = __DIR__.'/../../stubs/ci/gitlab-ci.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('__APP_NAME__', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('__REPOSITORY__', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('__ORGANIZATION__', config('sail.build.organization', 'my-org'), $stub);

        file_put_contents($configFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created GitLab CI config: '.$configFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following CI/CD variables in GitLab project settings:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_ACCESS_KEY_ID');
            $this->output->writeln('     - AWS_SECRET_ACCESS_KEY');
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CLIENT_ID');
            $this->output->writeln('     - AZURE_CLIENT_SECRET');
            $this->output->writeln('     - AZURE_TENANT_ID');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME (or CI_REGISTRY_USER for GitLab Container Registry)');
            $this->output->writeln('     - REGISTRY_PASSWORD (or CI_REGISTRY_PASSWORD for GitLab Container Registry)');
        }
        $this->output->writeln('  2. Customize the config file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup Azure DevOps Pipelines configuration.
     */
    protected function setupAzureDevOps(bool $overwrite): int
    {
        $pipelineDir = base_path('azure-pipelines');
        $pipelineFile = $pipelineDir.'/build.yml';

        if (file_exists($pipelineFile) && ! $overwrite) {
            $this->components->error('Azure DevOps pipeline already exists at: '.$pipelineFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        if (! is_dir($pipelineDir)) {
            mkdir($pipelineDir, 0755, true);
        }

        $stubPath = __DIR__.'/../../stubs/ci/azure-pipelines.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('__APP_NAME__', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('__REPOSITORY__', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('__ORGANIZATION__', config('sail.build.organization', 'my-org'), $stub);

        file_put_contents($pipelineFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created Azure DevOps pipeline: '.$pipelineFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Add the following variables in Azure DevOps pipeline settings:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_ACCESS_KEY_ID');
            $this->output->writeln('     - AWS_SECRET_ACCESS_KEY');
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CREDENTIALS (service connection)');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME');
            $this->output->writeln('     - REGISTRY_PASSWORD');
        }
        $this->output->writeln('  2. Create a pipeline in Azure DevOps and point it to: azure-pipelines/build.yml');
        $this->output->writeln('  3. Customize the pipeline file if needed');
        $this->output->writeln('');

        return 0;
    }

    /**
     * Setup AWS CodeBuild configuration.
     */
    protected function setupAWSCodeBuild(bool $overwrite): int
    {
        $buildspecFile = base_path('buildspec.yml');

        if (file_exists($buildspecFile) && ! $overwrite) {
            $this->components->error('AWS CodeBuild buildspec already exists at: '.$buildspecFile);
            $this->output->writeln('  Use --overwrite to replace it.');

            return 1;
        }

        $stubPath = __DIR__.'/../../stubs/ci/buildspec.yml.stub';
        $stub = file_get_contents($stubPath);

        // Replace placeholders
        $stub = str_replace('__APP_NAME__', Str::slug(config('app.name', 'laravel')), $stub);
        $stub = str_replace('__REPOSITORY__', config('sail.build.repository', 'ghcr.io'), $stub);
        $stub = str_replace('__ORGANIZATION__', config('sail.build.organization', 'my-org'), $stub);

        file_put_contents($buildspecFile, $stub);

        $this->output->writeln('  <fg=green>✓</> Created AWS CodeBuild buildspec: '.$buildspecFile);
        $this->output->writeln('');
        $this->components->info('📝 Next steps:');
        $this->output->writeln('  1. Create a CodeBuild project in AWS Console');
        $this->output->writeln('  2. Set the buildspec file to: buildspec.yml');
        $this->output->writeln('  3. Configure environment variables in CodeBuild project:');
        $registry = config('sail.build.repository', 'ghcr.io');
        if (strpos($registry, 'ecr') !== false || strpos($registry, 'amazonaws.com') !== false) {
            $this->output->writeln('     - AWS_REGION (optional, defaults to us-east-1)');
            $this->output->writeln('     Note: ECR authentication uses IAM role attached to CodeBuild project');
        } elseif (strpos($registry, 'azurecr.io') !== false) {
            $this->output->writeln('     - AZURE_CLIENT_ID');
            $this->output->writeln('     - AZURE_CLIENT_SECRET');
            $this->output->writeln('     - AZURE_TENANT_ID');
        } else {
            $this->output->writeln('     - REGISTRY_USERNAME');
            $this->output->writeln('     - REGISTRY_PASSWORD');
        }
        $this->output->writeln('  4. Customize the buildspec file if needed');
        $this->output->writeln('');

        return 0;
    }
}
