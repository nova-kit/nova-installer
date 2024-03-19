<?php

namespace NovaKit\SetupNova\Commands\Concerns;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;

use function Illuminate\Filesystem\join_paths;

trait PathFinders
{
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
     * Get the PHP binary for the environment.
     *
     * @return string
     */
    protected function findMySqlBinary(): string
    {
        return tap((new ExecutableFinder())->find('mysql'), function ($mysql) {
            if (is_null($mysql)) {
                throw new RuntimeException('Unable to find mysql client binary');
            }
        });
    }

    /**
     * Get the laravel installer command for the environment.
     *
     * @return string
     */
    protected function findLaravelInstaller(): string
    {
        return tap((new ExecutableFinder())->find('laravel'), function ($installer) {
            if (is_null($installer)) {
                throw new RuntimeException('Unable to find Laravel Installer, please run "composer global require \'laravel/installer\'"');
            }
        });
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer(string $workingPath): string
    {
        $composer = join_paths($workingPath, 'composer.phar');

        $php = $this->findPhpBinary();

        if (! file_exists($composer)) {
            $composer = tap((new ExecutableFinder())->find('composer'), function ($composer) {
                if (is_null($composer)) {
                    throw new RuntimeException('Unable to find composer path');
                }
            });
        }

        return '"'.$php.'" '.$composer;
    }
}
