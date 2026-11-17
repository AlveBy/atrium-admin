<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Contracts\HasSlug;
use Atrium\Admin\Traits\HasAdminSlug;
use Illuminate\Database\Eloquent\Model;

/**
 * A record with a cached address. Not final: atrium:slug:regenerate --all
 * finds models in app/Models, so the tests put subclasses there.
 */
class Leaflet extends Model implements HasSlug
{
    use HasAdminSlug;

    protected $table = 'leaflets';

    protected $fillable = ['name', 'code'];
}
