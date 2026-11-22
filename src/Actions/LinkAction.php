<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * A button on a record that opens an address: a page of the panel, the
 * record on the site, another service.
 *
 *     LinkAction::make('preview')->label('Preview')
 *         ->url(fn (Page $page) => route('pages.preview', $page))
 *         ->newTab();
 *
 * Needs the module's `view` permission unless told otherwise.
 */
final class LinkAction extends Action
{
    protected string $permission = 'view';

    private Closure|string $url = '';

    private bool $newTab = false;

    /**
     * The address, or a closure that makes it from the record.
     */
    public function url(Closure|string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function newTab(): self
    {
        $this->newTab = true;

        return $this;
    }

    public function href(Model $record): string
    {
        return $this->url instanceof Closure ? (string) ($this->url)($record) : $this->url;
    }

    public function type(): string
    {
        return 'link';
    }

    public function toArray(): array
    {
        return [...parent::toArray(), 'new_tab' => $this->newTab];
    }
}
