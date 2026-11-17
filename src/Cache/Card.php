<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Cache;

use ArrayAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use LogicException;

/**
 * One record as the record cache keeps it, in one language: built once from
 * the database, then read back with no query at all.
 *
 * It is stored as a plain array (scalars only, safe on every cache store
 * and across deploys):
 *
 *     id, type, locale, site
 *     raw        the row as the database returns it; a translated column
 *                holds the card's language and the default one only
 *     relations  name => null | {type, raw, relations} | list of them
 *     seo        title, description, keywords, og_image
 *     media      field => list of {url, path, name, thumbnails}
 *     url        the record's public address (PublicUrl::forRecord)
 *     extra      what modules added (RecordCache::extend()): the theme's
 *                page, custom field values …
 *
 * Read it as an array or through the methods. `$card->name` and
 * `$card['name']` go through the model's casts and translations, exactly as
 * `$model->name` would. `model()` gives the record back as a read-only
 * Eloquent model with its declared relations set: templates that want a
 * model get one without a query.
 *
 * @implements ArrayAccess<string,mixed>
 */
final class Card implements ArrayAccess, JsonSerializable
{
    private ?Model $model = null;

    /**
     * @param  array<string,mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    public function id(): int|string
    {
        return $this->data['id'];
    }

    /**
     * @return class-string<Model>
     */
    public function type(): string
    {
        return $this->data['type'];
    }

    public function locale(): string
    {
        return (string) $this->data['locale'];
    }

    /**
     * An attribute through the model's casts and translations.
     */
    public function get(string $attribute, mixed $default = null): mixed
    {
        return $this->model()->getAttribute($attribute) ?? $default;
    }

    /**
     * The row as the database returns it.
     *
     * @return array<string,mixed>
     */
    public function raw(): array
    {
        return $this->data['raw'];
    }

    /**
     * A declared relation as a model, a collection of models, or null.
     *
     * @return Model|Collection<int,Model>|null
     */
    public function relation(string $name): Model|Collection|null
    {
        return $this->model()->getRelationValue($name);
    }

    public function seo(string $key): ?string
    {
        $value = $this->data['seo'][$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The pictures or files of a form field.
     *
     * @return array<int,array{url:string,path:string,name:string,thumbnails:array<string,string>}>
     */
    public function media(string $field): array
    {
        return $this->data['media'][$field] ?? [];
    }

    /**
     * The first picture of the first filled-in field.
     */
    public function picture(string ...$fields): ?string
    {
        foreach ($fields as $field) {
            if (($url = $this->media($field)[0]['url'] ?? null) !== null) {
                return $url;
            }
        }

        return null;
    }

    public function url(): ?string
    {
        $url = $this->data['url'] ?? null;

        return is_string($url) ? $url : null;
    }

    /**
     * What a module added to the card under its name.
     */
    public function extra(string $name, mixed $default = null): mixed
    {
        return $this->data['extra'][$name] ?? $default;
    }

    /**
     * The record as an Eloquent model, its relations set from the card.
     * Read it, never save it: it holds one language only. Name the class
     * you expect to get it typed.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $as
     * @return T
     */
    public function model(string $as = Model::class): Model
    {
        $model = $this->model ??= self::hydrate($this->data, $this->locale());

        if (! $model instanceof $as) {
            throw new LogicException('The card holds a '.$model::class.', not a '.$as.'.');
        }

        return $model;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id(),
            'type' => $this->type(),
            'locale' => $this->locale(),
            'attributes' => $this->model()->attributesToArray(),
            'seo' => $this->data['seo'] ?? [],
            'media' => $this->data['media'] ?? [],
            'url' => $this->url(),
            'extra' => $this->data['extra'] ?? [],
        ];
    }

    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    public function __isset(string $name): bool
    {
        return $this->get($name) !== null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->get((string) $offset) !== null;
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('A cached record is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('A cached record is read-only.');
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private static function hydrate(array $data, string $locale): Model
    {
        /** @var class-string<Model> $type */
        $type = $data['type'];
        $prototype = new $type;
        // Named like a model read by a query, so is() and the connection agree.
        $model = $prototype->newFromBuilder($data['raw'], $prototype->getConnection()->getName());

        // Translated attributes read in the card's language, whatever the
        // app's language is now.
        if (method_exists($model, 'setLocale')) {
            $model->setLocale($locale);
        }

        foreach ($data['relations'] ?? [] as $name => $related) {
            if ($related === null) {
                $model->setRelation($name, null);
            } elseif (array_is_list($related)) {
                $model->setRelation($name, (new $type)->newCollection(array_map(static fn (array $item): Model => self::hydrate($item, $locale), $related)));
            } else {
                $model->setRelation($name, self::hydrate($related, $locale));
            }
        }

        return $model;
    }
}
