<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A section tree that is not an admin module: fields may point at it, the
 * API has no list of it.
 *
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 */
final class ApiAisle extends Model
{
    protected $table = 'api_aisles';

    protected $fillable = ['name', 'parent_id', 'sort'];
}
