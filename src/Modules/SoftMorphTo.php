<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A morphTo that does not fail on a record of a module that is gone: such a
 * record reads as null, also when loaded with `with()`.
 *
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphTo<TRelatedModel,TDeclaringModel>
 */
class SoftMorphTo extends MorphTo
{
    /**
     * @param  string  $type
     * @return Collection<int,TRelatedModel>
     */
    protected function getResultsByType($type)
    {
        if (! Soft::available(Model::getActualClassNameForMorph($type))) {
            return new Collection;
        }

        return parent::getResultsByType($type);
    }
}
