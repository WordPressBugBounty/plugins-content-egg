<?php

namespace ContentEgg\application\helpers;

defined('\ABSPATH') || exit;

/**
 * CsvReader class file
 *
 * Wraps fgetcsv() with row-merging support for unquoted CSV feeds
 * where field values may contain embedded newlines.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class CsvReader
{
	/** @var resource */
	private $handle;

	private string $delimiter;
	private string $enclosure;
	private string $escape;
	private int $expectedColumns;
	private bool $mergeEnabled;

	/** Maximum extra lines to read when merging a broken row */
	private int $mergeLimit = 100;

	/**
	 * @param resource $handle        Open file handle
	 * @param string   $delimiter     CSV delimiter
	 * @param string   $enclosure     CSV enclosure ("\0" for none)
	 * @param string   $escape        CSV escape character
	 * @param int      $expectedColumns Number of columns in the header (0 = unknown yet)
	 */
	public function __construct($handle, string $delimiter, string $enclosure, string $escape = '\\', int $expectedColumns = 0)
	{
		$this->handle          = $handle;
		$this->delimiter       = $delimiter;
		$this->enclosure       = $enclosure;
		$this->escape          = $escape;
		$this->expectedColumns = $expectedColumns;
		$this->mergeEnabled    = ($enclosure === "\0");
	}

	public function setExpectedColumns(int $count): void
	{
		$this->expectedColumns = $count;
	}

	/**
	 * Read next logical CSV row.
	 *
	 * When merging is enabled (no enclosure) and a row is short,
	 * reads additional lines and appends them to the last field
	 * until the column count matches expectedColumns.
	 *
	 * @return array|false
	 */
	public function readRow()
	{
		$row = fgetcsv($this->handle, null, $this->delimiter, $this->enclosure, $this->escape);

		if ($row === false)
		{
			return false;
		}

		if (!$this->mergeEnabled || $this->expectedColumns < 2)
		{
			return $row;
		}

		$mergeAttempts = 0;

		while (count($row) < $this->expectedColumns && $mergeAttempts < $this->mergeLimit)
		{
			$next = fgetcsv($this->handle, null, $this->delimiter, $this->enclosure, $this->escape);

			if ($next === false)
			{
				break;
			}

			// Append first element of next line to last element of current row
			// (the newline split the field)
			$lastIndex = count($row) - 1;
			$row[$lastIndex] .= "\n" . array_shift($next);

			// Append any remaining fields from next line
			foreach ($next as $field)
			{
				$row[] = $field;
			}

			++$mergeAttempts;
		}

		return $row;
	}
}
