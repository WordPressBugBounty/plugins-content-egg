<?php

namespace ContentEgg\application\helpers;

defined('\ABSPATH') || exit;

/**
 * JsonStreamReader class file
 *
 * Incrementally reads product objects from large JSON feed files without
 * loading the whole document into memory. Supported shapes:
 *  - top-level array of objects:            [ {...}, {...} ]
 *  - object with an array property (node):  { "products": [ {...}, ... ] }
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class JsonStreamReader
{
    const CHUNK_SIZE = 65536;       // 64 KiB per fread
    const MAX_ITEM_BYTES = 8388608; // 8 MiB cap for a single product object

    private $handle = null;
    private $buffer = '';
    private $pos = 0;
    private $eof = false;
    private $node;
    private $array_found = false;
    private $finished = false;
    private $error = '';

    public function __construct(string $file_path, ?string $node = null)
    {
        $this->node = ($node !== null && $node !== '') ? $node : null;
        $this->handle = @fopen($file_path, 'rb');
    }

    public function isOpen(): bool
    {
        return is_resource($this->handle);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function close(): void
    {
        if (is_resource($this->handle))
        {
            fclose($this->handle);
        }
        $this->handle = null;
        $this->buffer = '';
    }

    /**
     * Return the next product object as an associative array, or null when
     * the feed is exhausted or a fatal error occurred (see getError()).
     */
    public function read(): ?array
    {
        if (!$this->isOpen() || $this->finished)
        {
            return null;
        }

        if (!$this->array_found)
        {
            if (!$this->locateArray())
            {
                $this->finished = true;
                return null;
            }
        }

        while (true)
        {
            // Skip whitespace and commas between items.
            while (true)
            {
                if (!$this->has($this->pos))
                {
                    $this->finished = true;
                    return null;
                }
                $c = $this->buffer[$this->pos];
                if ($c === ',' || ctype_space($c))
                {
                    $this->pos++;
                    $this->compact();
                    continue;
                }
                break;
            }

            if ($this->buffer[$this->pos] === ']')
            {
                $this->finished = true;
                return null;
            }

            if ($this->buffer[$this->pos] !== '{')
            {
                // Scalar or nested-array item: not a product row, skip it.
                if (!$this->skipNonObjectItem())
                {
                    $this->finished = true;
                    return null;
                }
                continue;
            }

            $item = $this->readObject();

            if ($this->finished)
            {
                return null;
            }

            if ($item !== null)
            {
                return $item;
            }
            // Malformed single item: keep streaming.
        }
    }

    // ----------------- Internals -----------------

    private function fill(): bool
    {
        if ($this->eof || !is_resource($this->handle))
        {
            return false;
        }
        $chunk = fread($this->handle, self::CHUNK_SIZE);
        if ($chunk === false || $chunk === '')
        {
            $this->eof = true;
            return false;
        }
        $this->buffer .= $chunk;
        return true;
    }

    /** Ensure the buffer contains a byte at offset $i. */
    private function has(int $i): bool
    {
        while (strlen($this->buffer) <= $i)
        {
            if (!$this->fill())
            {
                return false;
            }
        }
        return true;
    }

    /** Discard consumed bytes to keep memory flat. */
    private function compact(): void
    {
        if ($this->pos >= self::CHUNK_SIZE)
        {
            $this->buffer = substr($this->buffer, $this->pos);
            $this->pos = 0;
        }
    }

    /**
     * Advance to just after the opening bracket of the product array.
     * Root-level array always matches. Inside a root object, the value
     * of $this->node matches; with no node, the first array property wins.
     */
    private function locateArray(): bool
    {
        $depth = 0;
        $in_string = false;
        $escape = false;
        $capturing = false;
        $str = '';
        $last_key = '';
        $i = $this->pos;

        while ($this->has($i))
        {
            $c = $this->buffer[$i];
            $i++;

            if ($in_string)
            {
                if ($escape)
                {
                    $escape = false;
                }
                elseif ($c === '\\')
                {
                    $escape = true;
                }
                elseif ($c === '"')
                {
                    $in_string = false;
                    if ($capturing)
                    {
                        $last_key = $str;
                        $capturing = false;
                    }
                }
                elseif ($capturing)
                {
                    $str .= $c;
                }
            }
            elseif ($c === '"')
            {
                $in_string = true;
                if ($depth === 1)
                {
                    // Root-object member position: the string immediately
                    // preceding a value is always its key.
                    $capturing = true;
                    $str = '';
                }
            }
            elseif ($c === '{')
            {
                $depth++;
            }
            elseif ($c === '}')
            {
                $depth--;
            }
            elseif ($c === '[')
            {
                if ($depth === 0 || ($depth === 1 && ($this->node === null || $last_key === $this->node)))
                {
                    $this->pos = $i;
                    $this->compact();
                    $this->array_found = true;
                    return true;
                }
                $depth++;
            }
            elseif ($c === ']')
            {
                $depth--;
            }

            // Keep memory flat while scanning past non-matching content.
            if ($i >= self::CHUNK_SIZE * 2)
            {
                $this->buffer = substr($this->buffer, $i);
                $i = 0;
            }
        }

        $this->error = $this->node !== null
            ? sprintf('Product node "%s" not found in the JSON feed.', $this->node)
            : 'No product array found in the JSON feed.';

        return false;
    }

    /**
     * Read one complete {...} object starting at $this->pos.
     * Returns the decoded array, or null for a malformed item (stream
     * position is advanced past it either way). Sets $finished + $error
     * on fatal conditions (EOF inside object, oversized item).
     */
    private function readObject(): ?array
    {
        $start = $this->pos;
        $depth = 0;
        $in_string = false;
        $escape = false;
        $i = $this->pos;

        while ($this->has($i))
        {
            $c = $this->buffer[$i];
            $i++;

            if ($in_string)
            {
                if ($escape)
                {
                    $escape = false;
                }
                elseif ($c === '\\')
                {
                    $escape = true;
                }
                elseif ($c === '"')
                {
                    $in_string = false;
                }
                continue;
            }

            if ($c === '"')
            {
                $in_string = true;
            }
            elseif ($c === '{')
            {
                $depth++;
            }
            elseif ($c === '}')
            {
                $depth--;
                if ($depth === 0)
                {
                    $json = substr($this->buffer, $start, $i - $start);
                    $this->pos = $i;
                    $this->compact();

                    $item = json_decode($json, true);
                    return is_array($item) ? $item : null;
                }
            }

            if ($i - $start > self::MAX_ITEM_BYTES)
            {
                $this->error = 'A single JSON feed item exceeds the size limit.';
                $this->finished = true;
                return null;
            }
        }

        $this->error = 'Unexpected end of JSON feed.';
        $this->finished = true;
        return null;
    }

    /**
     * Skip one non-object item (scalar or nested array). Leaves the position
     * on the following comma or on the closing ']' of the product array.
     */
    private function skipNonObjectItem(): bool
    {
        $depth = 0;
        $in_string = false;
        $escape = false;
        $i = $this->pos;

        while ($this->has($i))
        {
            $c = $this->buffer[$i];

            if ($in_string)
            {
                $i++;
                if ($escape)
                {
                    $escape = false;
                }
                elseif ($c === '\\')
                {
                    $escape = true;
                }
                elseif ($c === '"')
                {
                    $in_string = false;
                    if ($depth === 0)
                    {
                        $this->pos = $i;
                        $this->compact();
                        return true;
                    }
                }
                continue;
            }

            if ($c === '"')
            {
                $in_string = true;
                $i++;
                continue;
            }
            if ($c === '[')
            {
                $depth++;
                $i++;
                continue;
            }
            if ($c === ']')
            {
                if ($depth === 0)
                {
                    // Closing bracket of the product array: do not consume it.
                    $this->pos = $i;
                    $this->compact();
                    return true;
                }
                $depth--;
                $i++;
                continue;
            }
            if ($depth === 0 && $c === ',')
            {
                $this->pos = $i;
                $this->compact();
                return true;
            }
            $i++;
        }

        return false;
    }
}
