<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Modules;

use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Modules\ModuleSettings;

/**
 * The settings of the acme/atrium-labels module package.
 */
final class LabelSettings extends ModuleSettings
{
    public function fields(): array
    {
        return [
            SelectField::make('color')->label('Color')->options(['red' => 'Red', 'blue' => 'Blue'])->default('red')->required(),
            NumberField::make('per_page')->label('Labels per page')->rules(['integer', 'min:1']),
            CheckboxField::make('show_count')->label('Show the count'),
        ];
    }

    public function description(): string
    {
        return 'How labels look on the site.';
    }
}
