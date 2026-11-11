<?php

namespace Mifesta\DotenvEditor\Console\Commands;

use Illuminate\Console\Command;
use Mifesta\DotenvEditor\Console\Traits\CreateCommandInstanceTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DotenvGetBackupsCommand
 *
 * @package Mifesta\DotenvEditor\Console\Commands
 */
class DotenvGetBackupsCommand extends Command
{
    use CreateCommandInstanceTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dotenv:get-backups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all the .env file backup versions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function fire()
    {
        $headers = ['File name', 'File path', 'Created at'];

        if ($this->option('latest')) {
            $latest = $this->editor->getLatestBackup();
            $backups = $latest === null ? [] : [$latest];
        } else {
            $backups = $this->editor->getBackups();
        }

        $total = count($backups);

        $this->line('Loading backup files...');
        $this->line('');

        if ($total == 0) {
            $this->info('There are no backup files.');
        } elseif ($total == 1) {
            $this->table($headers, $backups);
            $this->line('');
            $this->info("There is 1 backup file found from your request");
        } else {
            $this->table($headers, $backups);
            $this->line('');
            $this->info("There are {$total} backup files found from your request");
        }
        
        return 0;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            [
                'latest',
                'l',
                InputOption::VALUE_NONE,
                'Only get latest version from backup files.',
            ],
        ];
    }
}
