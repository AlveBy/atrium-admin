<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Extensions;

use Atrium\Admin\Models\AdminUser;
use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A block on a record's page drawn by a Blade view: under the form, beside
 * it, or on a tab of its own. Registered in `atrium.ui.panels`, for one
 * module or for all:
 *
 *     Hook::register('atrium.ui.panels', 'time.task', Panel::make('time.task')
 *         ->for(Task::class)
 *         ->label('acme-time::panels.time')
 *         ->html('acme-time::panels.task')
 *         ->side()
 *         ->permission('time.view'));
 *
 * The view gets the record as `$record` and the administrator as `$user`;
 * the page shows the HTML as it is, so the view escapes what it prints.
 */
final class Panel
{
    public const BELOW = 'below';

    public const SIDE = 'side';

    public const TAB = 'tab';

    private string $label;

    /** @var class-string<Model>|null */
    private ?string $model = null;

    private ?string $view = null;

    private string $placement = self::BELOW;

    private ?string $permission = null;

    private ?Closure $when = null;

    private function __construct(private readonly string $key)
    {
        $this->label = $key;
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    /**
     * Only on the records of this module; without it, on every module's.
     *
     * @param  class-string<Model>  $model
     */
    public function for(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /** The heading, or a translation key. */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** The Blade view that draws the panel. */
    public function html(string $view): self
    {
        $this->view = $view;

        return $this;
    }

    /** Under the form: the default. */
    public function below(): self
    {
        $this->placement = self::BELOW;

        return $this;
    }

    /** In a column beside the form, on a wide screen. */
    public function side(): self
    {
        $this->placement = self::SIDE;

        return $this;
    }

    /** A tab after the form's own; under the form when it has no tabs. */
    public function tab(): self
    {
        $this->placement = self::TAB;

        return $this;
    }

    /** Only for administrators with this permission. */
    public function permission(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * Only when this says true, given the record and the administrator.
     */
    public function when(Closure $when): self
    {
        $this->when = $when;

        return $this;
    }

    public function shows(Model $record, ?AdminUser $user): bool
    {
        if ($this->view === null || ($this->model !== null && ! $record instanceof $this->model)) {
            return false;
        }

        if ($this->permission !== null && $user?->hasAdminPermission($this->permission) !== true) {
            return false;
        }

        return $this->when === null || ($this->when)($record, $user) === true;
    }

    /**
     * @return array{key: string, label: string, placement: string, html: string}
     */
    public function toArray(Model $record, ?AdminUser $user): array
    {
        return [
            'key' => $this->key,
            'label' => $this->labelText(),
            'placement' => $this->placement,
            'html' => view($this->view ?? '', ['record' => $record, 'user' => $user])->render(),
        ];
    }

    /** The heading in the language of the panel. */
    public function labelText(): string
    {
        return (string) __($this->label);
    }
}
