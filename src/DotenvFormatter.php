<?php

namespace Mifesta\DotenvEditor;

use Mifesta\DotenvEditor\Contracts\DotenvFormatter as DotenvFormatterContract;
use Mifesta\DotenvEditor\Exceptions\InvalidValueException;

/**
 * The .env formatter.
 */
class DotenvFormatter implements DotenvFormatterContract
{
    /**
     * Formatting the key of setter to writing
     *
     * @param string $key
     * @return string
     */
    public function formatKey($key)
    {
        return trim(str_replace(['export ', '\'', '"', ' '], '', (string) $key));
    }

    /**
     * Formatting the value of setter to writing
     *
     * @param string|null $value
     * @param bool $forceQuotes
     * @return string
     */
    public function formatValue($value, $forceQuotes = false)
    {
        $value = $this->stringifyValue($value);

        if (! $forceQuotes && ! preg_match('/[#\s"\'\\\\]|\\\\n/', $value)) {
            return $value;
        }

        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\"', $value);

        return '"'.$value.'"';
    }

    /**
     * Build a setter line from the individual components for writing
     *
     * @param string $key
     * @param string|null $value
     * @param string|null $comment
     * @param bool $export
     * @return string
     */
    public function formatSetterLine($key, $value = null, $comment = null, $export = false)
    {
        $renderedComment = $this->formatComment($comment);
        $forceQuotes = ($renderedComment !== '' && trim($this->stringifyValue($value)) === '');
        $value = $this->formatValue($value, $forceQuotes);
        $key = $this->formatKey($key);
        $export = $export ? 'export ' : '';

        return $export.$key.'='.$value.$renderedComment;
    }

    /**
     * Formatting the comment to writing
     *
     * @param string|null|false $comment
     * @return string
     */
    public function formatComment($comment)
    {
        if ($comment === null || $comment === false) {
            return '';
        }

        $comment = trim((string) $comment, '# ');

        return $comment !== '' ? ' # '.$comment : '';
    }

    /**
     * Normalising the key of setter to reading
     *
     * @param string $key
     * @return string
     */
    public function normaliseKey($key)
    {
        return $this->formatKey($key);
    }

    /**
     * Normalising the value of setter to reading
     *
     * @param string $value
     * @param string $quote
     * @return string
     */
    public function normaliseValue($value, $quote = '')
    {
        if (strlen($quote) === 0) {
            return trim($value);
        }

        $value = str_replace("\\".$quote, $quote, $value);
        $value = str_replace('\\\\', '\\', $value);

        return $value;
    }

    /**
     * Normalising the comment to reading
     *
     * @param string $comment
     * @return string
     */
    public function normaliseComment($comment)
    {
        return trim((string) $comment, '# ');
    }

    /**
     * Parse a line into an array of type, export, key, value and comment
     *
     * @param string $line
     * @return array
     * @throws InvalidValueException
     */
    public function parseLine($line)
    {
        $output = [
            'type' => null,
            'export' => null,
            'key' => null,
            'value' => null,
            'comment' => null,
        ];

        if ($this->isEmpty($line)) {
            $output['type'] = 'empty';
        } elseif ($this->isComment($line)) {
            $output['type'] = 'comment';
            $output['comment'] = $this->normaliseComment($line);
        } elseif ($this->looksLikeSetter($line)) {
            list($key, $data) = array_map('trim', explode('=', $line, 2));
            $export = $this->isExportKey($key);
            $key = $this->normaliseKey($key);
            $data = trim($data);

            if ($data === '') {
                $value = '';
                $comment = '';
            } elseif ($this->beginsWithAQuote($data)) {
                $quote = $data[0];
                $regexPattern = sprintf('/^
                    %1$s
                    (
                     (?:
                      [^%1$s\\\\]
                      |\\\\\\\\
                      |\\\\%1$s
                     )*
                    )
                    %1$s
                    (.*)$
                    /mx', $quote);

                if (! preg_match($regexPattern, $data, $matches)) {
                    throw new InvalidValueException('A quoted dotenv value is missing its closing quote.');
                }

                $value = $this->normaliseValue($matches[1], $quote);
                $comment = $this->isComment($matches[2]) ? $this->normaliseComment($matches[2]) : '';
            } elseif ($this->isComment($data)) {
                $value = '';
                $comment = $this->normaliseComment($data);
            } else {
                $parts = explode(' #', $data, 2);
                $value = $this->normaliseValue($parts[0]);
                $comment = isset($parts[1]) ? $this->normaliseComment($parts[1]) : '';

                if (preg_match('/\s+/', $value)) {
                    throw new InvalidValueException('Dotenv values containing spaces must be surrounded by quotes.');
                }
            }

            $output['type'] = 'setter';
            $output['export'] = $export;
            $output['key'] = $key;
            $output['value'] = $value;
            $output['comment'] = $comment;
        } else {
            $output['type'] = 'unknown';
        }

        return $output;
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function stringifyValue($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected function isEmpty($line)
    {
        return strlen(trim($line)) === 0;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected function isComment($line)
    {
        return strpos(ltrim($line), '#') === 0;
    }

    /**
     * @param string $line
     * @return bool
     */
    protected function looksLikeSetter($line)
    {
        return strpos($line, '=') !== false && strpos($line, '=') !== 0;
    }

    /**
     * @param string $key
     * @return bool
     */
    protected function isExportKey($key)
    {
        return preg_match('/^export\h.+$/', trim($key)) === 1;
    }

    /**
     * @param string $data
     * @return bool
     */
    protected function beginsWithAQuote($data)
    {
        return $data !== '' && strpbrk($data[0], '"\'') !== false;
    }
}
