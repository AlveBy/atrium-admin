<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A tree of sections nested through parent_id, for the tree select.
 */
final class Section extends Model
{
    protected $table = 'sections';

    protected $fillable = ['name', 'title', 'sort', 'parent_id', 'group_id'];
}
