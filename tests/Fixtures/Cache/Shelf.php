<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Atrium\Admin\Cache\CachesRecords;
use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a book's card shows the name of, like a product's brand.
 */
final class Shelf extends Model implements Cacheable
{
    use CachesRecords, Translatable;

    protected $table = 'cache_shelves';

    protected $fillable = ['name'];

    /** @var array<int,string> */
    public array $translatable = ['name'];
}
