<?php

namespace Mifesta\DotenvEditor\Tests;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Mifesta\DotenvEditor\DotenvEditor;
use PHPUnit\Framework\TestCase;

abstract class PackageTestCase extends TestCase
{
    /**
     * @var string
     */
    protected $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'dotenv-editor-'.bin2hex(random_bytes(4));
        mkdir($this->root.DIRECTORY_SEPARATOR.'env', 0777, true);
        mkdir($this->root.DIRECTORY_SEPARATOR.'bak', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);

        parent::tearDown();
    }

    /**
     * @param bool $autoBackup
     * @return DotenvEditor
     */
    protected function editor($autoBackup = false)
    {
        $root = $this->root;
        $config = new Repository([
            'dotenv-editor' => [
                'backupPath' => $root.DIRECTORY_SEPARATOR.'bak',
                'autoBackup' => $autoBackup,
            ],
        ]);
        $app = new class($root.DIRECTORY_SEPARATOR.'env') extends Container {
            private $envPath;

            public function __construct($envPath)
            {
                $this->envPath = $envPath;
            }

            public function environmentPath()
            {
                return $this->envPath;
            }

            public function environmentFile()
            {
                return '.env';
            }
        };

        return new DotenvEditor($app, $config);
    }

    /**
     * @param string $contents
     * @param string $name
     * @return string
     */
    protected function putEnv($contents, $name = '.env')
    {
        $path = $this->root.DIRECTORY_SEPARATOR.'env'.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @param callable $callback
     * @return mixed
     */
    protected function withoutDeprecations(callable $callback)
    {
        $warnings = [];
        set_error_handler(function ($severity, $message) use (&$warnings) {
            if (($severity & (E_DEPRECATED | E_USER_DEPRECATED | E_WARNING | E_USER_WARNING)) !== 0) {
                $warnings[] = $message;
            }

            return true;
        });

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings, implode("\n", $warnings));

        return $result;
    }

    /**
     * @param string $path
     * @return void
     */
    private function deleteTree($path)
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @chmod($item->getPathname(), 0666);
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
