<?php

namespace Mifesta\DotenvEditor;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Mifesta\DotenvEditor\Exceptions\FileNotFoundException;
use Mifesta\DotenvEditor\Exceptions\KeyNotFoundException;
use Mifesta\DotenvEditor\Exceptions\NoBackupAvailableException;
use Mifesta\DotenvEditor\Exceptions\UnableWriteToFileException;

/**
 * The DotenvEditor class.
 */
class DotenvEditor
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var DotenvFormatter
     */
    protected $formatter;

    /**
     * @var DotenvReader
     */
    protected $reader;

    /**
     * @var DotenvWriter
     */
    protected $writer;

    /**
     * @var string|null
     */
    protected $filePath;

    /**
     * @var bool
     */
    protected $autoBackup;

    /**
     * @var string
     */
    protected $backupPath;

    const BACKUP_FILENAME_PREFIX = '.env.backup_';

    const BACKUP_FILENAME_SUFFIX = '';

    /**
     * @param Container $app
     * @param Config $config
     */
    public function __construct(Container $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
        $this->formatter = new DotenvFormatter;
        $this->reader = new DotenvReader($this->formatter);
        $this->writer = new DotenvWriter($this->formatter);

        $backupPath = $this->config->get('dotenv-editor.backupPath');

        if ($backupPath === null || $backupPath === '') {
            if (function_exists('base_path')) {
                $backupPath = base_path('storage/dotenv-editor/backups/');
            } else {
                $backupPath = __DIR__.'/../../../../storage/dotenv-editor/backups/';
            }
        }

        $this->backupPath = rtrim($backupPath, "/\\").DIRECTORY_SEPARATOR;
        $this->ensureBackupDirectory();
        $this->autoBackup = (bool) $this->config->get('dotenv-editor.autoBackup', true);
        $this->load();
    }

    /**
     * Load file for working
     *
     * @param string|null $filePath
     * @param bool $restoreIfNotFound
     * @param string|null $restorePath
     * @return DotenvEditor
     */
    public function load($filePath = null, $restoreIfNotFound = false, $restorePath = null)
    {
        $this->resetContent();

        if ($filePath !== null) {
            $this->filePath = $filePath;
        } elseif (method_exists($this->app, 'environmentPath') && method_exists($this->app, 'environmentFile')) {
            $this->filePath = $this->app->environmentPath().DIRECTORY_SEPARATOR.$this->app->environmentFile();
        } else {
            $this->filePath = __DIR__.'/../../../../.env';
        }

        $this->reader->load($this->filePath);

        if (is_file($this->filePath)) {
            $this->writer->setBuffer($this->getContent());

            return $this;
        }

        if ($restoreIfNotFound) {
            return $this->restore($restorePath);
        }

        $this->writer->setBuffer('');

        return $this;
    }

    /**
     * @return void
     */
    protected function resetContent()
    {
        $this->filePath = null;
        $this->reader->load(null);
        $this->writer->setBuffer('');
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->reader->content();
    }

    /**
     * @return array
     */
    public function getLines()
    {
        return $this->reader->lines();
    }

    /**
     * @param array $keys
     * @return array
     */
    public function getKeys(array $keys = [])
    {
        $allKeys = $this->reader->keys();

        return array_filter($allKeys, function ($key) use ($keys) {
            if (! empty($keys)) {
                return in_array($key, $keys, true);
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param string $key
     * @return bool
     */
    public function keyExists($key)
    {
        return array_key_exists($key, $this->getKeys());
    }

    /**
     * @param string $key
     * @return string
     * @throws KeyNotFoundException
     */
    public function getValue($key)
    {
        $allKeys = $this->getKeys([$key]);

        if (array_key_exists($key, $allKeys)) {
            return $allKeys[$key]['value'];
        }

        throw new KeyNotFoundException('Requested key not found in your file.');
    }

    /**
     * @return string
     */
    public function getBuffer()
    {
        return $this->writer->getBuffer();
    }

    /**
     * @return DotenvEditor
     */
    public function addEmpty()
    {
        $this->writer->appendEmptyLine();

        return $this;
    }

    /**
     * @param string $comment
     * @return DotenvEditor
     */
    public function addComment($comment)
    {
        $this->writer->appendCommentLine($comment);

        return $this;
    }

    /**
     * @param array $data
     * @return DotenvEditor
     */
    public function setKeys(array $data)
    {
        foreach ($data as $setter) {
            if (! is_array($setter) || ! array_key_exists('key', $setter)) {
                continue;
            }

            $key = $this->formatter->formatKey($setter['key']);

            if ($key === '') {
                continue;
            }

            $value = array_key_exists('value', $setter) ? $setter['value'] : null;
            $comment = array_key_exists('comment', $setter) ? $setter['comment'] : null;
            $export = array_key_exists('export', $setter) ? $setter['export'] : null;
            $existing = $this->findBufferedSetter($key);

            if ($existing === null) {
                $this->writer->appendSetter($key, $value, $comment, $export === null ? false : (bool) $export);
                continue;
            }

            if ($comment === null) {
                $comment = $existing['comment'];
            }

            if ($export === null) {
                $export = $existing['export'];
            }

            $this->writer->updateSetter($key, $value, $comment, (bool) $export);
        }

        return $this;
    }

    /**
     * Set one key in the buffer.
     *
     * A null comment keeps the comment already stored for that key.
     * A null export flag keeps the current export prefix on update and omits it for a new key.
     *
     * @param string $key
     * @param string|null $value
     * @param string|null $comment
     * @param bool|null $export
     * @return DotenvEditor
     */
    public function setKey($key, $value = null, $comment = null, $export = null)
    {
        return $this->setKeys([compact('key', 'value', 'comment', 'export')]);
    }

    /**
     * @param array $keys
     * @return DotenvEditor
     */
    public function deleteKeys(array $keys = [])
    {
        foreach ($keys as $key) {
            $this->writer->deleteSetter($this->formatter->formatKey($key));
        }

        return $this;
    }

    /**
     * @param string $key
     * @return DotenvEditor
     */
    public function deleteKey($key)
    {
        return $this->deleteKeys([$key]);
    }

    /**
     * @return DotenvEditor
     */
    public function save()
    {
        if (is_file($this->filePath) && $this->autoBackup) {
            $this->backup();
        }

        $this->writer->save($this->filePath);

        return $this;
    }

    /**
     * @param bool $on
     * @return DotenvEditor
     */
    public function autoBackup($on = true)
    {
        $this->autoBackup = $on;

        return $this;
    }

    /**
     * @return DotenvEditor
     * @throws FileNotFoundException
     * @throws UnableWriteToFileException
     */
    public function backup()
    {
        if (! is_file($this->filePath)) {
            throw new FileNotFoundException('File does not exist at path '.$this->filePath);
        }

        if (! $this->ensureBackupDirectory() || ! is_writable($this->backupPath)) {
            throw new UnableWriteToFileException('Unable to write a backup in '.$this->backupPath);
        }

        $stamp = date('Y_m_d_His');
        $sequence = 0;

        do {
            if ($sequence > 1000) {
                throw new UnableWriteToFileException('Unable to choose a backup file name.');
            }

            $suffix = $sequence === 0 ? '' : '_'.$sequence;
            $destination = $this->backupPath.self::BACKUP_FILENAME_PREFIX.$stamp.$suffix.self::BACKUP_FILENAME_SUFFIX;
            $sequence++;
        } while (is_file($destination));

        if (! @copy($this->filePath, $destination)) {
            throw new UnableWriteToFileException('Unable to copy the backup to '.$destination);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getBackups()
    {
        if (! is_dir($this->backupPath)) {
            return [];
        }

        $entries = scandir($this->backupPath);

        if ($entries === false) {
            return [];
        }

        $rows = [];

        foreach ($entries as $backup) {
            if (! preg_match($this->backupFilenamePattern(), $backup, $matches)) {
                continue;
            }

            $rows[] = [
                'filename' => $backup,
                'filepath' => $this->backupPath.$backup,
                'created_at' => $matches[1].'-'.$matches[2].'-'.$matches[3].' '.$matches[4].':'.$matches[5].':'.$matches[6],
                'stamp' => $matches[1].$matches[2].$matches[3].$matches[4].$matches[5].$matches[6],
                'seq' => isset($matches[7]) && $matches[7] !== '' ? (int) $matches[7] : 0,
            ];
        }

        usort($rows, function ($left, $right) {
            if ($left['stamp'] === $right['stamp']) {
                return $left['seq'] <=> $right['seq'];
            }

            return strcmp($left['stamp'], $right['stamp']);
        });

        $output = [];

        foreach ($rows as $row) {
            $output[] = [
                'filename' => $row['filename'],
                'filepath' => $row['filepath'],
                'created_at' => $row['created_at'],
            ];
        }

        return $output;
    }

    /**
     * @return array|null
     */
    public function getLatestBackup()
    {
        $backups = $this->getBackups();

        if ($backups === []) {
            return null;
        }

        return $backups[count($backups) - 1];
    }

    /**
     * @param string|null $filePath
     * @return DotenvEditor
     * @throws FileNotFoundException
     * @throws NoBackupAvailableException
     * @throws UnableWriteToFileException
     */
    public function restore($filePath = null)
    {
        if ($filePath === null) {
            $latestBackup = $this->getLatestBackup();

            if ($latestBackup === null) {
                throw new NoBackupAvailableException('There are no available backups!');
            }

            $filePath = $latestBackup['filepath'];
        }

        if (! is_file($filePath)) {
            throw new FileNotFoundException('File does not exist at path '.$filePath);
        }

        $directory = dirname($this->filePath);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new UnableWriteToFileException('Unable to restore the file to '.$this->filePath);
        }

        if (! @copy($filePath, $this->filePath)) {
            throw new UnableWriteToFileException('Unable to restore the file to '.$this->filePath);
        }

        $this->writer->setBuffer($this->getContent());

        return $this;
    }

    /**
     * @param array $filePaths
     * @return DotenvEditor
     */
    public function deleteBackups(array $filePaths = [])
    {
        if ($filePaths === []) {
            foreach ($this->getBackups() as $backup) {
                $filePaths[] = $backup['filepath'];
            }
        }

        foreach ($filePaths as $filePath) {
            if (is_file($filePath)) {
                unlink($filePath);
            }
        }

        return $this;
    }

    /**
     * @param string $filePath
     * @return DotenvEditor
     */
    public function deleteBackup($filePath)
    {
        return $this->deleteBackups([$filePath]);
    }

    /**
     * Look at the unsaved buffer, not the file on disk.
     *
     * @param string $key
     * @return array|null
     */
    protected function findBufferedSetter($key)
    {
        $buffer = $this->writer->getBuffer();

        if ($buffer === '') {
            return null;
        }

        foreach (preg_split("/\r\n|\n|\r/", $buffer) as $line) {
            $parsed = $this->formatter->parseLine($line);

            if ($parsed['type'] === 'setter' && $parsed['key'] === $key) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return bool
     */
    protected function ensureBackupDirectory()
    {
        if (! is_dir($this->backupPath) && ! @mkdir($this->backupPath, 0755, true) && ! is_dir($this->backupPath)) {
            return false;
        }

        $gitignore = dirname(rtrim($this->backupPath, "/\\")).DIRECTORY_SEPARATOR.'.gitignore';

        if (! is_file($gitignore)) {
            @copy(__DIR__.'/../stubs/gitignore.txt', $gitignore);
        }

        return true;
    }

    /**
     * @return string
     */
    protected function backupFilenamePattern()
    {
        $prefix = preg_quote(self::BACKUP_FILENAME_PREFIX, '/');
        $suffix = preg_quote(self::BACKUP_FILENAME_SUFFIX, '/');

        return '/^'.$prefix.'(\d{4})_(\d{2})_(\d{2})_(\d{2})(\d{2})(\d{2})(?:_(\d+))?'.$suffix.'$/';
    }
}
