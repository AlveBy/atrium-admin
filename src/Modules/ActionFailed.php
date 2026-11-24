<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use RuntimeException;

/**
 * A settings button that did not work. The message goes to the screen as it
 * is, so it must not carry a secret: say what is wrong, not what was sent.
 */
final class ActionFailed extends RuntimeException {}
