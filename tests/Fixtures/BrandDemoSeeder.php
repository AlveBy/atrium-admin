<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Seeder;

/**
 * Demo content of a module others build on: one brand, once.
 */
final class BrandDemoSeeder extends Seeder
{
    public function run(): ?bool
    {
        if (Post::query()->where('name', 'Demo brand')->exists()) {
            return false;
        }

        Post::query()->create(['name' => 'Demo brand']);

        return null;
    }
}
