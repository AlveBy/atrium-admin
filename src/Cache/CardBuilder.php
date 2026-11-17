<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use Atrium\Admin\Contracts\Cacheable;
use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\MediaUrl;
use Atrium\Admin\Support\PublicUrl;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Throwable;

/**
 * Builds the cards of records the cache does not have, all of them in one
 * go: one query for the records and one per declared relation, however
 * many records and properties there are.
 */
final class CardBuilder
{
    /** @var array<string,array<int,string>> */
    private array $relations = [];

    /** @var array<string,array<int,string>> */
    private array $dependencies = [];

    /** @var array<string,array<string,array<int,string>>> */
    private array $mediaFields = [];

    /** @var array<string,array<int,mixed>> */
    private array $fields = [];

    /**
     * @param  array<string,Closure(Collection<int,Model>,string):array<int|string,mixed>>  $extenders
     */
    public function __construct(
        private readonly array $extenders = [],
        private readonly ?Closure $thumbnails = null,
    ) {}

    /**
     * @param  class-string<Model>  $class
     * @param  array<int,int|string>  $ids
     * @return array<int|string,array<string,mixed>> id => card data
     */
    public function build(string $class, array $ids, string $locale, int $site): array
    {
        if ($ids === []) {
            return [];
        }

        $previous = app()->getLocale();
        app()->setLocale($locale);

        try {
            /** @var Collection<int,Model> $models */
            $models = $class::query()->whereKey($ids)->with($this->relationsOf($class))->get();

            $extra = [];

            foreach ($this->extenders as $name => $extender) {
                // A module's broken extender costs its own data, not the page.
                $extra[$name] = (array) ($this->safely(static fn (): mixed => $extender($models, $locale)) ?? []);
            }

            $cards = [];

            foreach ($models as $model) {
                $id = $model->getKey();
                $cards[$id] = [
                    'id' => $id,
                    'type' => $class,
                    'locale' => $locale,
                    'site' => $site,
                    'raw' => $this->raw($model, $locale),
                    'relations' => $this->relationData($model, $locale),
                    'seo' => $this->seo($model),
                    'media' => $this->media($model),
                    'url' => $this->safely(static fn (): ?string => PublicUrl::forRecord($model)),
                    'extra' => array_filter(array_map(static fn (array $values): mixed => $values[$id] ?? null, $extra), static fn (mixed $value): bool => $value !== null),
                ];
            }

            return $cards;
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @param  class-string<Model>  $class
     * @return array<int,string>
     */
    public function relationsOf(string $class): array
    {
        if (isset($this->relations[$class])) {
            return $this->relations[$class];
        }

        $model = new $class;

        return $this->relations[$class] = $model instanceof Cacheable ? array_values($model->cacheRelations()) : [];
    }

    /**
     * The classes a card shows something of: saving any record of them
     * makes the cards rebuild. Morph relations are owned by the record
     * (its SEO meta, its pictures) and drop its card themselves.
     *
     * @param  class-string<Model>  $class
     * @return array<int,string>
     */
    public function dependenciesOf(string $class): array
    {
        if (isset($this->dependencies[$class])) {
            return $this->dependencies[$class];
        }

        $model = new $class;
        $classes = [];

        foreach ($this->relationsOf($class) as $name) {
            $relation = $this->safely(static fn (): mixed => $model->{$name}());

            if ($relation instanceof Relation && ! $relation instanceof MorphOneOrMany && ! $relation instanceof MorphTo) {
                $classes[] = $relation->getRelated()::class;
            }
        }

        // What the form links to: modules show the linked record's name.
        foreach ($this->fieldsOf($class) as $field) {
            if ($field instanceof BelongsToField && ($related = $field->getModelClass()) !== null) {
                $classes[] = $related;
            }
        }

        // A record of its own class is dropped by referencing() instead:
        // otherwise every save would rebuild every card of the class.
        $classes = array_filter($classes, static fn (string $related): bool => $related !== $class);

        // The address is made of the sections' codes, its own class's too
        // for a section: moving one moves the addresses below it.
        $tree = PublicUrl::ruleFor($class)['tree'] ?? null;

        if (is_string($tree) && $tree !== '') {
            $classes[] = $tree;
        }

        return $this->dependencies[$class] = array_values(array_unique($classes));
    }

    /**
     * Records of the same class whose cards show this one (related
     * products, an analogue): their cards go when it is saved.
     *
     * @return array<int,int|string>
     */
    public function referencing(Model $record): array
    {
        $class = $record::class;
        $ids = [];

        foreach ($this->fieldsOf($class) as $field) {
            if (! $field instanceof BelongsToField || $field->getModelClass() !== $class) {
                continue;
            }

            $query = $class::query()->withoutGlobalScopes();
            $name = $field->getName();

            if ($field->getType() === 'belongsToMany') {
                if (! method_exists($record, $name) || ! $record->{$name}() instanceof BelongsToMany) {
                    continue;
                }

                $query->whereHas($name, static fn ($related) => $related->withoutGlobalScopes()->whereKey($record->getKey()));
            } else {
                $value = $record->getAttribute($field->getValueColumn());

                if ($value === null || $field->getColumn() === $record->getKeyName()) {
                    continue;
                }

                $query->where($field->getColumn(), $value);
            }

            $ids = [...$ids, ...$query->whereKeyNot($record->getKey())->pluck($record->getKeyName())->all()];
        }

        return array_values(array_unique($ids));
    }

    /**
     * The row, a translated column reduced to the card's language and the
     * default one: fifteen languages of a long text would make every card
     * fifteen times bigger.
     *
     * @return array<string,mixed>
     */
    private function raw(Model $model, string $locale): array
    {
        $raw = $model->getAttributes();

        if (! method_exists($model, 'getTranslatableAttributes') || ! method_exists($model, 'getTranslations')) {
            return $raw;
        }

        $default = app(ContentLanguages::class)->default();

        foreach ($model->getTranslatableAttributes() as $attribute) {
            if (! isset($raw[$attribute])) {
                continue;
            }

            $translations = (array) $model->getTranslations($attribute);
            $kept = array_intersect_key($translations, array_flip([$locale, $default]));
            $raw[$attribute] = json_encode($kept, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $raw;
    }

    /**
     * @return array<string,mixed>
     */
    private function relationData(Model $model, string $locale): array
    {
        $data = [];

        foreach ($model->getRelations() as $name => $value) {
            if ($value instanceof Model) {
                $data[$name] = $this->related($value, $locale);
            } elseif ($value instanceof Collection) {
                $data[$name] = $value->map(fn (Model $item): array => $this->related($item, $locale))->values()->all();
            } elseif ($value === null) {
                $data[$name] = null;
            }
        }

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function related(Model $model, string $locale): array
    {
        return ['id' => $model->getKey(), 'type' => $model::class, 'raw' => $this->raw($model, $locale), 'relations' => []];
    }

    /**
     * @return array<string,string|null>
     */
    private function seo(Model $model): array
    {
        if (! method_exists($model, 'getSeoTitle')) {
            return [];
        }

        return [
            'title' => $this->safely(static fn (): string => (string) $model->getSeoTitle()),
            'description' => method_exists($model, 'getSeoDescription') ? $this->safely(static fn (): string => (string) $model->getSeoDescription()) : null,
            'keywords' => method_exists($model, 'getSeoKeywords') ? $this->safely(static fn (): ?string => $model->getSeoKeywords()) : null,
            'og_image' => method_exists($model, 'getOgImage') ? $this->safely(static fn (): ?string => $model->getOgImage()) : null,
        ];
    }

    /**
     * Pictures and files of the form's image, gallery and file fields, with
     * their addresses worked out once.
     *
     * @return array<string,array<int,array{url:string,path:string,name:string,thumbnails:array<string,string>}>>
     */
    private function media(Model $model): array
    {
        $media = [];

        foreach ($this->mediaFieldsOf($model::class) as $column) {
            $items = [];

            foreach ($this->list($model->getAttribute($column)) as $item) {
                $url = MediaUrl::to($item);

                if ($url === null) {
                    continue;
                }

                $path = is_array($item) ? (string) ($item['path'] ?? $item['url'] ?? '') : (string) $item;
                $name = is_array($item) && isset($item['name']) ? (string) $item['name'] : rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));

                $items[] = [
                    'url' => $url,
                    'path' => $path,
                    'name' => $name,
                    'thumbnails' => $this->thumbnails === null ? [] : (array) ($this->thumbnails)($path, $url),
                ];
            }

            if ($items !== []) {
                $media[$column] = $items;
            }
        }

        return $media;
    }

    /**
     * @return array<int,string>
     */
    private function mediaFieldsOf(string $class): array
    {
        if (isset($this->mediaFields[$class]['columns'])) {
            return $this->mediaFields[$class]['columns'];
        }

        $columns = [];

        foreach ($this->fieldsOf($class) as $field) {
            if ($field instanceof Field && in_array($field->getType(), ['image', 'gallery', 'file'], true)) {
                $columns[] = $field->getColumn();
            }
        }

        return $this->mediaFields[$class]['columns'] = $columns;
    }

    /**
     * @return array<int,mixed>
     */
    private function fieldsOf(string $class): array
    {
        return $this->fields[$class] ??= method_exists($class, 'getAdministrationFields') ? array_values((array) $class::getAdministrationFields()) : [];
    }

    /**
     * A value that may be one item, a list, or a JSON list.
     *
     * @return array<int|string,mixed>
     */
    private function list(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value) && str_starts_with(ltrim($value), '[')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_array($value) && (array_is_list($value) || ! (isset($value['path']) || isset($value['url'])))) {
            return $value;
        }

        return [$value];
    }

    /**
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T|null
     */
    private function safely(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
