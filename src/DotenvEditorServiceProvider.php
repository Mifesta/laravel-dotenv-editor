<?php

namespace Mifesta\DotenvEditor;

use Illuminate\Support\ServiceProvider;
use Mifesta\DotenvEditor\Console\Commands\DotenvBackupCommand;
use Mifesta\DotenvEditor\Console\Commands\DotenvDeleteKeyCommand;
use Mifesta\DotenvEditor\Console\Commands\DotenvGetBackupsCommand;
use Mifesta\DotenvEditor\Console\Commands\DotenvGetKeysCommand;
use Mifesta\DotenvEditor\Console\Commands\DotenvRestoreCommand;
use Mifesta\DotenvEditor\Console\Commands\DotenvSetKeyCommand;

class DotenvEditorServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'dotenv-editor');
        $this->app->bind('dotenv-editor', DotenvEditor::class);
        $this->registerCommands();
    }

    /**
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/config.php' => config_path('dotenv-editor.php'),
        ], 'config');
    }

    /**
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            DotenvBackupCommand::class,
            DotenvDeleteKeyCommand::class,
            DotenvGetBackupsCommand::class,
            DotenvGetKeysCommand::class,
            DotenvRestoreCommand::class,
            DotenvSetKeyCommand::class,
        ]);
    }
}
