<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Builder;

use Atrium\Admin\Builder\BlockType;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextField;

/**
 * A block type as a module writes one: a translatable title and two settings
 * that are the same in every language.
 */
final class NoteBlock extends BlockType
{
    public function label(): string
    {
        return 'Note';
    }

    public function view(): string
    {
        return 'fixtures::note';
    }

    public function fields(): array
    {
        return [
            TextField::make('title')->label('Title')->required()->rules(['string', 'max:10']),
            SelectField::make('colour')->label('Colour')->options(['red' => 'Red', 'blue' => 'Blue']),
            CheckboxField::make('framed')->label('Framed'),
        ];
    }

    public function translatable(): array
    {
        return ['title'];
    }
}
