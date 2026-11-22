<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer\Output;

use Atrium\Admin\Transfer\Cells;

/**
 * UTF-8 with a byte order mark, which is how Excel knows the text is not in
 * the system's code page, and a semicolon between the cells, which is what
 * Excel splits by in most countries. The import recognises both.
 *
 * A cell a spreadsheet would take for a formula is written with an
 * apostrophe in front; the import takes the apostrophe off again.
 */
final class CsvOutput implements Output
{
    public const DELIMITER = ';';

    /** @var resource */
    private $handle;

    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'wb');
        fwrite($this->handle, "\xEF\xBB\xBF");
    }

    public function row(array $cells): void
    {
        fputcsv($this->handle, array_map(Cells::protect(...), $cells), self::DELIMITER, '"', '');
    }

    public function close(): void
    {
        fclose($this->handle);
    }
}
