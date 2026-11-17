<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cards;

use Illuminate\Database\Eloquent\Model;

/**
 * Names its translatable columns but cannot give the translations: its rows
 * are kept as they are.
 */
final class HalfTranslated extends Model
{
    protected $table = 'card_owners';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<int,string>
     */
    public function getTranslatableAttributes(): array
    {
        return ['name'];
    }
}
