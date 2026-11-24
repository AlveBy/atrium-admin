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
 * One entry of the activity log (Atrium\Admin\History\ActivityLog): a record
 * of a module created, changed, deleted or restored, by whom and from where.
 *
 * `changes` holds field => ['old' => …, 'new' => …], or ['secret' => true]
 * for a password or a key: that it changed is told, never what to.
 *
 * @property int $id
 * @property int|null $site_id
 * @property string $module
 * @property int $record_id
 * @property string|null $record_title
 * @property string $event
 * @property array<string,array{old?:mixed,new?:mixed,secret?:bool}>|null $changes
 * @property int|null $admin_user_id
 * @property string|null $admin_name
 * @property string $source
 * @property Carbon|null $created_at
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_activity';

    protected $fillable = [
        'site_id', 'module', 'record_id', 'record_title', 'event', 'changes', 'admin_user_id', 'admin_name', 'source',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'admin_user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
