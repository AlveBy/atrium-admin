<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Templates;

/**
 * A template a record can be drawn with: its key (the file name), the
 * Blade view and the name the editor sees.
 */
final readonly class Template
{
    public function __construct(
        public string $key,
        public string $view,
        public string $label,
    ) {}
}
