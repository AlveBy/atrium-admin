<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\DisplayField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FileField;
use Atrium\Admin\Fields\LinkField;
use Atrium\Admin\Fields\MapField;
use Atrium\Admin\Fields\TreeField;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Support\ContentLanguages;

/**
 * What a column of the file can fill: the id, and every field of the module's
 * form that takes a value from text — the SEO fields and custom fields
 * included, a field kept per content language once per language. A module or
 * a package changes the list with the `atrium.import.targets` filter: adds a
 * target, or replaces one with its own way of reading a cell.
 *
 * The mapping is guessed from the headers: a header that reads like a field's
 * label in any of the panel's languages, its name or a usual synonym ("Title",
 * "Описание") maps to that field. Export writes the same targets in the same
 * order, so a file it wrote maps itself.
 */
final class Targets
{
    public const ID = 'id';

    /** Headers other programs use besides the labels of the form. */
    private const SYNONYMS = [
        'name' => ['title', 'наименование'],
        'code' => ['slug', 'url', 'чпу', 'символьныйкод'],
        'detail_text' => ['description', 'описание'],
        'preview_text' => ['shortdescription', 'summary', 'анонс'],
        'preview_picture' => ['image', 'picture', 'photo', 'картинка', 'изображение', 'фото'],
        'active' => ['enabled', 'published', 'активность'],
        'sort' => ['order', 'position', 'сортировка'],
    ];

    /** @var array<string,Target>|null */
    private ?array $all = null;

    /**
     * @param  class-string  $model
     */
    public function __construct(private readonly string $model, private readonly RecordWriter $writer, private readonly ContentLanguages $languages) {}

    /**
     * @param  class-string  $model
     */
    public static function for(string $model): self
    {
        return new self($model, new RecordWriter($model), app(ContentLanguages::class));
    }

    /**
     * @return array<string,Target>
     */
    public function all(): array
    {
        return $this->all ??= $this->build();
    }

    public function get(string $key): ?Target
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Whether a column can point into a tree of records: the separator of a path matters then.
     */
    public function hasTree(): bool
    {
        foreach ($this->all() as $target) {
            if ($target->field instanceof TreeSelectField) {
                return true;
            }
        }

        return false;
    }

    /**
     * For the mapping screen: key and label, in the form's order.
     *
     * @return array<int,array{key:string,label:string}>
     */
    public function options(): array
    {
        return array_values(array_map(static fn (Target $target): array => ['key' => $target->key, 'label' => $target->label], $this->all()));
    }

    /**
     * The fields a record can be found by again: those the module names
     * (getImportKeys(), the id and the code by default) that it has.
     *
     * @return array<int,array{key:string,label:string}>
     */
    public function keys(): array
    {
        $declared = method_exists($this->model, 'getImportKeys') ? $this->model::getImportKeys() : [self::ID, 'code'];
        $keys = [];

        foreach ($declared as $key) {
            $target = $this->keyTarget($key);

            if ($target !== null) {
                $keys[] = ['key' => $key, 'label' => $target->label];
            }
        }

        return $keys;
    }

    /**
     * The target a key is filled by: a field kept per language is the default
     * language's.
     */
    public function keyTarget(string $key): ?Target
    {
        return $this->get($key) ?? $this->get($key.'.'.$this->languages->default());
    }

    /**
     * A target for each header, or null. A target takes one column only: the
     * first header that names it.
     *
     * @param  array<int,string>  $headers
     * @return array<int,string|null>
     */
    public function guess(array $headers): array
    {
        $aliases = $this->aliases();
        $used = [];
        $mapping = [];

        foreach ($headers as $index => $header) {
            $key = $aliases[Cells::normalize($header)] ?? null;
            $mapping[$index] = null;

            if ($key !== null && ! isset($used[$key])) {
                $mapping[$index] = $key;
                $used[$key] = $index;
            }
        }

        return $mapping;
    }

    /**
     * @return array<string,Target>
     */
    private function build(): array
    {
        $translatable = $this->writer->translatable();
        $multilingual = $this->languages->isMultilingual();
        $targets = [self::ID => new Target(self::ID, 'ID')];

        foreach ($this->writer->fields() as $field) {
            $name = $field->getName();

            if (self::skipped($field)) {
                continue;
            }

            if ($multilingual && in_array($name, $translatable, true)) {
                foreach ($this->languages->all() as $language) {
                    $targets["{$name}.{$language->code}"] = new Target(
                        "{$name}.{$language->code}",
                        "{$field->getLabel()} ({$language->name})",
                        $field,
                        $language->code,
                        $field->getLabel(),
                    );
                }

                continue;
            }

            $targets[$name] = new Target($name, $field->getLabel(), $field);
        }

        /** @var array<string,Target> $targets */
        $targets = Hook::apply('atrium.import.targets', $targets, $this->model);

        return $targets;
    }

    /**
     * Fields a cell of text cannot fill: read-only ones, links to many
     * records, a tree of several choices, maps, documents.
     */
    private static function skipped(Field $field): bool
    {
        foreach ([DisplayField::class, BelongsToManyField::class, TreeField::class, FileField::class, LinkField::class, MapField::class] as $class) {
            if ($field instanceof $class) {
                return true;
            }
        }

        return $field instanceof TreeSelectField && $field->toArray()['multiple'];
    }

    /**
     * Normalized header => target key: the labels in every language of the
     * panel, the keys themselves, the synonyms. What comes first wins.
     *
     * @return array<string,string>
     */
    private function aliases(): array
    {
        $aliases = [];
        $default = $this->languages->default();
        $current = app()->getLocale();

        foreach (array_keys(config('atrium.locales')) as $locale) {
            app()->setLocale($locale);

            foreach ($this->build() as $key => $target) {
                $aliases[Cells::normalize($target->label)] ??= $key;

                // "Name" alone is the default language's name.
                if ($target->locale === $default) {
                    $aliases[Cells::normalize($target->base ?? '')] ??= $key;
                }
            }
        }

        app()->setLocale($current);

        foreach ($this->all() as $key => $target) {
            $aliases[Cells::normalize($key)] ??= $key;

            foreach ($target->synonyms as $synonym) {
                $aliases[$synonym] ??= $key;
            }
        }

        // A module may have taken a field out of the form (atrium.module.fields).
        foreach (self::SYNONYMS as $key => $synonyms) {
            $key = $this->keyTarget($key)?->key;

            foreach ($key === null ? [] : $synonyms as $synonym) {
                $aliases[$synonym] ??= $key;
            }
        }

        return $aliases;
    }
}
