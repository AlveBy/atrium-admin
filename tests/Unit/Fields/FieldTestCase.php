<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

abstract class FieldTestCase extends TestCase
{
    /** A value left out of the request altogether. */
    public const MISSING = "\0missing";

    /**
     * Whether the field's rules let the value through, the way the admin
     * validates a submitted form.
     */
    protected function passes(Field $field, mixed $value, ?FieldContext $context = null): bool
    {
        $data = $value === self::MISSING ? [] : [$field->getName() => $value];

        return Validator::make($data, [$field->getName() => $field->getValidationRules($context)])->passes();
    }
}
