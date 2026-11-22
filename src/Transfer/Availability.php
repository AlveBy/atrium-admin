<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Illuminate\Database\Eloquent\Model;

/**
 * Whether a module takes part in import and export: every module does,
 * unless its model says otherwise with `isImportableInAdmin()` or
 * `isExportableInAdmin()` (InteractsWithAdmin has both, true).
 */
final class Availability
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function import(string $model): bool
    {
        return ! method_exists($model, 'isImportableInAdmin') || $model::isImportableInAdmin();
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function export(string $model): bool
    {
        return ! method_exists($model, 'isExportableInAdmin') || $model::isExportableInAdmin();
    }
}
