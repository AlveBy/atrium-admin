<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\DataImport;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The mapping and the options of an import, checked the same way whether the
 * panel or the console sets them: a target for a column at most, each target
 * for one column only, and the column the records are found by among them.
 */
final class Settings
{
    /**
     * The settings of the import besides the mapping: the separator of a path
     * when the module has a tree to point into, and what the module adds.
     *
     * @param  class-string  $model
     * @return array<int,ImportOption>
     */
    public static function options(string $model): array
    {
        $own = Targets::for($model)->hasTree()
            ? [new ImportOption('separator', __('atrium::import.settings.separator'), ImportOption::TEXT, '/', __('atrium::import.show.separator_hint'))]
            : [];

        return [...$own, ...array_values(Hook::apply('atrium.import.options', [], $model))];
    }

    /**
     * The options as they are when nobody has set them.
     *
     * @param  array<int,ImportOption>  $options
     * @return array<string,bool|string>
     */
    public static function defaults(array $options): array
    {
        $defaults = ['mode' => 'both'];

        foreach ($options as $option) {
            $defaults[$option->name] = $option->default;
        }

        return $defaults;
    }

    /**
     * The key a file is found by: the first of the keys that a column fills,
     * the module's first one when none does.
     *
     * @param  array<int,string|null>  $mapping
     */
    public static function key(array $mapping, Targets $targets): string
    {
        $keys = $targets->keys();

        foreach ($keys as $key) {
            if (in_array($targets->keyTarget($key['key'])->key, $mapping, true)) {
                return $key['key'];
            }
        }

        return $keys[0]['key'];
    }

    /**
     * @param  array<string,mixed>  $input  mapping (a target or null per column), key, mode and the module's options
     * @param  array<int,ImportOption>  $options
     * @return array{mapping:array<int,string|null>,options:array<string,mixed>}
     *
     * @throws ValidationException
     */
    public static function validate(array $input, int $columns, Targets $targets, array $options = []): array
    {
        $rules = [
            'mapping' => ['required', 'list', 'size:'.$columns],
            'mapping.*' => ['nullable', 'string', Rule::in(array_keys($targets->all()))],
            'key' => ['required', Rule::in(array_column($targets->keys(), 'key'))],
            'mode' => ['required', Rule::in(DataImport::MODES)],
        ];
        $attributes = [
            'mapping' => __('atrium::import.settings.mapping'),
            'key' => __('atrium::import.settings.key'),
            'mode' => __('atrium::import.settings.mode'),
        ];

        foreach ($options as $option) {
            $rules[$option->name] = $option->type === ImportOption::BOOLEAN ? ['boolean'] : ['nullable', 'string', 'max:255'];
            $attributes[$option->name] = $option->label;
        }

        $attributes['mapping.*'] = $attributes['mapping'];
        $data = Validator::make($input, $rules, [], $attributes)->validate();

        $mapped = array_filter($data['mapping'], static fn (?string $target): bool => $target !== null);
        $twice = array_keys(array_filter(array_count_values($mapped), static fn (int $count): bool => $count > 1));

        if ($twice !== []) {
            throw ValidationException::withMessages(['mapping' => __('atrium::import.validation.twice', [
                'fields' => implode(', ', array_map(static fn (string $key): string => $targets->get($key)->label, $twice)),
            ])]);
        }

        if (! in_array($targets->keyTarget($data['key'])->key, $mapped, true)) {
            throw ValidationException::withMessages(['key' => __('atrium::import.validation.key', [
                'field' => $targets->keyTarget($data['key'])->label,
            ])]);
        }

        $settings = ['key' => $data['key'], 'mode' => $data['mode']];

        foreach ($options as $option) {
            $value = $data[$option->name] ?? null;
            $settings[$option->name] = $option->type === ImportOption::BOOLEAN ? (bool) $value : (string) ($value ?? $option->default);
        }

        return ['mapping' => $data['mapping'], 'options' => $settings];
    }
}
