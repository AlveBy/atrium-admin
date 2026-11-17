<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Support;

use Atrium\Admin\Models\AdminUser;

/**
 * Colour themes of the panel. The colours themselves live in
 * resources/css/atrium.css; here is only which theme a request gets.
 */
final class Themes
{
    /** The themes with colours of their own, in menu order. */
    public const THEMES = ['graphite', 'day', 'night', 'sand', 'contrast'];

    /** Follow the operating system: graphite when it is light, night when dark. */
    public const SYSTEM = 'system';

    public const FALLBACK = 'graphite';

    /**
     * What an administrator may pick: every theme and "as the system".
     *
     * @return list<string>
     */
    public function choices(): array
    {
        return [self::SYSTEM, ...self::THEMES];
    }

    /** @phpstan-assert-if-true string $choice */
    public function isChoice(?string $choice): bool
    {
        return $choice !== null && in_array($choice, $this->choices(), true);
    }

    /** The configured default, or graphite when the config names no theme. */
    public function default(): string
    {
        $configured = config('atrium.admin.theme');

        return is_string($configured) && $this->isChoice($configured) ? $configured : self::FALLBACK;
    }

    /** The admin's own choice, otherwise the default. May be "system". */
    public function choiceFor(?AdminUser $user): string
    {
        $theme = $user?->theme;

        return $this->isChoice($theme) ? $theme : $this->default();
    }

    /**
     * The theme to render on the server. "system" renders as graphite; a
     * script in the page head swaps it for night before the first paint
     * when the system is dark.
     */
    public function render(string $choice): string
    {
        return $choice === self::SYSTEM ? self::FALLBACK : $choice;
    }
}
