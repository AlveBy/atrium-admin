<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Illuminate\Database\Eloquent\Model;

/**
 * Knows its SEO title and nothing else.
 */
final class SeoTitleOnly extends Model
{
    protected $table = 'card_items';

    protected $guarded = [];

    public $timestamps = false;

    public function getSeoTitle(): string
    {
        return 'Only a title';
    }
}
