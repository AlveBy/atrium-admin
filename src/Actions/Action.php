<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A button of a module: on a record (in the list and on its form) or over
 * the records selected in the list. Modules return them from
 * getAdministrationActions() and getAdministrationBulkActions(), other
 * modules add theirs through the `atrium.module.actions` and
 * `atrium.module.bulk_actions` hooks.
 *
 * Each needs a permission: a bare action ("update") is the module's own
 * (`pages.update`), a full name ("menus.update") is taken as it is. Who
 * lacks it does not see the button, and the server answers 403.
 *
 * @implements Arrayable<string,mixed>
 */
abstract class Action implements Arrayable
{
    protected string $label = '';

    protected string $permission = 'update';

    protected ?string $confirm = null;

    final private function __construct(protected readonly string $name) {}

    /**
     * The name is the button's address: letters, digits, "-" and "_".
     */
    final public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function permission(string $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * Ask before running it; without a question, "Are you sure?".
     */
    public function confirm(?string $question = null): static
    {
        $this->confirm = $question ?? __('atrium::actions.confirm');

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * The permission it needs in a module: `update` in `pages` is `pages.update`.
     */
    public function permissionIn(string $module): string
    {
        return str_contains($this->permission, '.') ? $this->permission : "{$module}.{$this->permission}";
    }

    /**
     * `link`, `record` or `bulk`: how the panel draws and runs it.
     */
    abstract public function type(): string;

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return ['type' => $this->type(), 'name' => $this->name, 'label' => $this->label, 'confirm' => $this->confirm];
    }
}
