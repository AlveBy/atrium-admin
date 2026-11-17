<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Cache;

use Illuminate\Database\Eloquent\Model;

/**
 * A record that tells another about every save: two of them telling each
 * other would go on for ever, so after fifty tellings they stop.
 */
final class Node extends Model
{
    /** How many times the nodes told each other: a loop with no guard stops here. */
    public static int $told = 0;

    /** What the node answers when asked who to tell, as it is. */
    public mixed $tells = [];

    public $timestamps = false;

    protected $table = 'cache_nodes';

    protected $fillable = ['name'];

    public function cacheTouches(): mixed
    {
        return self::$told++ >= 50 ? [] : $this->tells;
    }
}
