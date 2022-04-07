<?php

namespace NovaKit\SetupNova\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use TitasGailius\Terminal\Terminal;

class InstallCommand extends Command
{
    use Concerns\PathFinders;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'install {name}
                                    {--working-path= : Project working directory}
                                    {--install-optional : Install all optional dependencies}
                                    {--with-sample-data : With sample data}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Install Nova';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $projectName = $this->argument('name');
        $workingPath = $this->option('working-path') ?? getcwd();
        $php = $this->findPhpBinary();
        $composer = $this->findComposer($workingPath);

        $this->configureDatabase($projectName, $workingPath);

        if ($this->option('install-optional') === true) {
            $this->requireHelperPackages($composer, $workingPath);
        }

        $this->requireLaravelNova($php, $composer, $workingPath);

        if ($this->option('with-sample-data') === true) {
            $this->copyDatabaseSeeders($workingPath);
        }

        $this->runDatabaseMigrations($php, $composer, $workingPath);
    }

    /**
     * Configure database.
     */
    protected function configureDatabase(string $projectName, string $workingPath): void
    {
        $this->task('Configure Database', function () use ($projectName, $workingPath) {
            $database = $this->menu('Choose Database Driver', [
                'mysql',
                'sqlite'
            ])->open();

            $phpBinary = $this->findPhpBinary();

            if ($database == 0) {
                return $this->configureMySqlDatabase($phpBinary, $projectName, $workingPath);
            } elseif ($database == 1) {
                return $this->configureSqliteDatabase($phpBinary, $projectName, $workingPath);
            }

            return false;
        });
    }

    /**
     * Configure database.
     */
    protected function configureMySqlDatabase(string $phpBinary, string $projectName, string $workingPath): bool
    {
        $db['HOST'] = $this->ask('Database Host?', $defaults['HOST'] = '127.0.0.1');
        $db['PORT'] = $this->ask('Database Port?', $defaults['PORT'] = '3306');
        $db['USERNAME'] = $this->ask('Database Username?', $defaults['USERNAME'] = 'root');
        $db['PASSWORD'] = $this->ask('Database Password?', $defaults['PASSWORD'] = null);
        $db['DATABASE'] = $this->ask('Database Name?', $defaults['DATABASE'] = str_replace('-', '_', strtolower($projectName)));

        Terminal::builder()
            ->in(getcwd())
            ->run(
                "mysql --user={$db['USERNAME']} --password={$db['PASSWORD']} --host={$db['HOST']} --port={$db['PORT']} -e \"create database {$db['DATABASE']};\""
            );

        $commands = collect();

        foreach (['HOST', 'PORT', 'USERNAME', 'PASSWORD', 'DATABASE'] as $type) {
            if ($db[$type] !== $defaults[$type]) {
                $commands->push(
                    "{$phpBinary} artisan tinker --execute=\"file_put_contents('.env', str_replace(['DB_{$type}={$defaults[$type]}'], ['DB_{$type}={$db[$type]}'], file_get_contents('.env')));\""
                );
            }
        }

        foreach ($commands as $command) {
            Terminal::builder()->in($workingPath)->run($command);
        }

        return true;
    }

    protected function configureSqliteDatabase(string $phpBinary, string $projectName, string $workingPath): bool
    {
        $defaultDatabase = str_replace('-', '_', strtolower($projectName));

        touch("{$workingPath}/database/database.sqlite");

        $commands = collect([
            "{$phpBinary} artisan tinker --execute=\"file_put_contents('.env', str_replace(['DB_CONNECTION=mysql'], ['DB_CONNECTION=sqlite'], file_get_contents('.env')));\"",
            "{$phpBinary} artisan tinker --execute=\"file_put_contents('.env', str_replace(['DB_DATABASE={$defaultDatabase}'], ['# DB_DATABASE={$defaultDatabase}'], file_get_contents('.env')));\"",
        ]);

        foreach ($commands as $command) {
            Terminal::builder()->in($workingPath)->run($command);
        }

        return true;
    }

    /**
     * Copy seeder files.
     */
    protected function copyDatabaseSeeders(string $workingPath): void
    {
        $this->task('Setup database seeders', function () use ($workingPath) {
            $source = __DIR__ . '/stubs';
            $target = $workingPath . '/database/seeders';

            foreach (['UserTableSeeder', 'DatabaseSeeder'] as $file) {
                File::put("{$target}/{$file}.php", File::get("{$source}/{$file}.stub"));
            }

            return true;
        });
    }

    /**
     * Run database migrations.
     */
    protected function runDatabaseMigrations(string $php, string $composer, $workingPath): void
    {
        $this->task('Migrate database', function () use ($php, $composer, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$php} artisan migrate --seed"
            );

            return true;
        });
    }

    /**
     * Require helper packages.
     */
    protected function requireHelperPackages(string $composer, $workingPath): void
    {
        $this->task('Require helper packages', function () use ($composer, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$composer} require --dev 'spatie/laravel-ray'"
            );

            Terminal::builder()->in($workingPath)->run(
                "{$composer} require 'nova-kit/helpers'"
            );

            return true;
        });
    }

    /**
     * Require Laravel Nova
     */
    protected function requireLaravelNova(string $php, string $composer, $workingPath): void
    {
        $this->task('Require Laravel Nova', function () use ($php, $composer, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$composer} config repositories.nova '{\"type\": \"composer\", \"url\": \"https://nova.laravel.com\"}' --file composer.json"
            );

            $supportedVersions = [
                '4.0',
                '3.0',
            ];

            $branch = $this->menu('Choose Laravel Nova Version', $supportedVersions)->open();

            Terminal::builder()->in($workingPath)->run(
                "{$composer} require 'laravel/nova:^{$supportedVersions[$branch]}'"
            );

            Terminal::builder()->in($workingPath)->run(
                "{$php} artisan nova:install"
            );

            return true;
        });
    }
}
