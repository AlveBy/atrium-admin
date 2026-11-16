<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Actions;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * A button on a record, in the list and on its form, that opens the create
 * form of the same module with fields filled from the record:
 *
 *     public static function getAdministrationActions(): array
 *     {
 *         return [
 *             CreateFromRecord::make('subpage')->label('Subpage')->fill(['parent_id' => 'id']),
 *         ];
 *     }
 *
 * opens /admin/pages/create?parent_id=12 with the parent chosen. Shown only
 * to administrators who may create records here.
 *
 * @implements Arrayable<string,mixed>
 */
final class CreateFromRecord implements Arrayable, JsonSerializable
{
    private string $label = '';

    /** @var array<string,string> form field => attribute of the record */
    private array $fill = [];

    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param  array<string,string>  $fill  form field => attribute of the record
     */
    public function fill(array $fill): self
    {
        $this->fill = $fill;

        return $this;
    }

    /**
     * @return array<string,string>
     */
    public function getFill(): array
    {
        return $this->fill;
    }

    /**
     * @return array{name:string,label:string,fill:array<string,string>}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'label' => $this->label, 'fill' => $this->fill];
    }

    /**
     * @return array{name:string,label:string,fill:array<string,string>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
