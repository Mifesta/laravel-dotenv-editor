<?php

namespace Mifesta\DotenvEditor\Tests;

use Mifesta\DotenvEditor\DotenvFormatter;
use Mifesta\DotenvEditor\Exceptions\InvalidValueException;
use Mifesta\DotenvEditor\Exceptions\KeyNotFoundException;
use Mifesta\DotenvEditor\Exceptions\NoBackupAvailableException;
use Mifesta\DotenvEditor\Exceptions\UnableWriteToFileException;

class EditorTest extends PackageTestCase
{
    public function testReadsANormalEnvFile()
    {
        $path = $this->putEnv(implode("\n", [
            'APP_NAME=Laravel',
            'APP_ENV=local',
            'APP_KEY=base64:abc+def/ghi=',
            'APP_DEBUG=true',
            'APP_URL=http://localhost',
            '',
            '# Database',
            'DB_CONNECTION=mysql',
            'DB_PASSWORD=',
            'DB_SECRET="p@ss #word"',
            '',
            'export CACHE_DRIVER=file',
            'ZERO=0',
            '',
        ]));

        $editor = $this->editor();
        $keys = $this->withoutDeprecations(function () use ($editor, $path) {
            $editor->load($path);

            return $editor->getKeys();
        });

        $this->assertSame('http://localhost', $editor->getValue('APP_URL'));
        $this->assertSame('', $editor->getValue('DB_PASSWORD'));
        $this->assertSame('0', $editor->getValue('ZERO'));
        $this->assertSame('p@ss #word', $editor->getValue('DB_SECRET'));
        $this->assertTrue($keys['CACHE_DRIVER']['export']);
        $this->assertSame(['APP_URL'], array_keys($editor->getKeys(['APP_URL', 'MISSING'])));

        try {
            $editor->getValue('NOPE');
            $this->fail('Missing key should throw.');
        } catch (KeyNotFoundException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }

    public function testPlainUpdateKeepsTheRestOfTheFileAndTheComment()
    {
        $body = "APP_NAME=Laravel\nAPP_URL=http://localhost # local\nDB_PASSWORD=\n";
        $path = $this->putEnv($body);
        $editor = $this->editor();

        $this->withoutDeprecations(function () use ($editor, $path) {
            $editor->load($path)->setKey('APP_URL', 'https://example.com')->save();
        });

        $this->assertSame(
            "APP_NAME=Laravel\nAPP_URL=https://example.com # local\nDB_PASSWORD=\n",
            file_get_contents($path)
        );

        $editor->load($path)->setKey('APP_URL', 'https://example.com', '')->save();
        $this->assertSame(
            "APP_NAME=Laravel\nAPP_URL=https://example.com\nDB_PASSWORD=\n",
            file_get_contents($path)
        );
    }

    public function testUpdateKeepsExportUntilItIsTurnedOff()
    {
        $path = $this->putEnv("export CACHE_DRIVER=file\n");
        $editor = $this->editor();
        $editor->load($path)->setKey('CACHE_DRIVER', 'redis')->save();
        $this->assertSame("export CACHE_DRIVER=redis\n", file_get_contents($path));

        $editor->load($path)->setKey('CACHE_DRIVER', 'array', null, false)->save();
        $this->assertSame("CACHE_DRIVER=array\n", file_get_contents($path));
    }

    public function testRepeatedSetBeforeSaveUpdatesTheSameKey()
    {
        $path = $this->putEnv("A=1\n");
        $editor = $this->editor();
        $editor->load($path)->setKey('NEWK', '1')->setKey('NEWK', '2')->save();

        $this->assertSame("A=1\nNEWK=2\n", file_get_contents($path));
    }

    public function testDeleteThenSetWritesTheKeyAgain()
    {
        $path = $this->putEnv("FOO=1\nKEEP=yes\n");
        $editor = $this->editor();
        $editor->load($path)->deleteKey('FOO')->setKey('FOO', '2')->save();

        $this->assertSame("KEEP=yes\nFOO=2\n", file_get_contents($path));
    }

    public function testDeleteRemovesTheLastLineWithoutANewline()
    {
        $path = $this->putEnv("A=1\nB=2");
        $editor = $this->editor();
        $editor->load($path)->deleteKey('B')->save();

        $this->assertSame("A=1\n", file_get_contents($path));
    }

    public function testDeleteRemovesAMiddleKey()
    {
        $path = $this->putEnv("A=1\nB=2\nC=3\n");
        $editor = $this->editor();
        $editor->load($path)->deleteKey('B')->save();

        $this->assertSame("A=1\nC=3\n", file_get_contents($path));
    }

    public function testSpacesAroundEqualsAreUpdated()
    {
        $path = $this->putEnv("FOO = bar\n");
        $editor = $this->editor();
        $editor->load($path);
        $this->assertSame('bar', $editor->getValue('FOO'));
        $editor->setKey('FOO', 'baz')->save();

        $this->assertSame("FOO=baz\n", file_get_contents($path));
    }

    public function testCrlfLineEndingSurvivesAnUpdate()
    {
        $path = $this->putEnv("FOO=bar\r\nNEXT=1\r\n");
        $editor = $this->editor();
        $editor->load($path)->setKey('FOO', 'baz')->save();

        $this->assertSame("FOO=baz\r\nNEXT=1\r\n", file_get_contents($path));
    }

    public function testWindowsPathStaysSafelyEscapedAcrossUpdates()
    {
        $path = $this->putEnv("OTHER=1\n");
        $value = 'C:\\certs\\app.pem';
        $editor = $this->editor();

        $this->withoutDeprecations(function () use ($editor, $path, $value) {
            $editor->load($path)->setKey('CERT_PATH', $value)->save();
            $editor->load($path)->setKey('CERT_PATH', $value)->save();
        });

        $this->assertStringContainsString('CERT_PATH="C:\\\\certs\\\\app.pem"', file_get_contents($path));
        $this->assertSame($value, $editor->load($path)->getValue('CERT_PATH'));
        $this->assertTrue($this->phpdotenvAccepts(file_get_contents($path), 'CERT_PATH', $value));
    }

    public function testTwoBackslashesStayStableWhenTheReadValueIsSavedAgain()
    {
        $path = $this->putEnv("OTHER=1\n");
        $value = 'a\\\\b';
        $editor = $this->editor();
        $editor->load($path)->setKey('V', $value)->save();
        $once = $editor->load($path)->getValue('V');
        $editor->setKey('V', $once)->save();

        $this->assertSame($value, $once);
        $this->assertSame($value, $editor->load($path)->getValue('V'));
    }

    public function testQuotesAndDollarsRoundTrip()
    {
        $path = $this->putEnv("OTHER=1\n");
        $editor = $this->editor();
        $editor->load($path)->setKey('QUOTE', 'say "hi"')->setKey('SECRET', 'p@ss$w0rd')->save();
        $first = file_get_contents($path);
        $editor->load($path)->setKey('QUOTE', 'say "hi"')->setKey('SECRET', 'p@ss$w0rd')->save();

        $this->assertSame($first, file_get_contents($path));
        $this->assertSame('say "hi"', $editor->load($path)->getValue('QUOTE'));
        $this->assertSame('p@ss$w0rd', $editor->getValue('SECRET'));
    }

    public function testDottedKeyDoesNotRewriteAnotherKey()
    {
        $path = $this->putEnv("APP_NAME=Laravel\nAPP.NAME=dotted\n");
        $editor = $this->editor();
        $editor->load($path)->setKey('APP.NAME', 'changed')->save();

        $this->assertSame("APP_NAME=Laravel\nAPP.NAME=changed\n", file_get_contents($path));
    }

    public function testKeysWithRegexCharactersCanBeUpdated()
    {
        $path = $this->putEnv("FOO/BAR=1\nAPP_KEY=secret\nA(B=1\nFOO(BAR)=1\nKEEP=yes\n");
        $editor = $this->editor();
        $editor->load($path)
            ->setKey('FOO/BAR', '2')
            ->setKey('A(B', '2')
            ->setKey('FOO(BAR)', '2')
            ->save();

        $this->assertSame(
            "FOO/BAR=2\nAPP_KEY=secret\nA(B=2\nFOO(BAR)=2\nKEEP=yes\n",
            file_get_contents($path)
        );
    }

    public function testEmptyValueWithInlineCommentDoesNotBlockTheFile()
    {
        $path = $this->putEnv("APP_NAME=Laravel\nFOO= # comment\nAPP_DEBUG=true\n");
        $editor = $this->editor();
        $keys = $editor->load($path)->getKeys();

        $this->assertSame('Laravel', $keys['APP_NAME']['value']);
        $this->assertSame('', $keys['FOO']['value']);
        $this->assertSame('comment', $keys['FOO']['comment']);
        $this->assertSame('true', $keys['APP_DEBUG']['value']);
    }

    public function testUnclosedQuoteIsRejected()
    {
        $path = $this->putEnv("FOO=\"bar\nKEEP=1\n");
        $editor = $this->editor();
        $editor->load($path);

        $this->expectException(InvalidValueException::class);
        $editor->getKeys();
    }

    public function testEmptySetterDoesNotEmitDeprecations()
    {
        $path = $this->putEnv("A=1\n");
        $editor = $this->editor();

        $this->withoutDeprecations(function () use ($editor, $path) {
            $editor->load($path)->setKey('EMPTY_KEY')->save();
        });

        $this->assertSame("A=1\nEMPTY_KEY=\n", file_get_contents($path));
    }

    public function testBooleanValuesAreWrittenAsWords()
    {
        $formatter = new DotenvFormatter();

        $this->assertSame('APP_DEBUG=true', $formatter->formatSetterLine('APP_DEBUG', true));
        $this->assertSame('APP_DEBUG=false', $formatter->formatSetterLine('APP_DEBUG', false));
        $this->assertSame('APP_DEBUG=null', $formatter->formatSetterLine('APP_DEBUG', 'null'));
    }

    public function testBackupAndRestore()
    {
        $path = $this->putEnv("FOO=old\n");
        $editor = $this->editor(true);
        $editor->load($path)->setKey('FOO', 'new')->save();

        $latest = $editor->getLatestBackup();
        $this->assertNotNull($latest);
        $this->assertSame("FOO=old\n", file_get_contents($latest['filepath']));
        $this->assertSame("FOO=new\n", file_get_contents($path));

        $editor->restore();
        $this->assertSame("FOO=old\n", file_get_contents($path));
    }

    public function testTwoBackupsInTheSameSecondBothExist()
    {
        $path = $this->putEnv("FOO=1\n");
        $editor = $this->editor();
        $editor->load($path)->backup();
        file_put_contents($path, "FOO=2\n");
        $editor->backup();

        $backups = $editor->getBackups();
        $latest = $editor->getLatestBackup();

        $this->assertCount(2, $backups);
        $this->assertSame("FOO=2\n", file_get_contents($latest['filepath']));
    }

    public function testLatestBackupIgnoresUnrelatedFiles()
    {
        $path = $this->putEnv("FOO=1\n");
        $editor = $this->editor();
        $editor->load($path);
        file_put_contents($this->root.DIRECTORY_SEPARATOR.'bak'.DIRECTORY_SEPARATOR.'readme.txt', "nope\n");

        $this->assertNull($editor->getLatestBackup());

        $backupDir = $this->root.DIRECTORY_SEPARATOR.'bak'.DIRECTORY_SEPARATOR;
        file_put_contents($backupDir.'.env.backup_2020_01_01_010101', "old\n");
        file_put_contents($backupDir.'.env.backup_2024_05_06_070809', "new\n");

        $latest = $editor->getLatestBackup();
        $this->assertSame('.env.backup_2024_05_06_070809', $latest['filename']);
        $this->assertCount(2, $editor->getBackups());
    }

    public function testRestoreWithoutBackupsThrows()
    {
        $path = $this->putEnv("FOO=1\n");
        $editor = $this->editor();
        $editor->load($path);

        $this->expectException(NoBackupAvailableException::class);
        $editor->restore();
    }

    public function testMissingFileCanBeRestoredFromAGivenPath()
    {
        $source = $this->putEnv("FOO=from-source\n", 'source.env');
        $missing = $this->root.DIRECTORY_SEPARATOR.'env'.DIRECTORY_SEPARATOR.'missing.env';
        $editor = $this->editor();
        $editor->load($missing, true, $source);

        $this->assertSame("FOO=from-source\n", file_get_contents($missing));
    }

    public function testFailedRestoreIsReported()
    {
        $path = $this->putEnv("FOO=keep\n");
        $source = $this->putEnv("FOO=other\n", 'source.env');
        $editor = $this->editor();
        $editor->load($path);
        chmod($path, 0444);

        try {
            $editor->restore($source);
            $this->fail('A failed copy must throw.');
        } catch (UnableWriteToFileException $exception) {
            $this->assertStringContainsString('Unable to restore', $exception->getMessage());
        } finally {
            chmod($path, 0666);
        }

        $this->assertSame("FOO=keep\n", file_get_contents($path));
    }

    /**
     * @param string $file
     * @param string $key
     * @param string $expected
     * @return bool
     */
    private function phpdotenvAccepts($file, $key, $expected)
    {
        $line = '';

        foreach (preg_split("/\r\n|\n|\r/", $file) as $candidate) {
            if (strpos($candidate, $key.'=') === 0) {
                $line = $candidate;
            }
        }

        if (! preg_match('/^'.preg_quote($key, '/').'="(.*)"$/', $line, $matches)) {
            return false;
        }

        $inner = $matches[1];
        $out = '';
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            if ($inner[$i] !== '\\') {
                $out .= $inner[$i];
                continue;
            }

            if ($i + 1 >= $length) {
                return false;
            }

            $next = $inner[++$i];

            if ($next === '"' || $next === '\\' || $next === '$') {
                $out .= $next;
                continue;
            }

            if (in_array($next, ['f', 'n', 'r', 't', 'v'], true)) {
                $out .= stripcslashes('\\'.$next);
                continue;
            }

            return false;
        }

        return $out === $expected;
    }
}
