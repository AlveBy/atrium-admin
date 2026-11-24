<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Templates\OwnsTemplateColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * A record that keeps the key of its template in a column of its own: the
 * name column of the posts table.
 */
final class Flyer extends Model implements OwnsTemplateColumn
{
    protected $table = 'posts';

    protected $fillable = ['name'];

    public static function templateColumn(): string
    {
        return 'name';
    }
}
