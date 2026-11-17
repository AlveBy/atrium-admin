<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Illuminate\Database\Eloquent\Model;

/**
 * A section of a tree: the address of what is in it is made of the codes.
 */
final class Section extends Model
{
    protected $table = 'card_sections';

    protected $guarded = [];

    public $timestamps = false;
}
