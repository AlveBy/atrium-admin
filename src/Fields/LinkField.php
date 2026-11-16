<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Fields;

use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Support\RecordUrls;
use Closure;

/**
 * Where a link goes: an address typed in, or a record of any installed
 * module chosen from a picker (a page, a product, a news item). A record
 * link follows the record: its address changes when the record moves.
 *
 * The value is null, ['type' => 'url', 'url' => '/contacts'] or
 * ['type' => 'record', 'model' => Page::class, 'id' => 12]. The form shows
 * the record's name; the picker lists the modules whose records have pages.
 */
class LinkField extends Field
{
    public const URL = 'url';

    public const RECORD = 'record';

    protected bool $records = true;

    public function getType(): string
    {
        return 'link';
    }

    /**
     * Addresses only, no record picker.
     */
    public function urlsOnly(): static
    {
        $this->records = false;

        return $this;
    }

    /**
     * The modules a record may be picked from: model class => label.
     *
     * @return array<class-string,string>
     */
    public function targets(): array
    {
        if (! $this->records) {
            return [];
        }

        $urls = app(RecordUrls::class);

        return array_filter(Soft::targets(), static fn (string $label, string $model): bool => $urls->hasPages($model), ARRAY_FILTER_USE_BOTH);
    }

    protected function getFieldProps(): array
    {
        $targets = [];

        foreach ($this->targets() as $model => $label) {
            $targets[] = ['value' => $model, 'label' => $label];
        }

        return [
            'targets' => $targets,
            'searchUrl' => $targets === [] ? null : route('admin.links.records', [], false),
        ];
    }

    public function getValidationRules(?FieldContext $context = null): array
    {
        $rules = $this->baseValidationRules($context);
        $rules[] = 'array';
        $rules[] = $this->shapeRule();

        return $rules;
    }

    /**
     * Records only of the modules on offer, addresses only of the kinds a
     * browser follows: no `javascript:` in a menu.
     */
    private function shapeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value) || in_array($value['type'] ?? null, [null, ''], true)) {
                return;
            }

            if ($value['type'] === self::URL) {
                $url = trim((string) (is_scalar($value['url'] ?? null) ? $value['url'] : ''));

                if ($url === '' || strlen($url) > 2048 || ! self::isSafeUrl($url)) {
                    $fail(__('atrium::fields.link.invalid_url'));
                }

                return;
            }

            $model = $value['model'] ?? null;
            $id = $value['id'] ?? null;

            if ($value['type'] !== self::RECORD || ! is_string($model) || ! array_key_exists($model, $this->targets()) || ! is_numeric($id)) {
                $fail(__('atrium::fields.link.invalid_record'));

                return;
            }

            if (! $model::query()->whereKey((int) $id)->exists()) {
                $fail(__('atrium::fields.link.invalid_record'));
            }
        };
    }

    /**
     * A path on the site, an anchor, or http(s), mailto and tel addresses.
     */
    public static function isSafeUrl(string $url): bool
    {
        if (preg_match('#^(/(?![/\\\\])|\#|\?)#', $url) === 1) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)
            && ($scheme !== 'http' && $scheme !== 'https' || filter_var($url, FILTER_VALIDATE_URL) !== false);
    }
}
