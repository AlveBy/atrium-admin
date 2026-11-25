<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\History\PublishingSchedule;
use Illuminate\Console\Command;

/**
 * Switches on and off the records whose "Publish at" or "Unpublish at" has
 * come; the scheduler runs it every minute.
 */
final class PublishCommand extends Command
{
    protected $signature = 'atrium:publish';

    protected $description = 'Switch on and off the records whose "Publish at" or "Unpublish at" has come';

    public function handle(PublishingSchedule $schedule): int
    {
        $result = $schedule->run(now());

        $this->info($result['switched'].' switched.');

        foreach ($result['refused'] as $refusal) {
            $this->warn('Not switched, '.$refusal);
        }

        return self::SUCCESS;
    }
}
