<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Templates;

/**
 * A model that keeps the key of its template in a column of its own (a
 * page has `template`) instead of in admin_record_templates: it has a field
 * for it already, so the core adds none, and the same list of templates
 * and the same drawing serve it.
 */
interface OwnsTemplateColumn
{
    /**
     * The column that holds the key of the template.
     */
    public static function templateColumn(): string;
}
