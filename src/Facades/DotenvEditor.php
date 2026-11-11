<?php

namespace Mifesta\DotenvEditor\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The DotenvEditor facade.
 *
 * @method static \Mifesta\DotenvEditor\DotenvEditor load(string|null $filePath = null, bool $restoreIfNotFound = false, string|null $restorePath = null)
 *
 * Working with reading
 * @method static string getContent()
 * @method static array getLines()
 * @method static array getKeys()
 * @method static bool keyExists(string $key)
 * @method static string getValue(string $key)
 *
 * Working with writing
 * @method static string getBuffer()
 * @method static \Mifesta\DotenvEditor\DotenvEditor addEmpty()
 * @method static \Mifesta\DotenvEditor\DotenvEditor addComment(string $comment)
 * @method static \Mifesta\DotenvEditor\DotenvEditor setKeys(array $data)
 * @method static \Mifesta\DotenvEditor\DotenvEditor setKey(string $key, string|null $value = null, string|null $comment = null, bool|null $export = null)
 * @method static \Mifesta\DotenvEditor\DotenvEditor deleteKeys(array $keys = [])
 * @method static \Mifesta\DotenvEditor\DotenvEditor deleteKey(string $key)
 * @method static \Mifesta\DotenvEditor\DotenvEditor save()
 *
 * Working with backups
 * @method static \Mifesta\DotenvEditor\DotenvEditor autoBackup(bool $on = true)
 * @method static \Mifesta\DotenvEditor\DotenvEditor backup()
 * @method static array getBackups()
 * @method static array getLatestBackup()
 * @method static \Mifesta\DotenvEditor\DotenvEditor restore(string|null $filePath = null)
 * @method static \Mifesta\DotenvEditor\DotenvEditor deleteBackups(array $filePaths = [])
 * @method static \Mifesta\DotenvEditor\DotenvEditor deleteBackup($filePath)
 *
 * @package Mifesta\DotenvEditor\Facades
 */
class DotenvEditor extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'dotenv-editor';
    }
}
