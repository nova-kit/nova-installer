<?php

namespace NovaKit\SetupNova\Commands;

use LaravelZero\Framework\Commands\Command;
use TitasGailius\Terminal\Terminal;

use function Illuminate\Filesystem\join_paths;

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

        $workingPath = (string) realpath(join_paths(getcwd(), $projectName));

        $this->runLaravelInstaller($projectName);

        $this->call('install', [
            'name' => $projectName,
            '--working-path' => $workingPath,
            '--install-optional' => $this->option('install-optional'),
            '--with-sample-data' => $this->option('with-sample-data'),
        ], $this->output);
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

        $this->task('Install Laravel', function () use ($command) {
            Terminal::builder()->in(getcwd())->run($command->join(' '));

            return true;
        });
    }
}
