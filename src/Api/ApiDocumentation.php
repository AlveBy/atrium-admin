<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Api;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Support\ContentLanguages;

/**
 * API documentation built from the admin field definitions.
 *
 * Nobody writes it by hand: fields, their types, whether they are required and
 * their allowed values come from the same Field objects that render the form
 * and validate saving. Add a field or a module and the documentation knows it.
 *
 * Two views of the same thing: Markdown for reading (by a human or a model)
 * and OpenAPI for agents that build calls from a schema.
 */
final class ApiDocumentation
{
    /**
     * How many options to list right in the documentation. Longer lists become
     * a reference to the module to fetch them from (relations can have hundreds).
     */
    private const MAX_INLINE_OPTIONS = 80;

    public function __construct(private readonly ApiResources $resources) {}

    /**
     * @param  array<int,string>  $only  module keys; empty — all
     * @return array<string,array{label:string,fields:array<int,array<string,mixed>>}>
     */
    public function modules(array $only = []): array
    {
        $modules = [];

        foreach ($this->resources->all() as $key => $model) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $modules[$key] = $this->describeModule($key, $model);
        }

        return $modules;
    }

    /**
     * One module of the API by its key; the key must be one of the API's.
     *
     * @return array{label:string,fields:array<int,array<string,mixed>>}
     */
    public function module(string $key): array
    {
        return $this->describeModule($key, $this->resources->all()[$key]);
    }

    /**
     * @param  class-string  $model
     * @return array{label:string,fields:array<int,array<string,mixed>>}
     */
    private function describeModule(string $key, string $model): array
    {
        $instance = new $model;
        $translatable = Hook::apply(
            'atrium.module.translatable',
            method_exists($instance, 'getTranslatableAttributes') ? $instance->getTranslatableAttributes() : [],
            $model,
        );

        $fields = [];

        foreach (AdminController::fieldsOf($model) as $field) {
            if (! $field->shouldShowOnCreate() && ! $field->shouldShowOnEdit()) {
                continue;
            }

            $doc = $this->describe($field);
            $fields[] = in_array($field->getName(), $translatable, true) ? $this->translatable($doc) : $doc;
        }

        return [
            'label' => $this->resources->label($key),
            'fields' => $fields,
        ];
    }

    /**
     * A field as an API client sees it: what JSON to send and what it means.
     *
     * Field properties are picked selectively rather than the whole toArray():
     * it contains, for example, the maps API key, which has no place in public
     * documentation.
     *
     * @return array<string,mixed>
     */
    private function describe(Field $field): array
    {
        $props = $field->toArray();
        $type = $props['type'];

        $doc = [
            'name' => $field->getName(),
            'label' => $field->getLabel(),
            'required' => $field->isRequired(),
            'hint' => $props['hint'] ?? null,
            'schema' => ['type' => 'string'],
            'note' => null,
            'options' => null,
            'options_from' => null,
        ];

        if (! $field->shouldShowOnCreate()) {
            $doc['note'] = 'Edit only.';
        } elseif (! $field->shouldShowOnEdit()) {
            $doc['note'] = 'Create only.';
        }

        $doc = match ($type) {
            'number' => $this->set($doc, ['type' => 'number'], ''),
            'checkbox' => $this->checkboxSchema($doc, $props),
            'date' => $this->set($doc, ['type' => 'string', 'format' => 'date'], 'Date: YYYY-MM-DD.'),
            'datetime' => $this->set($doc, ['type' => 'string', 'format' => 'date-time'], 'Date and time: YYYY-MM-DD HH:MM:SS.'),
            'slug' => $this->set($doc, ['type' => 'string', 'pattern' => '^[a-z0-9-]+$'], 'For the page URL: Latin letters, digits and hyphens. Empty — built from the name.'),
            'content', 'wysiwyg' => $this->set($doc, ['type' => 'string'], 'HTML: p, h2, h3, ul, ol, li, a, b, i, img, table.'),
            'color' => $this->set($doc, ['type' => 'string'], 'Colour, e.g. #1e88e5.'),
            'map' => $this->set($doc, ['type' => 'string'], 'Coordinates "latitude,longitude", e.g. 51.5074,-0.1278.'),
            'image', 'file' => $this->set($doc, ['type' => 'string'], 'File URL: upload the file via POST /files and use `url` from the response.'),
            'gallery' => $this->set($doc, ['type' => 'array', 'items' => ['type' => 'string']], 'List of file URLs, each uploaded via POST /files.'),
            'repeatable' => $this->set($doc, ['type' => 'array', 'items' => $this->repeatableItem($props['itemType'] ?? 'text')], $this->repeatableNote($props['itemType'] ?? 'text')),
            'select' => $this->optionsSchema($doc, $props['options'] ?? [], false),
            'multiSelect' => $this->optionsSchema($doc, $props['options'] ?? [], true),
            'treeSelect' => $this->relationSchema($doc, $this->flattenTree($props['options'] ?? []), $props['multiple'], $props['modelClass'] ?? null),
            'belongsTo' => $this->relationSchema($doc, $props['options'] ?? [], false, $props['modelClass'] ?? null),
            'belongsToMany' => $this->relationSchema($doc, $props['options'] ?? [], true, $props['model'] ?? null),
            default => $doc,
        };

        // What validation enforces, not the input's maxlength hint (255).
        if ($field instanceof TextField) {
            $doc['schema']['maxLength'] = $field->getMaxLength();
        }

        return $doc;
    }

    /**
     * A field written in every content language: one value (the default
     * language) or an object keyed by language code.
     *
     * @param  array<string,mixed>  $doc
     * @return array<string,mixed>
     */
    private function translatable(array $doc): array
    {
        $languages = app(ContentLanguages::class);
        $properties = [];

        foreach ($languages->codes() as $code) {
            $properties[$code] = $doc['schema'];
        }

        $schema = ['oneOf' => [$doc['schema'], ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false]]];

        return $this->set($doc, $schema, 'Translatable: a plain value sets the default language ('.$languages->default().'), an object {"'.implode('": …, "', $languages->codes()).'": …} sets several. Read back as that object.');
    }

    /**
     * @param  array<string,mixed>  $doc
     * @param  array<string,mixed>  $schema
     * @return array<string,mixed>
     */
    private function set(array $doc, array $schema, string $note): array
    {
        $doc['schema'] = $schema;
        $doc['note'] = trim(($doc['note'] ?? '').' '.$note) ?: null;

        return $doc;
    }

    private function checkboxSchema(array $doc, array $props): array
    {
        $doc['schema'] = ['type' => 'boolean'];

        if (($props['checkedValue'] ?? null) !== null) {
            $doc['note'] = trim(($doc['note'] ?? '')." Send true/false; the response contains \"{$props['checkedValue']}\" or null.");
        }

        return $doc;
    }

    private function repeatableItem(string $itemType): array
    {
        return match ($itemType) {
            'number' => ['type' => 'number'],
            'date' => ['type' => 'string', 'format' => 'date'],
            'datetime' => ['type' => 'string', 'format' => 'date-time'],
            default => ['type' => 'string'],
        };
    }

    private function repeatableNote(string $itemType): string
    {
        return match ($itemType) {
            'date' => 'List of dates YYYY-MM-DD.',
            'datetime' => 'List of dates with time YYYY-MM-DD HH:MM:SS.',
            'content' => 'List of HTML fragments.',
            'number' => 'List of numbers.',
            default => 'List of strings.',
        };
    }

    /**
     * @param  array<int,array{value:mixed,label:mixed}>  $options
     */
    private function optionsSchema(array $doc, array $options, bool $multiple): array
    {
        $values = array_column($options, 'value');
        $item = $values === [] ? ['type' => 'string'] : ['type' => 'string', 'enum' => $values];

        $doc['schema'] = $multiple ? ['type' => 'array', 'items' => $item] : $item;
        $doc['options'] = $options;

        if ($multiple) {
            $doc['note'] = trim(($doc['note'] ?? '').' List of values.');
        }

        return $doc;
    }

    /**
     * A reference to another record — by id. Options are either listed right
     * here or, when there are too many, a reference to the module to get them from.
     *
     * @param  array<int,array{value:mixed,label:mixed}>  $options
     */
    private function relationSchema(array $doc, array $options, bool $multiple, ?string $model): array
    {
        $doc['schema'] = $multiple ? ['type' => 'array', 'items' => ['type' => 'integer']] : ['type' => 'integer'];
        $doc['note'] = trim(($doc['note'] ?? '').($multiple ? ' List of ids.' : ' Record id.'));

        $source = $model !== null ? $this->resources->keyOf($model) : null;

        if (count($options) > self::MAX_INLINE_OPTIONS && $source !== null) {
            $doc['options_from'] = $source;
        } else {
            $doc['options'] = $options;
            $doc['options_from'] = $source;
        }

        return $doc;
    }

    /**
     * A section tree as a flat list with the full path in the label:
     * "Europe / Day trips", otherwise sections with the same name are indistinguishable.
     *
     * @return array<int,array{value:mixed,label:mixed}>
     */
    private function flattenTree(array $nodes, string $prefix = ''): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $label = $prefix === '' ? $node['label'] : $prefix.' / '.$node['label'];
            $flat[] = ['value' => $node['value'], 'label' => $label];

            if (! empty($node['children'])) {
                array_push($flat, ...$this->flattenTree($node['children'], $label));
            }
        }

        return $flat;
    }

    /**
     * @param  array<int,string>  $only
     */
    public function markdown(array $only = []): string
    {
        $base = url(config('atrium.api.prefix', 'api/v1'));
        $modules = $this->modules($only);
        $site = $this->siteName();

        $lines = [
            "# {$site} API",
            '',
            'The API offers the same as the admin panel: listing, reading, creating, editing and deleting records of any module.',
            'Records are validated and saved by the same rules as the admin form.',
            '',
            "Base URL: `{$base}`. This page: `{$base}`, OpenAPI 3.1 schema: `{$base}/openapi.json`.",
            "Only the modules you need: `{$base}?only=key,key` (same for `openapi.json`).",
            '',
            '## Access',
            '',
            'Tokens are issued in the admin panel, "API tokens" section. A token has per-module abilities: `module:read` allows reading, `module:write` creating, editing and deleting.',
            'Send every request with the headers `Authorization: Bearer <token>` and `Accept: application/json`.',
            '',
            '## Requests',
            '',
            '| What | Request |',
            '|---|---|',
            '| List | `GET /{module}?page=1&per_page=20&search=text&filter[field]=value&sort=field&direction=asc` |',
            '| One record | `GET /{module}/{id}` |',
            '| Create | `POST /{module}` with a JSON of fields |',
            '| Update | `PATCH /{module}/{id}`: only the fields that change |',
            '| Delete | `DELETE /{module}/{id}` |',
            '| Upload a file | `POST /files`, multipart/form-data, field `file` (jpg, png, gif, webp, pdf, doc, docx, xls, xlsx) |',
            '| Actions the token may run | `GET /{module}/actions` |',
            '| Run an action on a record | `POST /{module}/{id}/actions/{action}` |',
            '| Run an action on several records | `POST /{module}/bulk/{action}` with `{"ids": [1, 2]}`, at most 1000 |',
            '',
            'An action answers `{"message", "redirect"}`: 200 when it worked, 422 when it did not. `bulk/delete` deletes the records that may be deleted. An action with `confirm` set asks first in the admin panel: ask a human too.',
            '',
            '`per_page` is at most 100. `filter` and `sort` accept only fields of the module.',
            'Record response: `{"data": {...}}`, which also has `admin_url` (edit in the admin panel) and `public_url` (the page on the site, if any).',
            'List response: `{"data": [...], "meta": {"current_page", "last_page", "per_page", "total"}}`.',
            '',
            '## Errors',
            '',
            '- 401: no token, or it is revoked, expired, or its owner is disabled.',
            '- 403: the token has no ability for this module.',
            '- 404: no such module or record.',
            '- 422: fields failed validation, per-field details in `errors`.',
            '',
            '## Images',
            '',
            'An image field stores a file URL. First `POST /files` with the file, then put `url` from the response into the field. A gallery takes a list of such URLs.',
            '',
            '## How not to do harm',
            '',
            '- Create new records with `active: false` and let a human enable them after review, if the module has an `active` field.',
            '- Before creating, look the record up by code (`filter[code]=...`) to avoid duplicates.',
            '- For fields with a list of options, use only those options.',
            '',
            '## Modules',
            '',
            '| Module | Key |',
            '|---|---|',
        ];

        foreach ($modules as $key => $module) {
            $lines[] = '| '.$this->cell($module['label'])." | `{$key}` |";
        }

        foreach ($modules as $key => $module) {
            array_push($lines, '', '## '.$this->text($module['label']), '', "`GET|POST /{$key}`, `GET|PATCH|DELETE /{$key}/{id}`", '');
            $lines[] = '| Field | Type | Required | Description |';
            $lines[] = '|---|---|---|---|';

            $options = [];

            foreach ($module['fields'] as $field) {
                $about = array_filter([$field['label'], $field['hint'], $field['note']]);

                if ($field['options_from'] !== null && $field['options'] === null) {
                    $about[] = "Options: GET /{$field['options_from']}.";
                }

                $lines[] = sprintf(
                    '| `%s` | %s | %s | %s |',
                    $field['name'],
                    $this->typeName($field['schema']),
                    $field['required'] ? 'yes' : '',
                    $this->cell(implode('. ', array_map(static fn ($s) => rtrim((string) $s, '.'), $about)).'.')
                );

                if (! empty($field['options'])) {
                    $options[$field['name']] = $field['options'];
                }
            }

            foreach ($options as $name => $list) {
                array_push($lines, '', "Options for `{$name}`:", '');

                foreach ($list as $option) {
                    $lines[] = '- `'.$option['value'].'`: '.$this->cell((string) $option['label']);
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int,string>  $only
     * @return array<string,mixed>
     */
    public function openApi(array $only = []): array
    {
        $modules = $this->modules($only);
        $paths = [];
        $schemas = [];

        $json = static fn (array $schema): array => ['content' => ['application/json' => ['schema' => $schema]]];
        $idParam = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $errors = [
            '401' => ['description' => 'No valid token'],
            '403' => ['description' => 'The token has no ability for the module'],
            '404' => ['description' => 'No such module or record'],
        ];

        foreach ($modules as $key => $module) {
            $name = str_replace('_', '', ucwords($key, '_'));
            $properties = [];
            $required = [];

            foreach ($module['fields'] as $field) {
                $schema = $field['schema'];
                $description = implode('. ', array_filter([$field['label'], $field['hint'], $field['note']]));

                if (! empty($field['options']) && ! isset($schema['enum']) && ! isset($schema['items']['enum'])) {
                    $description .= '. Options: '.implode('; ', array_map(
                        static fn (array $option): string => $option['value'].' = '.$option['label'],
                        $field['options']
                    ));
                } elseif ($field['options_from'] !== null && $field['options'] === null) {
                    $description .= ". Options: GET /{$field['options_from']}";
                }

                $properties[$field['name']] = $schema + ['description' => $description];

                if ($field['required']) {
                    $required[] = $field['name'];
                }
            }

            $schemas[$name] = array_filter(['type' => 'object', 'properties' => (object) $properties, 'required' => $required]);
            // Updates are partial: the same fields, none required.
            $schemas[$name.'Changes'] = ['type' => 'object', 'properties' => (object) $properties];
            $schemas[$name.'Record'] = ['allOf' => [
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'admin_url' => ['type' => 'string'],
                    'public_url' => ['type' => ['string', 'null']],
                ]],
                ['$ref' => "#/components/schemas/{$name}"],
            ]];

            $ref = ['$ref' => "#/components/schemas/{$name}"];
            $record = $json(['type' => 'object', 'properties' => ['data' => ['$ref' => "#/components/schemas/{$name}Record"]]]);
            $tag = [$module['label']];

            $paths["/{$key}"] = [
                'get' => [
                    'operationId' => "list_{$key}",
                    'summary' => "{$module['label']}: list",
                    'tags' => $tag,
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'filter', 'in' => 'query', 'style' => 'deepObject', 'explode' => true, 'schema' => ['type' => 'object']],
                        ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                    ],
                    'responses' => ['200' => ['description' => 'List page'] + $json(['type' => 'object', 'properties' => [
                        'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$name}Record"]],
                        'meta' => ['type' => 'object'],
                    ]])] + $errors,
                ],
                'post' => [
                    'operationId' => "create_{$key}",
                    'summary' => "{$module['label']}: create",
                    'tags' => $tag,
                    'requestBody' => ['required' => true] + $json($ref),
                    'responses' => ['201' => ['description' => 'Created'] + $record, '422' => ['description' => 'Fields failed validation']] + $errors,
                ],
            ];

            $paths["/{$key}/{id}"] = [
                'get' => [
                    'operationId' => "get_{$key}",
                    'summary' => "{$module['label']}: record",
                    'tags' => $tag,
                    'parameters' => [$idParam],
                    'responses' => ['200' => ['description' => 'Record'] + $record] + $errors,
                ],
                'patch' => [
                    'operationId' => "update_{$key}",
                    'summary' => "{$module['label']}: update the fields sent",
                    'tags' => $tag,
                    'parameters' => [$idParam],
                    'requestBody' => ['required' => true] + $json(['$ref' => "#/components/schemas/{$name}Changes"]),
                    'responses' => ['200' => ['description' => 'Updated'] + $record, '422' => ['description' => 'Fields failed validation']] + $errors,
                ],
                'delete' => [
                    'operationId' => "delete_{$key}",
                    'summary' => "{$module['label']}: delete",
                    'tags' => $tag,
                    'parameters' => [$idParam],
                    'responses' => ['204' => ['description' => 'Deleted'], '409' => ['description' => 'The record cannot be deleted']] + $errors,
                ],
            ];
        }

        $paths['/files'] = ['post' => [
            'operationId' => 'upload_file',
            'summary' => 'Upload a file; returns a url for an image field or a gallery',
            'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => [
                'type' => 'object',
                'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                'required' => ['file'],
            ]]]],
            'responses' => ['200' => ['description' => 'File uploaded'] + $json(['type' => 'object', 'properties' => [
                'url' => ['type' => 'string'],
                'path' => ['type' => 'string'],
            ]])] + $errors,
        ]];

        $module = ['name' => 'module', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        $action = ['name' => 'action', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        $result = $json(['type' => 'object', 'properties' => [
            'message' => ['type' => 'string'],
            'redirect' => ['type' => ['string', 'null']],
        ]]);
        $paths['/{module}/actions'] = ['get' => [
            'operationId' => 'list_actions',
            'summary' => 'Actions of a module the token may run',
            'parameters' => [$module],
            'responses' => ['200' => ['description' => 'Actions on one record and on several'] + $json(['type' => 'object', 'properties' => [
                'data' => ['type' => 'object', 'properties' => ['record' => ['type' => 'array'], 'bulk' => ['type' => 'array']]],
            ]])] + $errors,
        ]];
        $paths['/{module}/{id}/actions/{action}'] = ['post' => [
            'operationId' => 'run_record_action',
            'summary' => 'Run an action on a record',
            'parameters' => [$module, $idParam, $action],
            'responses' => ['200' => ['description' => 'Done'] + $result, '422' => ['description' => 'The action failed'] + $result] + $errors,
        ]];
        $paths['/{module}/bulk/{action}'] = ['post' => [
            'operationId' => 'run_bulk_action',
            'summary' => 'Run an action on several records',
            'parameters' => [$module, $action],
            'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => [
                'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 1000],
            ], 'required' => ['ids']]),
            'responses' => ['200' => ['description' => 'Done'] + $result, '422' => ['description' => 'The action failed or no record was found'] + $result] + $errors,
        ]];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->siteName().' API',
                'version' => '1',
                'description' => 'The same as the admin panel. Full details with allowed values: '.url(config('atrium.api.prefix', 'api/v1')),
            ],
            'servers' => [['url' => url(config('atrium.api.prefix', 'api/v1'))]],
            'security' => [['bearer' => []]],
            'components' => [
                'securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer']],
                'schemas' => $schemas,
            ],
            'paths' => $paths,
        ];
    }

    /**
     * The site by its domain: projects often leave APP_NAME as "Laravel".
     */
    private function siteName(): string
    {
        return (string) parse_url(url('/'), PHP_URL_HOST);
    }

    private function typeName(array $schema): string
    {
        if (isset($schema['oneOf'])) {
            return $this->typeName($schema['oneOf'][0]).' per language';
        }

        $type = $schema['type'] ?? 'string';

        if ($type === 'array') {
            return match ($this->typeName($schema['items'] ?? [])) {
                'id' => 'list of ids',
                'number' => 'list of numbers',
                'date' => 'list of dates',
                'date and time' => 'list of dates with time',
                default => 'list of strings',
            };
        }

        return match ($schema['format'] ?? $type) {
            'integer' => 'id',
            'number' => 'number',
            'boolean' => 'yes/no',
            'date' => 'date',
            'date-time' => 'date and time',
            default => 'string',
        };
    }

    /**
     * A label or hint as the panel shows it, as text: on one line, and with
     * < and > escaped so that no Markdown viewer takes it for HTML.
     */
    private function text(string $text): string
    {
        return trim(str_replace(['<', '>', "\n", "\r"], ['\\<', '\\>', ' ', ''], $text));
    }

    private function cell(string $text): string
    {
        return str_replace('|', '\\|', $this->text($text));
    }
}
