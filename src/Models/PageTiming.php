<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Generation time of one public page.
 *
 * Written by RecordPageTiming after the response is sent, read by the
 * dashboard gadgets through PageSpeedStats.
 */
class PageTiming extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_page_timings';

    protected $fillable = [
        'path',
        'duration_ms',
    ];

    protected $casts = [
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}
