<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Seeder;

/**
 * A module's demo content: one shelf, once.
 */
final class ShelfDemoSeeder extends Seeder
{
    public function run(): ?bool
    {
        if (Post::query()->where('name', 'Demo shelf')->exists()) {
            return false;
        }

        Post::query()->create(['name' => 'Demo shelf']);

        return null;
    }
}
