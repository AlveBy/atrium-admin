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
 * A record in the trash (Atrium\Admin\History\Trash): which one, of which
 * site, what it was called, who deleted it and when. The record itself is
 * still in its table, hidden.
 *
 * @property int $id
 * @property string $module
 * @property int $record_id
 * @property int|null $site_id
 * @property string|null $record_title
 * @property int|null $admin_user_id
 * @property string|null $admin_name
 * @property Carbon|null $created_at
 */
class TrashedRecord extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_trash';

    protected $fillable = ['module', 'record_id', 'site_id', 'record_title', 'admin_user_id', 'admin_name'];

    protected function casts(): array
    {
        return [
            'record_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
