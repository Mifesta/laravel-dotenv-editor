<?php

namespace Mifesta\DotenvEditor;

use Mifesta\DotenvEditor\Contracts\DotenvFormatter as DotenvFormatterContract;
use Mifesta\DotenvEditor\Contracts\DotenvReader as DotenvReaderContract;
use Mifesta\DotenvEditor\Exceptions\UnableReadFileException;

/**
 * The DotenvReader class.
 */
class DotenvReader implements DotenvReaderContract
{
    /**
     * @var string|null
     */
    protected $filePath;

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
     * @param string|null $filePath
     * @return DotenvReader
     */
    public function load($filePath)
    {
        $this->filePath = $filePath;

        return $this;
    }

    /**
     * @return void
     * @throws UnableReadFileException
     */
    protected function ensureFileIsReadable()
    {
        if (! is_string($this->filePath) || ! is_file($this->filePath) || ! is_readable($this->filePath)) {
            $location = is_string($this->filePath) ? $this->filePath : '';

            throw new UnableReadFileException(sprintf('Unable to read the file at %s.', $location));
        }
    }

    /**
     * @return string
     * @throws UnableReadFileException
     */
    public function content()
    {
        $this->ensureFileIsReadable();
        $content = file_get_contents($this->filePath);

        if ($content === false) {
            throw new UnableReadFileException(sprintf('Unable to read the file at %s.', $this->filePath));
        }

        return $content;
    }

    /**
     * @return array
     * @throws UnableReadFileException
     */
    public function lines()
    {
        $content = [];

        foreach ($this->readLinesFromFile() as $row => $line) {
            $content[] = [
                'line' => $row + 1,
                'raw_data' => $line,
                'parsed_data' => $this->formatter->parseLine($line),
            ];
        }

        return $content;
    }

    /**
     * @return array
     * @throws UnableReadFileException
     */
    public function keys()
    {
        $content = [];

        foreach ($this->readLinesFromFile() as $row => $line) {
            $data = $this->formatter->parseLine($line);

            if ($data['type'] === 'setter') {
                $content[$data['key']] = [
                    'line' => $row + 1,
                    'export' => $data['export'],
                    'value' => $data['value'],
                    'comment' => $data['comment'],
                ];
            }
        }

        return $content;
    }

    /**
     * Read content into lines. Line endings are handled here so the deprecated
     * auto_detect_line_endings ini setting is not required.
     *
     * @return array
     * @throws UnableReadFileException
     */
    protected function readLinesFromFile()
    {
        $content = $this->content();

        if ($content === '') {
            return [];
        }

        $content = str_replace("\r\n", "\n", $content);
        $content = str_replace("\r", "\n", $content);

        if (substr($content, -1) === "\n") {
            $content = substr($content, 0, -1);
        }

        return explode("\n", $content);
    }
}
