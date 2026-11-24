<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Views;

/**
 * Another way to look at a module's list besides the table: a module
 * returns it from getAdministrationViews(), other modules add theirs through
 * the `atrium.module.views` hook. A module with no view has no switcher.
 */
abstract class View
{
    protected ?string $title = null;

    final private function __construct(protected readonly string $field) {}

    /**
     * The field the view is built on: the one that sorts the cards into
     * columns, or the one that holds the date.
     */
    final public static function make(string $field): static
    {
        return new static($field);
    }

    /**
     * The field that names a record in the view; without it the record's own title.
     */
    public function title(string $field): static
    {
        $this->title = $field;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getTitleField(): ?string
    {
        return $this->title;
    }

    /**
     * `kanban` or `calendar`: the value of `?view=` and the key of the
     * view's label in the panel's language files.
     */
    abstract public function type(): string;

    /**
     * Every field the view writes when a record is moved in it: nothing
     * else is taken from the request.
     *
     * @return array<int,string>
     */
    abstract public function writes(): array;

    /**
     * The type of the field(s) the view is built on, checked when it is drawn.
     *
     * @return array<string,array<int,string>> field => the field types it may be
     */
    abstract public function needs(): array;
}
