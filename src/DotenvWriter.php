<?php

namespace Mifesta\DotenvEditor;

use Mifesta\DotenvEditor\Contracts\DotenvFormatter as DotenvFormatterContract;
use Mifesta\DotenvEditor\Contracts\DotenvWriter as DotenvWriterContract;
use Mifesta\DotenvEditor\Exceptions\UnableWriteToFileException;

/**
 * The DotenvWriter writer.
 *
 * @package Mifesta\DotenvEditor
 */
class DotenvWriter implements DotenvWriterContract
{
	/**
	 * The content buffer
	 *
	 * @var string
	 */
	protected $buffer;

	/**
	 * The formatter instance
	 *
	 * @var \Mifesta\DotenvEditor\DotenvFormatter
	 */
	protected $formatter;

	/**
	 * Create a new writer instance
	 *
	 * @param \Mifesta\DotenvEditor\Contracts\DotenvFormatter $formatter
	 */
	public function __construct(DotenvFormatterContract $formatter)
	{
		$this->formatter = $formatter;
	}

	/**
	 * Tests file for write accessibility. If the file doesn't exist, check
	 * the parent directory for write access so the file can be created.
	 *
	 * @param string $file_path
	 * @throws \Mifesta\DotenvEditor\Exceptions\UnableWriteToFileException
	 */
	protected function ensureFileIsWritable($file_path)
	{
		if ((is_file($file_path) && ! is_writable($file_path)) || (! is_file($file_path) && ! is_writable(dirname($file_path)))) {
			throw new UnableWriteToFileException(sprintf('Unable to write to the file at %s.', $file_path));
		}
	}

	/**
	 * Set buffer with content
	 *
	 * @param string $content
	 *
	 * @return DotenvWriter
	 */
	public function setBuffer($content)
	{
		$this->buffer = $content;

		return $this;
	}

	/**
	 * Return content in buffer
	 *
	 * @return string
	 */
	public function getBuffer()
	{
		return $this->buffer;
	}

	/**
	 * Append new line to buffer
	 *
	 * @param string|null $text
	 *
	 * @return DotenvWriter
	 */
	protected function appendLine($text = null)
	{
		$this->buffer .= $text.PHP_EOL;

		return $this;
	}

	/**
	 * Append empty line to buffer
	 *
	 * @return DotenvWriter
	 */
	public function appendEmptyLine()
	{
		return $this->appendLine();
	}

	/**
	 * Append comment line to buffer
	 *
	 * @param string $comment
	 * @return DotenvWriter
	 */
	public function appendCommentLine($comment)
	{
		return $this->appendLine('# '.$comment);
	}

	/**
	 * Append one setter to buffer
	 *
	 * @param string $key
	 * @param string|null $value
	 * @param string|null $comment
	 * @param bool $export
	 * @return DotenvWriter
	 */
	public function appendSetter($key, $value = null, $comment = null, $export = false)
	{
		$line = $this->formatter->formatSetterLine($key, $value, $comment, $export);

		return $this->appendLine($line);
	}

	/**
	 * Update one setter in buffer
	 *
	 * @param string $key
	 * @param string|null $value
	 * @param string|null $comment
	 * @param bool $export
	 * @return DotenvWriter
	 */
	public function updateSetter($key, $value = null, $comment = null, $export = false)
	{
		$pattern = "/^(export\h)?\h*{$key}=.*/m";
		$line = $this->formatter->formatSetterLine($key, $value, $comment, $export);
		$this->buffer = preg_replace($pattern, str_replace('$', '\$', $line), $this->buffer);

		return $this;
	}

	/**
	 * Delete one setter in buffer
	 *
	 * @param string $key
	 * @return DotenvWriter
	 */
	public function deleteSetter($key)
	{
		$pattern = "/^(export\h)?\h*{$key}=.*\n/m";
		$this->buffer = preg_replace($pattern, null, $this->buffer);

		return $this;
	}

	/**
	 * Save buffer to special file path
	 *
	 * @param string $filePath
	 * @return DotenvWriter
	 * @throws \Mifesta\DotenvEditor\Exceptions\UnableWriteToFileException
	 */
	public function save($filePath)
	{
		$this->ensureFileIsWritable($filePath);
		file_put_contents($filePath, $this->buffer);

		return $this;
	}
}
