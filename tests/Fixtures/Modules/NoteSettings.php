<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Modules;

use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Modules\ModuleSettings;

/**
 * Settings with a field that has no label and one that takes an upload.
 */
final class NoteSettings extends ModuleSettings
{
    public bool $perSite = true;

    public function fields(): array
    {
        return [
            TextField::make('motto')->required(),
            ImageField::make('badge')->label('Badge'),
        ];
    }
}
