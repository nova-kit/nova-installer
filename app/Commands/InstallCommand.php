<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\Command;

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
        $this->task('Setup Seeders', function () {
            return $this->copySeeders();
        });
    }

    protected function copySeeders(): bool
    {
        $source = __DIR__.'/../../stubs';
        $target = getcwd().'/database/seeders';

        foreach (['UserTableSeeder', 'DatabaseSeeder'] as $file) {
            File::put("{$target}/{$file}.php", File::get("{$source}/{$file}.stub"));
        }

        return true;
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        //
    }
}
