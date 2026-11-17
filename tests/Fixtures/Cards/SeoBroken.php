<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Its SEO methods all fail.
 */
final class SeoBroken extends Model
{
    protected $table = 'card_items';

    protected $guarded = [];

    public $timestamps = false;

    public function getSeoTitle(): string
    {
        throw new RuntimeException('title');
    }

    public function getSeoDescription(): string
    {
        throw new RuntimeException('description');
    }

    public function getSeoKeywords(): ?string
    {
        throw new RuntimeException('keywords');
    }

    public function getOgImage(): ?string
    {
        throw new RuntimeException('image');
    }
}
