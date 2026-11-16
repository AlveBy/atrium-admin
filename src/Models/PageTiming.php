<?php

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Замер времени генерации одной страницы публичной части.
 *
 * Пишется из RecordPageTiming после отдачи ответа, читается гаджетами
 * рабочего стола через PageSpeedStats.
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
