<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

class InstallCommand extends Command
{
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
        $php = $this->findPhpBinary();
        $composer = $this->findComposer();

        $this->task('Setup .env', function () use ($php) {
            Terminal::in(getcwd())->run(
                "{$php} artisan --execute=\"file_put_contents('.env', str_replace(['DB_HOST=mysql'], ['DB_HOST=127.0.0.1'], file_get_contents('.env')));\""
            );

            return true;
        });

        $this->task('Require helper packages', function () use ($composer) {
            Terminal::in(getcwd())->run(
                "{$composer} require --dev 'spatie/laravel-ray'"
            );

            Terminal::in(getcwd())->run(
                "{$composer} require 'nova-kit/helpers'"
            );

            return true;
        });

        $this->task('Require Laravel Nova', function () use ($php, $composer) {
            Terminal::in(getcwd())->run(
                "{$composer} config repositories.local '{\"type\": \"composer\", \"url\": \"https://nova.laravel.com\"}' --file composer.json"
            );

            Terminal::in(getcwd())->run(
                "{$composer} require 'laravel/nova:*"
            );

            Terminal::in(getcwd())->run(
                "{$php} artisan nova:install"
            );

            return true;
        });

        $this->task('Setup Seeders', function () {
            return $this->copySeeders();
        });


        $this->task('Migrate', function () use ($php, $composer) {
            Terminal::in(getcwd())->run(
                "{$php} artisan migrate --seed"
            );

            return true;
        });
    }

    /**
     * Copy seeder files.
     */
    protected function copySeeders(): bool
    {
        $source = __DIR__.'/stubs';
        $target = getcwd().'/database/seeders';

        foreach (['UserTableSeeder', 'DatabaseSeeder'] as $file) {
            File::put("{$target}/{$file}.php", File::get("{$source}/{$file}.stub"));
        }

        return true;
    }

    /**
     * Get the PHP binary for the environment.
     *
     * @return string
     */
    protected function findPhpBinary(): string
    {
        return (new PhpExecutableFinder)->find();
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer(): string
    {
        $composerPath = getcwd().'/composer.phar';

        $php = $this->findPhpBinary();

        if (! file_exists($composerPath)) {
            $composerPath = (new ExecutableFinder())->find('composer');
        }

        return '"'.$php.'" '.$composerPath;
    }
}
