<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

interface ReferenceAdministrable extends Administrable
{
    /**
     * Get display value for dropdowns and relations
     */
    public function getDisplayValue(): string;
}
