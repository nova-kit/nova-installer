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
    protected $signature = 'install';

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
        $workingPath = getcwd();
        $php = $this->findPhpBinary();
        $composer = $this->findComposer($workingPath);

        $this->task('Setup .env', function () use ($php, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$php} artisan --execute=\"file_put_contents('.env', str_replace(['DB_HOST=mysql'], ['DB_HOST=127.0.0.1'], file_get_contents('.env')));\""
            );

            return true;
        });

        $this->task('Require helper packages', function () use ($composer, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$composer} require --dev 'spatie/laravel-ray'"
            );

            Terminal::builder()->in($workingPath)->run(
                "{$composer} require 'nova-kit/helpers'"
            );

            return true;
        });

        $this->task('Require Laravel Nova', function () use ($php, $composer, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$composer} config repositories.local '{\"type\": \"composer\", \"url\": \"https://nova.laravel.com\"}' --file composer.json"
            );

            Terminal::builder()->in($workingPath)->run(
                "{$composer} require 'laravel/nova:*"
            );

            Terminal::builder()->in($workingPath)->run(
                "{$php} artisan nova:install"
            );

            return true;
        });

        $this->task('Setup Seeders', function () use ($workingPath) {
            return $this->copySeeders($workingPath);
        });


        $this->task('Migrate', function () use ($php, $composer, $workingPath) {
            Terminal::builder()->in($workingPath)->run(
                "{$php} artisan migrate --seed"
            );

            return true;
        });
    }

    /**
     * Copy seeder files.
     */
    protected function copySeeders(string $workingPath): bool
    {
        $source = __DIR__.'/stubs';
        $target = $workingPath.'/database/seeders';

        foreach (['UserTableSeeder', 'DatabaseSeeder'] as $file) {
            File::put("{$target}/{$file}.php", File::get("{$source}/{$file}.stub"));
        }

        return true;
    }
}
