<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Embeds;

use Atrium\Admin\Hooks\Hooks;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * The embed types of the site and their placeholders in the text.
 *
 * Types are a registry of the hooks (REGISTRY, by the type's key); this
 * class reads it and replaces the placeholders the editor leaves in HTML.
 */
final class Embeds
{
    /** The hook registry that modules put their types in. */
    public const REGISTRY = 'atrium.embeds';

    /** A placeholder: an empty div that carries `data-atrium-embed`. */
    private const PLACEHOLDER = '#<div\b[^>]*\sdata-atrium-embed\s*=[^>]*>\s*</div>#i';

    private const ATTRIBUTE = '#\sdata-(atrium-embed|id)\s*=\s*(["\'])(.*?)\2#i';

    public function __construct(private readonly Hooks $hooks) {}

    /**
     * The types that work now, by key.
     *
     * @return array<string,Embed>
     */
    public function all(): array
    {
        return array_filter(
            $this->hooks->registered(self::REGISTRY),
            static fn (mixed $embed): bool => $embed instanceof Embed && $embed->available(),
        );
    }

    /**
     * The types this administrator may use, for the editor's "Insert" list.
     *
     * @return array<int,array{type:string,label:string,url:string}>
     */
    public function types(?AdminUser $user): array
    {
        $types = [];

        foreach ($this->all() as $key => $embed) {
            if ($embed->permitted($user)) {
                $types[] = ['type' => (string) $key, 'label' => $embed->label(), 'url' => route('admin.embeds', ['type' => $key], false)];
            }
        }

        return $types;
    }

    public function get(string $key): ?Embed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * The text with every placeholder swapped for its embed. A placeholder
     * of an unknown type, a missing item or an embed that fails leaves
     * nothing: a visitor never sees a broken page for it.
     */
    public function render(Htmlable|string|null $html): string
    {
        $html = (string) $html;

        if (stripos($html, 'data-atrium-embed') === false) {
            return $html;
        }

        return (string) preg_replace_callback(self::PLACEHOLDER, fn (array $match): string => $this->one($match[0]), $html);
    }

    private function one(string $placeholder): string
    {
        preg_match_all(self::ATTRIBUTE, $placeholder, $found, PREG_SET_ORDER);

        $values = [];

        foreach ($found as $attribute) {
            $values[strtolower($attribute[1])] = html_entity_decode($attribute[3]);
        }

        $embed = $this->get($values['atrium-embed'] ?? '');

        try {
            return $embed?->render($values['id'] ?? '') ?? '';
        } catch (Throwable $error) {
            report($error);

            return '';
        }
    }
}
