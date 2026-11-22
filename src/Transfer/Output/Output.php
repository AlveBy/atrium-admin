<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer\Output;

/**
 * A file an export is written to, a row at a time: the first row is the
 * header.
 */
interface Output
{
    /**
     * @param  array<int,string>  $cells
     */
    public function row(array $cells): void;

    public function close(): void;
}
