<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Templates;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * The "Template" field in the form of every module with a public page that
 * has a template besides the default one, through the core's hooks: the
 * choice is kept in admin_record_templates, not in a column of the module.
 */
final class TemplatesExtension
{
    /** The name of the field in the form. */
    public const FIELD = 'template';

    public static function register(): void
    {
        $templates = static fn (): Templates => app(Templates::class);

        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => self::fields($templates(), $fields, $model));

        Hook::filter('atrium.record.data', static function (array $data, Model $record) use ($templates): array {
            if (! self::stored($templates(), $record::class) || ! array_key_exists(self::FIELD, $data)) {
                return $data;
            }

            $key = $data[self::FIELD];
            unset($data[self::FIELD]);
            $templates()->hold($record::class, is_string($key) ? $key : null);

            return $data;
        });

        Hook::filter('atrium.record.present', static function (array $data, ?Model $record, string $context) use ($templates): array {
            if ($context !== 'form' || $record === null || ! $record->exists || ! self::stored($templates(), $record::class)) {
                return $data;
            }

            $data[self::FIELD] = $templates()->key($record);

            return $data;
        });

        Hook::on('atrium.record.saved', static fn (Model $record) => $templates()->release($record));

        // The choice goes with the record.
        Event::listen('eloquent.deleted: *', static function (string $event, array $payload) use ($templates): void {
            $record = $payload[0] ?? null;

            if ($record instanceof Model && PublicUrl::ruleFor($record::class) !== null && $templates()->ownColumn($record::class) === null) {
                $templates()->pick($record, null);
            }
        });
    }

    /**
     * Whether the choice for the model is kept by this extension: it has a
     * page, a template besides the default and no column of its own.
     *
     * @param  class-string<Model>  $model
     */
    private static function stored(Templates $templates, string $model): bool
    {
        return $templates->ownColumn($model) === null && $templates->offers($model);
    }

    /**
     * @param  array<int,Field>  $fields
     * @return array<int,Field>
     */
    private static function fields(Templates $templates, array $fields, string $model): array
    {
        if (! is_subclass_of($model, Model::class) || ! self::stored($templates, $model)) {
            return $fields;
        }

        $field = SelectField::make(self::FIELD)
            ->label(__('atrium::templates.label'))
            ->hint(__('atrium::templates.hint'))
            ->options($templates->options($model))
            ->default(Templates::DEFAULT)
            ->tab(self::tab($model));

        return [...$fields, $field];
    }

    /**
     * The first tab of the form, the one the field is shown on.
     *
     * @param  class-string<Model>  $model
     */
    private static function tab(string $model): string
    {
        if (! method_exists($model, 'getAdminTabs') || (method_exists($model, 'usesTabs') && ! $model::usesTabs())) {
            return 'params';
        }

        $tab = $model::getAdminTabs()[0] ?? null;

        return $tab instanceof Tab ? $tab->getName() : (string) ($tab['name'] ?? 'params');
    }
}
