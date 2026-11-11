<?php

namespace Mifesta\DotenvEditor\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Mifesta\DotenvEditor\Console\Traits\CreateCommandInstanceTrait;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class DotenvSetKeyCommand
 *
 * @package Mifesta\DotenvEditor\Console\Commands
 */
class DotenvSetKeyCommand extends Command
{
    use ConfirmableTrait, CreateCommandInstanceTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'dotenv:set-key';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add new or update one setter into the .env file';

    /**
     * The .env file path
     *
     * @var string|null
     */
    protected $filePath = null;

    /**
     * Determine restoring the .env file if not exists
     *
     * @var bool
     */
    protected $forceRestore = false;

    /**
     * The file path should use to restore
     *
     * @var string|null
     */
    protected $restorePath = null;

    /**
     * The key name use to add or update
     *
     * @var string
     */
    protected $key = 'NEW_ENV_KEY';

    /**
     * Value of key
     *
     * @var mixed
     */
    protected $value = null;

    /**
     * Comment for key
     *
     * @var mixed
     */
    protected $comment = null;

    /**
     * Determine leading the key with 'export '. Null keeps the current prefix.
     *
     * @var bool|null
     */
    protected $exportKey = null;

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

        $this->line('Setting key in your file...');

        $this->editor->load($this->filePath, $this->forceRestore, $this->restorePath);
        $this->editor->setKey($this->key, $this->value, $this->comment, $this->exportKey);
        $this->editor->save();

        $shown = $this->value === null ? '' : $this->value;
        $this->info("The key [{$this->key}] is set to [{$shown}].");
        
        return 0;
    }

    /**
     * Transfer inputs to properties of editing
     */
    protected function transferInputsToProperties()
    {
        $filePath = $this->stringToType($this->option('filepath'));
        $this->filePath = (is_string($filePath)) ? base_path($filePath) : null;

        $this->forceRestore = $this->option('restore');

        $restorePath = $this->stringToType($this->option('restore-path'));
        $this->restorePath = (is_string($restorePath)) ? base_path($restorePath) : null;

        $this->key = $this->argument('key');

        $value = $this->argument('value');
        $this->value = $value === null ? null : (string) $value;

        $comment = $this->argument('comment');
        if (is_string($comment) && ($comment === 'false' || $comment === 'FALSE')) {
            $this->comment = '';
        } else {
            $this->comment = $comment === null ? null : (string) $comment;
        }

        $this->exportKey = $this->option('export-key') ? true : null;
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
        return [
            [
                'key',
                InputArgument::REQUIRED,
                'Key name will be added or updated.',
            ],
            [
                'value',
                InputArgument::OPTIONAL,
                'Value want to set for this key.',
            ],
            [
                'comment',
                InputArgument::OPTIONAL,
                'Comment want to set for this key. Type "false" to clear comment for exists key.',
            ],
        ];
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
                'The file path should use to load for working. Do not use if you want to load file .env at root application folder.',
            ],
            [
                'restore',
                'r',
                InputOption::VALUE_NONE,
                'Restore the loaded file from backup or special file if the loaded file is not found.',
            ],
            [
                'restore-path',
                null,
                InputOption::VALUE_OPTIONAL,
                'The special file path should use to restore from. Do not use if you want to restore from latest backup file.',
            ],
            [
                'export-key',
                'e',
                InputOption::VALUE_NONE,
                'Leading before key name with "export " command.',
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
