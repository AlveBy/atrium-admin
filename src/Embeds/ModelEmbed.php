<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Embeds;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\RecordSearch;
use Illuminate\Database\Eloquent\Model;

/**
 * An embed whose items are the records of a module (one with an integer id):
 * the picker searches the module the way its own list does, only to those
 * who may see it. A subclass names the model, the label and how a record
 * looks on the site.
 */
abstract class ModelEmbed implements Embed
{
    private const LIMIT = 20;

    /** @return class-string<Model> */
    abstract protected function model(): string;

    abstract protected function renderRecord(Model $record): string;

    public function available(): bool
    {
        return app(Modules::class)->available($this->model());
    }

    public function permitted(?AdminUser $user): bool
    {
        return app(RecordSearch::class)->mayView($user, $this->model());
    }

    public function options(string $search): array
    {
        $records = app(RecordSearch::class)->query($this->model(), $search)->limit(self::LIMIT)->get();

        return $this->items($records);
    }

    public function labels(array $ids): array
    {
        $records = app(RecordSearch::class)->query($this->model(), '')->whereKey(array_filter($ids, ctype_digit(...)))->get();

        return $this->items($records);
    }

    public function render(string $id): string
    {
        $record = ctype_digit($id) ? $this->model()::query()->find($id) : null;

        return $record === null ? '' : $this->renderRecord($record);
    }

    /**
     * @param  iterable<Model>  $records
     * @return array<int,array{id:string,label:string}>
     */
    private function items(iterable $records): array
    {
        $search = app(RecordSearch::class);
        $items = [];

        foreach ($records as $record) {
            $items[] = ['id' => (string) $record->getKey(), 'label' => $search->label($record)];
        }

        return $items;
    }
}
