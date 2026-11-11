<?php

namespace Mifesta\DotenvEditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Mifesta\DotenvEditor\Console\Traits\CreateCommandInstanceTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DotenvRestoreCommand
 *
 * @package Mifesta\DotenvEditor\Console\Commands
 */
class DotenvRestoreCommand extends Command
{
    use ConfirmableTrait, CreateCommandInstanceTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $name = 'dotenv:restore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore the .env file from backup or special file';

    /**
     * The .env file path
     *
     * @var string|null
     */
    protected $filePath = null;

    /**
     * The file path should use to restore
     *
     * @var string|null
     */
    protected $restorePath = null;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function fire()
    {
        $this->transferInputsToProperties();

        if (! $this->confirmToProceed()) {
            return 1;
        }

        $this->line('Restoring your file...');

        $this->editor->load($this->filePath)->restore($this->restorePath);

        $this->info("Your file is restored successfully");
        
        return 0;
    }

    /**
     * Transfer inputs to properties of editing
     */
    protected function transferInputsToProperties()
    {
        $filePath = $this->stringToType($this->option('filepath'));
        $this->filePath = (is_string($filePath)) ? base_path($filePath) : null;

        $restorePath = $this->stringToType($this->option('restore-path'));
        $this->restorePath = (is_string($restorePath)) ? base_path($restorePath) : null;
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
                'The .env file path will be restored. Do not use if you want to restore file .env at root application folder.',
            ],
            [
                'restore-path',
                null,
                InputOption::VALUE_OPTIONAL,
                'The special file path should use to restore. Do not use if you want to restore from latest backup file.',
            ],
            [
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force the operation to run when in production.',
            ],
        ];
    }
}
