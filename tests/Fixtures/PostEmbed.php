<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures;

use Atrium\Admin\Embeds\ModelEmbed;
use Illuminate\Database\Eloquent\Model;

/** An embed of the Post fixture: shows the post's name in bold. */
class PostEmbed extends ModelEmbed
{
    public function label(): string
    {
        return 'Post';
    }

    protected function model(): string
    {
        return Post::class;
    }

    protected function renderRecord(Model $record): string
    {
        return '<b>'.e((string) $record->getAttribute('name')).'</b>';
    }
}
