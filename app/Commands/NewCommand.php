<?php

namespace NovaKit\SetupNova\Commands;

use LaravelZero\Framework\Commands\Command;
use TitasGailius\Terminal\Terminal;

class NewCommand extends Command
{
    use Concerns\PathFinders;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'new {name}
                                {--issue : Create an issue repository}
                                {--github= : Create a new repository on GitHub}
                                {--organization= : The GitHub organization to create the new repository for}
                                {--install-optional : Install all optional dependencies}
                                {--with-sample-data : With sample data}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Create a new Laravel application with Nova';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $projectName = $this->argument('name');

        if ($this->option('issue') === true) {
            $projectName = implode('-', ['issue', $projectName]);
        }

        $workingPath = getcwd().'/'.$projectName;

        $this->runLaravelInstaller($projectName);
        $this->configureDatabase($projectName, $workingPath);

        $this->call('install', [
            '--working-path' => $workingPath,
            '--install-optional' => $this->option('install-optional'),
            '--with-sample-data' => $this->option('with-sample-data'),
        ]);
    }

    /**
     * Run Laravel Installer.
     */
    protected function runLaravelInstaller(string $projectName): void
    {
        $command = collect([
            $this->findLaravelInstaller(),
            'new',
            $projectName,
        ]);

        if ($this->hasOption('github') && ! is_null($github = $this->option('github'))) {
            $command->push('--github="'.$github.'"');

            if ($this->hasOption('organization')) {
                $command->push('--organization="'.$this->option('organization').'"');
            }
        }

        $command->push('--git');

        $supportedVersions = [
            '8.x',
            '9.x',
        ];

        $branch = $this->menu('Choose Laravel Version', $supportedVersions)->open();

        $command->push('--branch="'.$supportedVersions[$branch].'"');

        $this->task('Install Laravel', function () use ($command) {
            Terminal::builder()->in(getcwd())->run($command->join(' '));

            return true;
        });
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
}
