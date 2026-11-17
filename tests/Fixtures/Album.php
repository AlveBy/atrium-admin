<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Traits\HasAdminFiles;
use Illuminate\Database\Eloquent\Model;

/**
 * A record with files of its own, kept in the posts table.
 */
final class Album extends Model
{
    use HasAdminFiles;

    protected $table = 'posts';

    protected $fillable = ['name'];
}
