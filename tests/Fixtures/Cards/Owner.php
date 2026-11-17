<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A record with a translated name that an item points to.
 */
final class Owner extends Model
{
    use Translatable;

    protected $table = 'card_owners';

    protected $guarded = [];

    public $timestamps = false;

    /** @var array<int,string> */
    public array $translatable = ['name'];
}
