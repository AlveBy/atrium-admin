<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A record whose form has the tabs a test gives it.
 */
final class Tabbed extends Model
{
    /** @var array<int,mixed> */
    public static array $tabs = [];

    public static bool $uses = true;

    protected $table = 'posts';

    public static function getAdminTabs(): array
    {
        return self::$tabs;
    }

    public static function usesTabs(): bool
    {
        return self::$uses;
    }
}
