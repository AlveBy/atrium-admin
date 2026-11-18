<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Tests\Fixtures\Notice;
use Atrium\Admin\Tests\TestCase;

/**
 * Looking a text up inside the translations of a column.
 */
final class TranslationLikeTest extends TestCase
{
    public function test_it_looks_in_the_given_languages_or_else_in_the_content_languages(): void
    {
        Notice::query()->create(['name' => ['en' => 'Alpha', 'de' => 'Beta']]);

        $find = fn (string $text, ?array $locales = null): int => Notice::query()->whereTranslationLike('name', $text, $locales)->count();

        $this->assertSame(1, $find('alp'));
        $this->assertSame(0, $find('bet'));
        $this->assertSame(1, $find('bet', ['de']));
        $this->assertSame(0, $find('alp', ['de']));
        $this->assertSame(1, $find('alp', ['en', 'de']));
        $this->assertSame(1, $find('bet', ['en', 'de']));
    }
}
