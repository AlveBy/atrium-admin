<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a record (Atrium\Admin\History\Revisions): the values of its
 * form after a save, passwords and keys left out. Its activity entry says who
 * saved it, when and from where; none for the record as it was before its
 * history began.
 *
 * @property int $id
 * @property int|null $activity_id
 * @property string $module
 * @property int $record_id
 * @property array<string,mixed> $values
 * @property Carbon|null $created_at
 * @property-read Activity|null $activity
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'admin_revisions';

    protected $fillable = ['activity_id', 'module', 'record_id', 'values'];

    /**
     * @return BelongsTo<Activity,$this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Which version this is: when, by whom and from where it was saved;
     * nulls for the record as it was before its history began.
     *
     * @return array{id:int,at:string|null,admin:string|null,source:string|null}
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'at' => $this->activity?->created_at->toIso8601String(),
            'admin' => $this->activity?->admin_name,
            'source' => $this->activity?->source,
        ];
    }

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
