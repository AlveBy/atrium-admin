<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Transfer;

use Atrium\Admin\Models\DataImport;
use Closure;

/**
 * A tree of records a cell points into: the sections of a catalog, the parent
 * of a page. A cell names one by its name ("Куртки") anywhere in the tree, or
 * by its path from the top ("Одежда / Куртки"), case aside; an export writes
 * the path.
 *
 * What is not there is an error — unless the module gave a function that
 * makes it (the missing part of a path with it): the import is then told to
 * create it. A check only counts what it would create.
 */
final class Trees
{
    /** @var array<int,array{parent:int|null,name:string,label:string}> id => node */
    private array $nodes = [];

    /**
     * @param  array<int,array{value:int|string,label:string,children?:array<int,mixed>}>  $options  the tree as a tree field lists it
     * @param  (Closure(int|null,string):int)|null  $create  makes a node under a parent (null: at the top) and gives its id
     */
    public function __construct(
        array $options,
        private readonly string $field,
        private readonly DataImport $import,
        private readonly bool $check = false,
        private readonly ?Closure $create = null,
    ) {
        $this->collect($options, null);
    }

    /**
     * @param  array<int,array{value:int|string,label:string,children?:array<int,mixed>}>  $options
     */
    private function collect(array $options, ?int $parent): void
    {
        foreach ($options as $option) {
            $id = (int) $option['value'];
            $label = (string) $option['label'];
            $this->nodes[$id] = ['parent' => $parent, 'name' => mb_strtolower($label), 'label' => $label];
            $this->collect($option['children'] ?? [], $id);
        }
    }

    /**
     * What an export writes: the names from the top down, "Clothes / Jackets".
     */
    public function path(?int $id, string $separator = ' / '): string
    {
        $names = [];

        // A cycle in the data ends the walk at the first node met twice.
        for ($seen = []; $id !== null && isset($this->nodes[$id]) && ! in_array($id, $seen, true); $id = $this->nodes[$id]['parent']) {
            $seen[] = $id;
            array_unshift($names, $this->nodes[$id]['label']);
        }

        return implode($separator, $names);
    }

    /**
     * The node's id; null in a check for a node it would create.
     */
    public function find(string $value): ?int
    {
        $parts = array_values(array_filter(
            array_map(trim(...), explode($this->import->option('separator', '/'), $value)),
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            throw RowProblem::because('tree_missing', ['field' => $this->field, 'value' => $value]);
        }

        if (count($parts) === 1) {
            $found = $this->named($parts[0]);

            // A name met in several places is the one at the top, if only one is: that is what an export writes for it.
            if (count($found) > 1) {
                $found = array_values(array_filter($found, fn (int $id): bool => $this->nodes[$id]['parent'] === null));

                if (count($found) !== 1) {
                    throw RowProblem::because('tree_ambiguous', ['field' => $this->field, 'value' => $value]);
                }
            }

            if ($found !== []) {
                return $found[0];
            }
        }

        $parent = null;

        foreach ($parts as $depth => $part) {
            $child = $this->child($parent, $part);

            if ($child === null) {
                return $this->make(array_slice($parts, 0, $depth), array_slice($parts, $depth), $parent, $value);
            }

            $parent = $child;
        }

        return $parent;
    }

    /**
     * @return array<int,int>
     */
    private function named(string $name): array
    {
        $name = mb_strtolower($name);

        return array_keys(array_filter($this->nodes, static fn (array $node): bool => $node['name'] === $name));
    }

    private function child(?int $parent, string $name): ?int
    {
        $name = mb_strtolower($name);

        foreach ($this->nodes as $id => $node) {
            if ($node['parent'] === $parent && $node['name'] === $name) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  array<int,string>  $found  the part of the path that is there
     * @param  array<int,string>  $missing  the part to create
     */
    private function make(array $found, array $missing, ?int $parent, string $value): ?int
    {
        if ($this->create === null) {
            throw RowProblem::because('tree_missing', ['field' => $this->field, 'value' => $value]);
        }

        foreach ($missing as $name) {
            $found[] = $name;

            if ($this->check) {
                $this->import->nodes += $this->import->remember('node', implode('/', $found)) ? 1 : 0;

                continue;
            }

            $id = ($this->create)($parent, $name);

            $this->nodes[$id] = ['parent' => $parent, 'name' => mb_strtolower($name), 'label' => $name];
            $parent = $id;
            $this->import->nodes++;
        }

        return $this->check ? null : $parent;
    }
}
