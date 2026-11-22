<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

/**
 * What an action tells the administrator: a message of success or of a
 * failure, and where to go next. Without an address the page they are on is
 * reloaded.
 */
final class ActionResult
{
    private ?string $redirect = null;

    private function __construct(private readonly bool $successful, private readonly string $message) {}

    public static function success(?string $message = null): self
    {
        return new self(true, $message ?? __('atrium::actions.done'));
    }

    public static function error(?string $message = null): self
    {
        return new self(false, $message ?? __('atrium::actions.failed'));
    }

    /**
     * Go to this address afterwards: a page of the panel or anywhere else.
     */
    public function redirect(string $url): self
    {
        $this->redirect = $url;

        return $this;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function redirectUrl(): ?string
    {
        return $this->redirect;
    }
}
