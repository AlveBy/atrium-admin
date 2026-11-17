<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * For a row that belongs to another record through a morph pair (SEO meta,
 * a slug): saving it drops its owner's cards. The owner is named, not
 * loaded: no query.
 *
 * @mixin Model
 */
trait TouchesOwner
{
    /**
     * @return array<int,Model>
     */
    public function cacheTouches(): array
    {
        $type = $this->getAttribute($this->ownerMorphName().'_type');
        $id = $this->getAttribute($this->ownerMorphName().'_id');
        $class = is_string($type) ? (Relation::getMorphedModel($type) ?? $type) : null;

        if ($class === null || $id === null || ! is_subclass_of($class, Model::class)) {
            return [];
        }

        $owner = new $class;
        $owner->setRawAttributes([$owner->getKeyName() => $id], true);
        $owner->exists = true;

        return [$owner];
    }

    abstract protected function ownerMorphName(): string;
}
