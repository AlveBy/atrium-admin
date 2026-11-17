<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Illuminate\Database\Eloquent\Model;

/**
 * A record whose key is text, digits and letters alike: what a list of ids
 * has to hand back as the numbers and the strings they are.
 */
final class Code extends Model
{
    protected $table = 'cache_codes';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['code'];
}
