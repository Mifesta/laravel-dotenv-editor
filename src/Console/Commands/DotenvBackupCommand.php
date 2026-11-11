<?php

namespace Mifesta\DotenvEditor\Console\Commands;

use Illuminate\Console\Command;
use Mifesta\DotenvEditor\Console\Traits\CreateCommandInstanceTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DotenvBackupCommand
 *
 * @package Mifesta\DotenvEditor\Console\Commands
 */
class DotenvBackupCommand extends Command
{
    use CreateCommandInstanceTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dotenv:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup the .env file';

    /**
     * The .env file path
     *
     * @var string|null
     */
    protected $filePath = null;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function fire()
    {
        $filePath = $this->stringToType($this->option('filepath'));
        $this->filePath = (is_string($filePath)) ? base_path($filePath) : null;

        $this->line('Backing up your file...');

        $backup = $this->editor->load($this->filePath)->backup()->getLatestBackup();

        if ($backup === null) {
            $this->error('The backup file was not created.');

            return 1;
        }

        $this->info("Your file was backed up successfully at path [{$backup['filepath']}].");
        
        return 0;
    }

    /**
     * Convert string to corresponding type
     *
     * @param string $string
     *
     * @return mixed
     */
    protected function stringToType($string)
    {
        if (is_string($string)) {
            switch (true) {
                case ($string == 'null' || $string == 'NULL'):
                    $string = null;
                    break;

                case ($string == 'true' || $string == 'TRUE'):
                    $string = true;
                    break;

                case ($string == 'false' || $string == 'FALSE'):
                    $string = false;
                    break;

                default:
                    break;
            }
        }

        return $string;
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
                'filepath',
                null,
                InputOption::VALUE_OPTIONAL,
                'The file path will be backed up. Do not use if you want to backup file .env at root application folder.',
            ],
        ];
    }
}
