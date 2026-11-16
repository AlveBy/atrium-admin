<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Traits\HasSoftRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Attached to a record of any module, the way a review or a comment is.
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 */
final class Remark extends Model
{
    use HasSoftRelations;

    protected $table = 'remarks';

    protected $fillable = ['text', 'subject_type', 'subject_id'];

    /**
     * @return MorphTo<covariant Model,$this>
     */
    public function subject(): MorphTo
    {
        return $this->softMorphTo();
    }
}
