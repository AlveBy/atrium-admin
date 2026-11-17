<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Illuminate\Database\Eloquent\Model;

final class Tag extends Model
{
    protected $table = 'cache_tags';

    protected $fillable = ['name'];
}
