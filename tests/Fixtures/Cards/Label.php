<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Illuminate\Database\Eloquent\Model;

/**
 * A plain record: no translations, no SEO, not cacheable.
 */
final class Label extends Model
{
    protected $table = 'card_labels';

    protected $guarded = [];

    public $timestamps = false;
}
