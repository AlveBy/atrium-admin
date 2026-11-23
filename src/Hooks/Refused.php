<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Hooks;

use RuntimeException;

/**
 * A "before" hook says no: thrown from `atrium.record.data` it keeps the
 * record unsaved, from `atrium.record.deleting` it keeps the record. The
 * message is shown to the editor and returned by the API and MCP, so write
 * it for a person: `throw new Refused(__('shop::orders.has_open'))`.
 *
 * With a field the panel shows the message under that field; without one,
 * above the form.
 */
final class Refused extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
