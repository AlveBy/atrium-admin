<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Console\Commands;

use Atrium\Admin\Extensions\Events;
use Atrium\Admin\Resources\ResourceRegistry;
use Illuminate\Console\Command;

/**
 * The event catalogue: what rules, webhooks and outside apps may hear.
 */
final class EventsCommand extends Command
{
    protected $signature = 'atrium:events';

    protected $description = 'List the events of the catalogue: the record events of every module and those modules declare';

    public function handle(Events $events, ResourceRegistry $resources): int
    {
        $rows = [];

        foreach ($events->all() as $key => $event) {
            $subject = $event->subjectModel();
            $rows[] = [$key, $event->labelText(), $event->hook(), $subject === null ? '' : ($resources->keyOf($subject) ?? $subject)];
        }

        $this->table(['Key', 'Label', 'Hook', 'Subject'], $rows);

        return self::SUCCESS;
    }
}
