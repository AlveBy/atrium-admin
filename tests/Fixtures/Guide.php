<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A public page with an address made of an optional folder and its code,
 * for the old-address redirects.
 */
final class Guide extends Model
{
    protected $table = 'guides';

    protected $fillable = ['name', 'code', 'folder', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function getFullPath(): string
    {
        return implode('/', array_filter([$this->folder, $this->code]));
    }
}
