<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Extensions;

use Atrium\Admin\Hooks\Hooks;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * The panels a record's page shows, from `atrium.ui.panels`. A panel that
 * fails to draw is reported and left out: the record stays editable.
 */
final class Panels
{
    public function __construct(private readonly Hooks $hooks) {}

    /**
     * @return list<array{key: string, label: string, placement: string, html: string}>
     */
    public function for(Model $record, ?AdminUser $user): array
    {
        $panels = [];

        foreach ($this->hooks->registered('atrium.ui.panels', $record) as $panel) {
            if (! $panel instanceof Panel || ! $panel->shows($record, $user)) {
                continue;
            }

            try {
                $panels[] = $panel->toArray($record, $user);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $panels;
    }
}
