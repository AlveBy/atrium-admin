<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Modules;

use Atrium\Admin\Fields\Field;

/**
 * What can be set for a module in the panel: the `settings` class of its
 * manifest. Its fields are the admin's own field types; a field's default()
 * is the value until someone saves another.
 *
 *     final class ShopSettings extends ModuleSettings
 *     {
 *         public bool $perSite = true;
 *
 *         public function fields(): array
 *         {
 *             return [
 *                 SelectField::make('currency')->label(__('atrium-shop::settings.currency'))
 *                     ->options(['EUR' => 'EUR', 'USD' => 'USD'])->default('EUR')->required(),
 *                 NumberField::make('free_delivery_from')->label(__('atrium-shop::settings.free_delivery_from')),
 *             ];
 *         }
 *     }
 *
 * Read the values with `Setting::get('shop.currency')`.
 */
abstract class ModuleSettings
{
    /**
     * True: every site has values of its own, set on the settings screen of
     * the site chosen in the panel's header. False: one set for all sites.
     */
    public bool $perSite = false;

    /**
     * @return array<int,Field>
     */
    abstract public function fields(): array;

    /**
     * Names of the fields that hold a secret (a webhook address, a password):
     * kept encrypted with the application key, never sent back to the screen
     * (an empty field keeps what is saved, the placeholder shows mask()) and
     * read decrypted with Setting::get().
     *
     * @return array<int,string>
     */
    public function secrets(): array
    {
        return [];
    }

    /**
     * What the screen shows in place of a saved secret. Override to keep the
     * recognisable part, such as the host of an address.
     */
    public function mask(string $field, string $value): string
    {
        return str_repeat('•', 8);
    }

    /**
     * A line under the title of the settings screen.
     */
    public function description(): string
    {
        return '';
    }

    /**
     * The values a field's default() gives, by field name.
     *
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->fields() as $field) {
            $defaults[$field->getName()] = $field->getDefault();
        }

        return $defaults;
    }
}
