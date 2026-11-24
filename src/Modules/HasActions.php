<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

/**
 * A ModuleSettings class that also puts buttons on the settings screen, such
 * as "Check the connection". A button posts to the panel, which calls run()
 * with the button's name and shows what it returns as a success message, or
 * the message of an ActionFailed as an error.
 */
interface HasActions
{
    /**
     * @return array<int,array{name:string,label:string}>
     */
    public function actions(): array;

    /**
     * Does what the button named $action says, with the saved settings.
     *
     * @throws ActionFailed with a message that is safe to show
     */
    public function run(string $action): string;
}
