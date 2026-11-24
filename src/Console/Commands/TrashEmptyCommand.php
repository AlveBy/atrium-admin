<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\History\Trash;
use Illuminate\Console\Command;

/**
 * Deletes for good what has been in the trash longer than
 * `atrium.trash.days`; the scheduler runs it every night.
 */
final class TrashEmptyCommand extends Command
{
    protected $signature = 'atrium:trash:empty';

    protected $description = 'Delete for good the records that have been in the trash longer than atrium.trash.days';

    public function handle(Trash $trash): int
    {
        $days = config()->integer('atrium.trash.days');

        if ($days < 1) {
            $this->info('The trash keeps everything: atrium.trash.days is 0.');

            return self::SUCCESS;
        }

        $this->info($trash->emptyOlderThan(now()->subDays($days)).' deleted for good.');

        return self::SUCCESS;
    }
}
