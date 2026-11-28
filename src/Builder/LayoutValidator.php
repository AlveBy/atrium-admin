<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Builder;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Support\ContentLanguages;
use Illuminate\Support\Facades\Validator;

/**
 * Checks a page's tree before it is saved: its shape, and every block's
 * settings against the fields of its type. The first problem is told with
 * its place.
 *
 * A block that arrives exactly as it is stored is not checked again, so a
 * page stays editable when a module was removed or a chosen record deleted
 * since the block was written.
 */
final class LayoutValidator
{
    /** @var array<array-key,string> the ids met so far */
    private array $ids = [];

    /** @var array<string,string> id => fingerprint of the stored block */
    private array $kept = [];

    /** Whether the blocks have to be filled in. */
    private bool $complete = true;

    /** The id of the node the last problem is in. */
    private ?string $failed = null;

    public function __construct(
        private readonly BlockTypes $types,
        private readonly ContentLanguages $languages,
    ) {}

    /**
     * What is wrong with the tree, or null.
     *
     * @param  array<array-key,mixed>  $layout
     * @param  mixed  $stored  the tree the record holds now
     * @param  bool  $complete  false for a draft: the tree has to be sound, its blocks may still be unfinished
     */
    public function problem(array $layout, mixed $stored = null, bool $complete = true): ?string
    {
        $this->complete = $complete;
        $this->failed = null;
        $this->ids = [];
        $this->kept = $this->fingerprints($stored);
        $sections = $layout['sections'] ?? null;

        if (! $this->only($layout, ['version', 'sections']) || ($layout['version'] ?? null) !== Layout::VERSION || ! $this->isList($sections)) {
            return $this->say('invalid');
        }

        if (count($sections) > Layout::MAX_SECTIONS) {
            return $this->say('too_many', ['max' => Layout::MAX_SECTIONS]);
        }

        foreach ($sections as $index => $section) {
            $problem = $this->section($section, $index + 1);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * The id of the section, column or block the problem() found is in; null
     * when there was none, or it is not in one node with an id.
     */
    public function failedAt(): ?string
    {
        return $this->failed;
    }

    private function section(mixed $section, int $number): ?string
    {
        $place = ['section' => $number];
        $problem = $this->node($section, ['id', 'settings', 'columns'])
            ?? $this->children($section['columns'] ?? null, Layout::MAX_COLUMNS)
            ?? $this->settings($section['settings'] ?? [], Layout::sectionFields());

        if ($problem !== null) {
            return $this->at('section', $place, $problem, $section);
        }

        foreach ($section['columns'] as $index => $column) {
            $problem = $this->column($column, $place + ['column' => $index + 1]);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * @param  array<string,int>  $place
     */
    private function column(mixed $column, array $place): ?string
    {
        $problem = $this->node($column, ['id', 'settings', 'blocks'])
            ?? $this->children($column['blocks'] ?? null, Layout::MAX_BLOCKS)
            ?? $this->settings($column['settings'] ?? [], Layout::columnFields());

        if ($problem !== null) {
            return $this->at('column', $place, $problem, $column);
        }

        foreach ($column['blocks'] as $index => $block) {
            $problem = $this->block($block, $place + ['block' => $index + 1]);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * @param  array<string,int>  $place
     */
    private function block(mixed $block, array $place): ?string
    {
        $key = is_array($block) && is_string($block['type'] ?? null) ? $block['type'] : '';
        $type = $this->types->get($key);
        $problem = $this->node($block, ['id', 'type', 'settings']);

        if ($problem === null && ($this->kept[$block['id']] ?? null) !== $this->fingerprint($block)) {
            $problem = match (true) {
                $key === '' => $this->say('invalid'),
                ! $this->complete => $this->isMap($block['settings'] ?? []) ? null : $this->say('invalid'),
                $type === null => $this->say('unknown_type', ['type' => $key]),
                default => $this->settings($block['settings'] ?? [], $type->fields(), $type->translatable()),
            };
        }

        return $problem === null ? null : $this->at('block', $place + ['type' => $type?->label() ?? $key], $problem, $block);
    }

    /**
     * A node is a map of known keys with an id of its own.
     *
     * @param  array<int,string>  $keys
     */
    private function node(mixed $node, array $keys): ?string
    {
        if (! is_array($node) || ! $this->only($node, $keys)) {
            return $this->say('invalid');
        }

        $id = $node['id'] ?? null;

        if (! is_string($id) || preg_match(Layout::ID, $id) !== 1) {
            return $this->say('invalid');
        }

        if (isset($this->ids[$id])) {
            return $this->say('duplicate_id', ['id' => $id]);
        }

        $this->ids[$id] = $id;

        return null;
    }

    private function children(mixed $list, int $max): ?string
    {
        if (! $this->isList($list)) {
            return $this->say('invalid');
        }

        return count($list) > $max ? $this->say('too_many', ['max' => $max]) : null;
    }

    /**
     * @param  array<int,Field>  $fields
     * @param  array<int,string>  $translatable
     */
    private function settings(mixed $settings, array $fields, array $translatable = []): ?string
    {
        if (! $this->isMap($settings)) {
            return $this->say('invalid');
        }

        $names = array_map(static fn (Field $field): string => $field->getName(), $fields);

        foreach (array_keys($settings) as $name) {
            if (! in_array($name, $names, true)) {
                return $this->say('unknown_setting', ['name' => $name]);
            }
        }

        foreach ($fields as $field) {
            $name = $field->getName();
            $problem = in_array($name, $translatable, true)
                ? $this->translations($field, $settings[$name] ?? [])
                : $this->value($field, $settings, $name, $field->getLabel(), true);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * As on a form: the default language is held to the field's rules, the
     * others may stay empty.
     */
    private function translations(Field $field, mixed $values): ?string
    {
        if (! $this->isMap($values)) {
            return $this->say('invalid');
        }

        $default = $this->languages->default();

        // The default language first, written or not.
        foreach (array_keys([$default => null] + $values) as $code) {
            $label = $this->languages->isMultilingual() ? "{$field->getLabel()} ({$code})" : $field->getLabel();
            $problem = $this->value($field, $values, $code, $label, $code === $default);

            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key,mixed>  $values
     */
    private function value(Field $field, array $values, int|string $key, string $label, bool $mayRequire): ?string
    {
        $name = $field->getName();
        $value = $values[$key] ?? null;
        $rules = $field->getValidationRules(FieldContext::create($value, array_key_exists($key, $values)));

        if (! $mayRequire) {
            $rules = ['nullable', ...array_filter($rules, static fn (mixed $rule): bool => $rule !== 'required' && $rule !== 'nullable')];
        }

        $validator = Validator::make([$name => $value], [$name => $rules], [], [$name => $label === '' ? $name : $label]);

        return $validator->fails() ? $validator->errors()->first() : null;
    }

    /**
     * @return array<string,string> id => fingerprint
     */
    private function fingerprints(mixed $stored): array
    {
        $prints = [];

        foreach (Layout::sections($stored) as $section) {
            foreach (Layout::nodes($section['columns'] ?? null) as $column) {
                foreach (Layout::nodes($column['blocks'] ?? null) as $block) {
                    if (is_string($block['id'] ?? null)) {
                        $prints[$block['id']] = $this->fingerprint($block);
                    }
                }
            }
        }

        return $prints;
    }

    /**
     * The same text for the same block, however its keys are ordered (a JSON
     * column of MySQL reorders them).
     *
     * @param  array<array-key,mixed>  $block
     */
    private function fingerprint(array $block): string
    {
        return Layout::fingerprint([$block['type'] ?? null, $block['settings'] ?? []]);
    }

    /**
     * @param  array<array-key,mixed>  $node
     * @param  array<int,string>  $keys
     */
    private function only(array $node, array $keys): bool
    {
        return array_diff(array_keys($node), $keys) === [];
    }

    /**
     * @phpstan-assert-if-true array<int,mixed> $value
     */
    private function isList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value);
    }

    /**
     * An empty array is both: JSON `{}` and `[]` read the same in PHP.
     *
     * @phpstan-assert-if-true array<array-key,mixed> $value
     */
    private function isMap(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }

    /**
     * @param  array<string,int|string>  $replace
     */
    private function say(string $problem, array $replace = []): string
    {
        return __("atrium::fields.layout.problems.{$problem}", $replace);
    }

    /**
     * The problem with its place in front, and the node it is in kept for failedAt().
     *
     * @param  array<string,int|string>  $place
     */
    private function at(string $level, array $place, string $problem, mixed $node): string
    {
        $this->failed = is_array($node) && is_string($node['id'] ?? null) ? $node['id'] : null;

        return __('atrium::fields.layout.at', [
            'place' => __("atrium::fields.layout.places.{$level}", $place),
            'problem' => $problem,
        ]);
    }
}
