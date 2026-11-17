<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model that keeps no timestamps of its own although its table has the
 * columns, and whose name is a number.
 */
final class Untimed extends Model
{
    protected $table = 'articles';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['name' => 'integer'];
    }
}
