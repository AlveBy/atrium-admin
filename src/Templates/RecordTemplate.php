<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Templates;

use Atrium\Admin\Cache\TouchesOwner;
use Illuminate\Database\Eloquent\Model;

/**
 * The template picked for one record.
 *
 * @property string $template
 */
final class RecordTemplate extends Model
{
    use TouchesOwner;

    protected $table = 'admin_record_templates';

    protected $fillable = ['templatable_type', 'templatable_id', 'template'];

    protected function ownerMorphName(): string
    {
        return 'templatable';
    }
}
