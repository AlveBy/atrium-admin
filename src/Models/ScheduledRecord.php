<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * When a record switches itself on or off (Atrium\Admin\History\Schedule),
 * and who planned it.
 *
 * @property int $id
 * @property string $module
 * @property int $record_id
 * @property int|null $site_id
 * @property Carbon|null $publish_at
 * @property Carbon|null $unpublish_at
 * @property int|null $admin_user_id
 */
class ScheduledRecord extends Model
{
    protected $table = 'admin_schedules';

    protected $fillable = ['module', 'record_id', 'site_id', 'publish_at', 'unpublish_at', 'admin_user_id'];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'unpublish_at' => 'datetime',
        ];
    }
}
