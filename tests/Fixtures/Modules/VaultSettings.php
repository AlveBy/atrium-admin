<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Fixtures\Modules;

use Atrium\Admin\Facades\Setting;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Modules\ActionFailed;
use Atrium\Admin\Modules\HasActions;
use Atrium\Admin\Modules\ModuleSettings;

/**
 * The settings of the acme/atrium-vault module package: an address that is a
 * secret, a plain name, and two buttons, one that works and one that fails.
 */
final class VaultSettings extends ModuleSettings implements HasActions
{
    public function fields(): array
    {
        return [
            TextField::make('name')->label('Name'),
            TextField::make('webhook')->label('Webhook'),
        ];
    }

    public function secrets(): array
    {
        return ['webhook'];
    }

    public function mask(string $field, string $value): string
    {
        return parse_url($value, PHP_URL_HOST).'/••••';
    }

    public function actions(): array
    {
        return [['name' => 'ping', 'label' => 'Ping'], ['name' => 'break', 'label' => 'Break']];
    }

    public function run(string $action): string
    {
        return match ($action) {
            'ping' => 'Pong from '.Setting::get('vault.webhook', 'nowhere'),
            default => throw new ActionFailed('It broke.'),
        };
    }
}
