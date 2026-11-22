<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Models\DataImport;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * What one column of a file stands for: a field of the module's form, one
 * language of a field kept per language, or something a module reads from a
 * cell in its own way (a section by its path).
 *
 * A module that needs its own reading of a cell, or its own writing of it,
 * gives the target two functions:
 *
 *  - resolve(cell, import, check): the attributes the cell sets, as the form
 *    would send them: ['section_id' => 12]. In a check nothing may be written.
 *  - export(record): the text of the cell for a record.
 */
final class Target
{
    /**
     * @param  array<int,string>  $synonyms  headers other programs use for it, normalized (Cells::normalize)
     * @param  (Closure(string,DataImport,bool):array<string,mixed>)|null  $resolve
     * @param  (Closure(Model):string)|null  $export
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?Field $field = null,
        public readonly ?string $locale = null,
        public readonly ?string $base = null,
        public readonly array $synonyms = [],
        public readonly ?Closure $resolve = null,
        public readonly ?Closure $export = null,
    ) {}

    /**
     * The same target that also answers to these headers.
     *
     * @param  array<int,string>  $synonyms
     */
    public function withSynonyms(array $synonyms): self
    {
        return new self($this->key, $this->label, $this->field, $this->locale, $this->base, [...$this->synonyms, ...$synonyms], $this->resolve, $this->export);
    }
}
