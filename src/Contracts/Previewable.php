<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Contracts;

use Illuminate\Contracts\Support\Renderable;

/**
 * A record that has a page on the site and can draw it for the page editor.
 */
interface Previewable
{
    /**
     * The record's page as the site draws it, made of the record as it stands
     * now, unsaved changes too, in a content language. The editor sets the
     * application's locale to $language around the drawing.
     */
    public function adminPreview(string $language): Renderable;
}
