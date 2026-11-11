<?php

namespace Mifesta\DotenvEditor;

use Mifesta\DotenvEditor\Contracts\DotenvFormatter as DotenvFormatterContract;
use Mifesta\DotenvEditor\Contracts\DotenvWriter as DotenvWriterContract;
use Mifesta\DotenvEditor\Exceptions\UnableWriteToFileException;

/**
 * The DotenvWriter writer.
 */
class DotenvWriter implements DotenvWriterContract
{
    /**
     * @var string
     */
    protected $buffer = '';

    /**
     * @var DotenvFormatterContract
     */
    protected $formatter;

    /**
     * @param DotenvFormatterContract $formatter
     */
    public function __construct(DotenvFormatterContract $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * @param string $filePath
     * @return void
     * @throws UnableWriteToFileException
     */
    protected function ensureFileIsWritable($filePath)
    {
        $exists = is_file($filePath);

        if (($exists && ! is_writable($filePath)) || (! $exists && ! is_writable(dirname($filePath)))) {
            throw new UnableWriteToFileException(sprintf('Unable to write to the file at %s.', $filePath));
        }
    }

    /**
     * @param string|null $content
     * @return DotenvWriter
     */
    public function setBuffer($content)
    {
        $this->buffer = $content === null ? '' : (string) $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getBuffer()
    {
        return $this->buffer;
    }

    /**
     * @return string
     */
    protected function detectEol()
    {
        if (strpos($this->buffer, "\r\n") !== false) {
            return "\r\n";
        }

        return "\n";
    }

    /**
     * @param string|null $text
     * @return DotenvWriter
     */
    protected function appendLine($text = null)
    {
        $this->buffer .= ($text === null ? '' : $text).$this->detectEol();

        return $this;
    }

    /**
     * @return DotenvWriter
     */
    public function appendEmptyLine()
    {
        return $this->appendLine();
    }

    /**
     * @param string $comment
     * @return DotenvWriter
     */
    public function appendCommentLine($comment)
    {
        return $this->appendLine('# '.$comment);
    }

    /**
     * @param string $key
     * @param string|null $value
     * @param string|null $comment
     * @param bool $export
     * @return DotenvWriter
     */
    public function appendSetter($key, $value = null, $comment = null, $export = false)
    {
        return $this->appendLine($this->formatter->formatSetterLine($key, $value, $comment, $export));
    }

    /**
     * @param string $key
     * @param string|null $value
     * @param string|null $comment
     * @param bool $export
     * @return DotenvWriter
     * @throws UnableWriteToFileException
     */
    public function updateSetter($key, $value = null, $comment = null, $export = false)
    {
        $line = $this->formatter->formatSetterLine($key, $value, $comment, $export);
        $updated = preg_replace_callback($this->setterPattern($key, false), function () use ($line) {
            return $line;
        }, $this->buffer);

        if (! is_string($updated)) {
            throw new UnableWriteToFileException('Unable to update ['.$key.'] in the buffer.');
        }

        $this->buffer = $updated;

        return $this;
    }

    /**
     * @param string $key
     * @return DotenvWriter
     * @throws UnableWriteToFileException
     */
    public function deleteSetter($key)
    {
        $updated = preg_replace($this->setterPattern($key, true), '', $this->buffer);

        if (! is_string($updated)) {
            throw new UnableWriteToFileException('Unable to delete ['.$key.'] from the buffer.');
        }

        $this->buffer = $updated;

        return $this;
    }

    /**
     * The replacement is applied by a callback so backslashes and dollars in
     * the new line stay literal. The key is quoted so it cannot change the pattern.
     *
     * @param string $key
     * @param bool $consumeBreak
     * @return string
     */
    protected function setterPattern($key, $consumeBreak)
    {
        $quoted = preg_quote($key, '/');
        $break = $consumeBreak ? '(?:\r\n|\n|\r|$)' : '';

        return '/^(?:export\h+)?\h*'.$quoted.'\h*=[^\r\n]*'.$break.'/m';
    }

    /**
     * @param string $filePath
     * @return DotenvWriter
     * @throws UnableWriteToFileException
     */
    public function save($filePath)
    {
        $this->ensureFileIsWritable($filePath);

        if (file_put_contents($filePath, $this->buffer) === false) {
            throw new UnableWriteToFileException(sprintf('Unable to write to the file at %s.', $filePath));
        }

        return $this;
    }
}
