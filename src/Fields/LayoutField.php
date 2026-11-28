<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Atrium\Admin\Builder\BlockTypes;
use Atrium\Admin\Builder\Layout;
use Atrium\Admin\Builder\LayoutValidator;
use Closure;

/**
 * A page built of blocks: sections, their columns and the blocks in them
 * (Atrium\Admin\Builder\Layout). The column holds the tree as JSON; cast it
 * to `array` on the model.
 *
 *     LayoutField::make('layout')->label('Blocks')
 *
 * The blocks on offer are those of the registry, with the fields they are set
 * up with.
 */
class LayoutField extends Field
{
    public function getType(): string
    {
        return 'layout';
    }

    protected function getFieldProps(): array
    {
        $types = [];

        foreach (app(BlockTypes::class)->all() as $key => $type) {
            $types[] = [
                'type' => (string) $key,
                'label' => $type->label(),
                'fields' => $this->settingsOf($type->fields(), $type->translatable()),
            ];
        }

        return [
            'types' => $types,
            'sectionFields' => $this->settingsOf(Layout::sectionFields()),
            'columnFields' => $this->settingsOf(Layout::columnFields()),
            'maxSections' => Layout::MAX_SECTIONS,
            'maxColumns' => Layout::MAX_COLUMNS,
            'maxBlocks' => Layout::MAX_BLOCKS,
            'version' => Layout::VERSION,
        ];
    }

    /**
     * The edit form leaves the tree to the page editor and does not send it:
     * a tree that is not sent stays as it is, required or not.
     */
    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = [...$this->baseValidationRules($context), 'array', $this->treeRule($context)];

        return $context?->isEdit() && ! $context->present ? ['sometimes', ...$rules] : $rules;
    }

    /** Whether the form lets the tree change. */
    public function isEditable(): bool
    {
        return ! $this->disabled && ! $this->readonly;
    }

    private function treeRule(?FieldContext $context): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($context): void {
            if (! is_array($value)) {
                return;
            }

            $problem = app(LayoutValidator::class)->problem($value, $context?->record?->getAttribute($this->getColumn()));

            if ($problem !== null) {
                $fail($problem);
            }
        };
    }

    /**
     * @param  array<int,Field>  $fields
     * @param  array<int,string>  $translatable
     * @return array<int,array<string,mixed>>
     */
    private function settingsOf(array $fields, array $translatable = []): array
    {
        return array_map(static fn (Field $field): array => in_array($field->getName(), $translatable, true)
            ? [...$field->toArray(), 'translatable' => true]
            : $field->toArray(), $fields);
    }
}
