<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer\Output;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * An Excel file, every cell a text: an article "00123" stays "00123", and a
 * cell is never a formula. The header row is bold.
 */
final class XlsxOutput implements Output
{
    private readonly Writer $writer;

    private bool $header = true;

    public function __construct(string $path)
    {
        $this->writer = new Writer;
        $this->writer->openToFile($path);
    }

    public function row(array $cells): void
    {
        $style = $this->header ? (new Style)->setFontBold() : null;

        // Row::fromValues() would make a formula of a text that starts with "=".
        $this->writer->addRow(new Row(array_map(static fn (string $cell): StringCell => new StringCell($cell, $style), $cells)));
        $this->header = false;
    }

    public function close(): void
    {
        $this->writer->close();
    }
}
