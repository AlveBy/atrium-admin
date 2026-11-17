<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Illuminate\Database\Eloquent\Model;

/**
 * Every SEO method of its own, with no types: numbers and a plain value.
 */
final class SeoLoose extends Model
{
    protected $table = 'card_items';

    protected $guarded = [];

    public $timestamps = false;

    public function getSeoTitle()
    {
        return 42;
    }

    public function getSeoDescription()
    {
        return 7;
    }

    public function getSeoKeywords()
    {
        return 'kw';
    }

    public function getOgImage()
    {
        return 'og.png';
    }
}
