<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Transfer\ImportOption;
use Atrium\Admin\Transfer\Settings;
use Atrium\Admin\Transfer\Targets;

/**
 * A text option that nobody filled in is a string: its default, or an empty
 * one when the default is the boolean that an option has when it says nothing.
 */
final class SettingsTextOptionDefaultTest extends TransferTestCase
{
    public function test_a_text_option_nobody_filled_in_is_its_default_as_a_string(): void
    {
        $options = [
            new ImportOption('prefix', 'Prefix', ImportOption::TEXT),
            new ImportOption('suffix', 'Suffix', ImportOption::TEXT, 'end'),
            new ImportOption('given', 'Given', ImportOption::TEXT, 'default'),
        ];

        $settings = Settings::validate(['mapping' => ['article'], 'key' => 'article', 'mode' => 'both', 'given' => 'mine'], 1, Targets::for(Parcel::class), $options);

        $this->assertSame(['key' => 'article', 'mode' => 'both', 'prefix' => '', 'suffix' => 'end', 'given' => 'mine'], $settings['options']);
    }

    public function test_a_text_option_that_is_null_is_its_default_too(): void
    {
        $options = [new ImportOption('suffix', 'Suffix', ImportOption::TEXT, 'end')];

        $settings = Settings::validate(['mapping' => ['article'], 'key' => 'article', 'mode' => 'both', 'suffix' => null], 1, Targets::for(Parcel::class), $options);

        $this->assertSame('end', $settings['options']['suffix']);
    }
}
