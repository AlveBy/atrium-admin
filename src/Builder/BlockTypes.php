<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Builder;

use Atrium\Admin\Hooks\Hooks;

/**
 * The block types of the site builder, by key. Types are a registry of the
 * hooks (REGISTRY); this class reads it.
 */
final class BlockTypes
{
    /** The hook registry that modules put their block types in. */
    public const REGISTRY = 'atrium.site.blocks';

    public function __construct(private readonly Hooks $hooks) {}

    /**
     * The types that work now.
     *
     * @return array<string,BlockType>
     */
    public function all(): array
    {
        return array_filter(
            $this->hooks->registered(self::REGISTRY),
            static fn (mixed $type): bool => $type instanceof BlockType && $type->available(),
        );
    }

    public function get(string $key): ?BlockType
    {
        return $this->all()[$key] ?? null;
    }
}
