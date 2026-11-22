<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A setting of a module's import besides the mapping: "create the sections
 * that are not there", "the separator of a path". A module adds them with
 * the `atrium.import.options` filter; the mapping screen shows them, and the
 * console takes them as `--option=name=value`.
 *
 * @implements Arrayable<string,bool|string>
 */
final class ImportOption implements Arrayable
{
    public const BOOLEAN = 'boolean';

    public const TEXT = 'text';

    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type = self::BOOLEAN,
        public readonly bool|string $default = false,
        public readonly string $hint = '',
    ) {}

    /**
     * @return array{name:string,label:string,type:string,default:bool|string,hint:string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'label' => $this->label, 'type' => $this->type, 'default' => $this->default, 'hint' => $this->hint];
    }
}
