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
    protected $signature = 'install {--working-path=}';

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
        $workingPath = $this->option('working-path') ?? getcwd();
        $php = $this->findPhpBinary();
        $composer = $this->findComposer($workingPath);

        $this->requireHelperPackages($composer, $workingPath);

        $this->requireLaravelNova($php, $composer, $workingPath);

        $this->copyDatabaseSeeders($workingPath);

        $this->runDatabaseMigrations($php, $composer, $workingPath);
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

            Terminal::builder()->in($workingPath)->run(
                "{$composer} require 'laravel/nova:*'"
            );

            Terminal::builder()->in($workingPath)->run(
                "{$php} artisan nova:install"
            );

            return true;
        });
    }
}
