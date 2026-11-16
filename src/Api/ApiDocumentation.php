<?php

declare(strict_types=1);

namespace Atrium\Admin\Api;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Http\Controllers\AdminController;

/**
 * Документация API, собранная из описания полей админки.
 *
 * Руками её не пишут: поля, их типы, обязательность и варианты значений
 * берутся у тех же Field, что рисуют форму и проверяют сохранение. Добавили
 * поле или модуль — документация уже про него знает.
 *
 * Два вида одного и того же: Markdown для чтения (человеком или моделью)
 * и OpenAPI для агентов, которые строят вызовы по схеме.
 */
final class ApiDocumentation
{
    /**
     * Сколько вариантов перечислять прямо в документации. Длиннее — ссылка
     * на модуль, откуда их взять (у «Связанных туров» их сотни).
     */
    private const MAX_INLINE_OPTIONS = 80;

    public function __construct(private readonly ApiResources $resources) {}

    /**
     * @param  array<int,string>  $only  ключи модулей; пусто — все
     * @return array<string,array{label:string,fields:array<int,array<string,mixed>>}>
     */
    public function modules(array $only = []): array
    {
        $modules = [];

        foreach ($this->resources->all() as $key => $model) {
            if ($only !== [] && ! in_array($key, $only, true)) {
                continue;
            }

            $fields = array_filter(
                AdminController::fieldsOf($model),
                static fn (Field $field): bool => $field->shouldShowOnCreate() || $field->shouldShowOnEdit()
            );

            $modules[$key] = [
                'label' => $this->resources->label($key),
                'fields' => array_values(array_map(fn (Field $field): array => $this->describe($field), $fields)),
            ];
        }

        return $modules;
    }

    /**
     * Поле глазами клиента API: какой JSON слать и что он значит.
     *
     * Свойства поля берутся выборочно, а не всем toArray(): там, например,
     * ключ API карт, которому в открытой документации не место.
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
            $doc['note'] = 'Только при правке.';
        } elseif (! $field->shouldShowOnEdit()) {
            $doc['note'] = 'Только при создании.';
        }

        $doc = match ($type) {
            'number' => $this->set($doc, ['type' => 'number'], ''),
            'checkbox' => $this->checkboxSchema($doc, $props),
            'date' => $this->set($doc, ['type' => 'string', 'format' => 'date'], 'Дата: ГГГГ-ММ-ДД.'),
            'datetime' => $this->set($doc, ['type' => 'string', 'format' => 'date-time'], 'Дата и время: ГГГГ-ММ-ДД ЧЧ:ММ:СС.'),
            'slug' => $this->set($doc, ['type' => 'string', 'pattern' => '^[a-z0-9-]+$'], 'Для адреса страницы: латиница, цифры и дефис. Пусто — соберётся из названия.'),
            'content', 'wysiwyg' => $this->set($doc, ['type' => 'string'], 'HTML: p, h2, h3, ul, ol, li, a, b, i, img, table.'),
            'color' => $this->set($doc, ['type' => 'string'], 'Цвет, например #1e88e5.'),
            'map' => $this->set($doc, ['type' => 'string'], 'Координаты «широта,долгота», например 53.9045,27.5615.'),
            'image', 'file' => $this->set($doc, ['type' => 'string'], 'Адрес файла: загрузите его через POST /files и подставьте `url` из ответа.'),
            'gallery' => $this->set($doc, ['type' => 'array', 'items' => ['type' => 'string']], 'Список адресов файлов, каждый загружается через POST /files.'),
            'repeatable' => $this->set($doc, ['type' => 'array', 'items' => $this->repeatableItem($props['itemType'] ?? 'text')], $this->repeatableNote($props['itemType'] ?? 'text')),
            'select' => $this->optionsSchema($doc, $props['options'] ?? [], false),
            'multiSelect' => $this->optionsSchema($doc, $props['options'] ?? [], true),
            'treeSelect' => $this->relationSchema($doc, $this->flattenTree($props['options'] ?? []), (bool) ($props['multiple'] ?? false), $props['modelClass'] ?? null),
            'belongsTo' => $this->relationSchema($doc, $props['options'] ?? [], false, $props['modelClass'] ?? null),
            'belongsToMany' => $this->relationSchema($doc, $props['options'] ?? [], true, $props['model'] ?? null),
            default => $doc,
        };

        if ($type === 'text' && ($props['maxLength'] ?? null)) {
            $doc['schema']['maxLength'] = $props['maxLength'];
        }

        return $doc;
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
            $doc['note'] = trim(($doc['note'] ?? '')." Шлите true/false, в ответе придёт «{$props['checkedValue']}» или null.");
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
            'date' => 'Список дат ГГГГ-ММ-ДД.',
            'datetime' => 'Список дат со временем ГГГГ-ММ-ДД ЧЧ:ММ:СС.',
            'content' => 'Список HTML-фрагментов.',
            'number' => 'Список чисел.',
            default => 'Список строк.',
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
            $doc['note'] = trim(($doc['note'] ?? '').' Список значений.');
        }

        return $doc;
    }

    /**
     * Ссылка на другую запись — по id. Варианты либо прямо здесь, либо, когда
     * их слишком много, ссылка на модуль, где их взять.
     *
     * @param  array<int,array{value:mixed,label:mixed}>  $options
     */
    private function relationSchema(array $doc, array $options, bool $multiple, ?string $model): array
    {
        $doc['schema'] = $multiple ? ['type' => 'array', 'items' => ['type' => 'integer']] : ['type' => 'integer'];
        $doc['note'] = trim(($doc['note'] ?? '').($multiple ? ' Список id.' : ' id записи.'));

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
     * Дерево разделов — в плоский список с полным путём в подписи:
     * «Беларусь / Однодневные туры», иначе одноимённые разделы не различить.
     *
     * @return array<int,array{value:mixed,label:string}>
     */
    private function flattenTree(array $nodes, string $prefix = ''): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $label = $prefix === '' ? (string) $node['label'] : $prefix.' / '.$node['label'];
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
            "# API сайта {$site}",
            '',
            'Через API доступно то же, что в админке: списки, чтение, создание, правка и удаление записей любого модуля.',
            'Записи проверяются и сохраняются теми же правилами, что и форма в админке.',
            '',
            "Адрес: `{$base}`. Эта страница: `{$base}`, схема OpenAPI 3.1: `{$base}/openapi.json`.",
            "Описание только нужных модулей: `{$base}?only=ключ,ключ` (так же у `openapi.json`).",
            '',
            '## Доступ',
            '',
            'Токен выпускают в админке, раздел «API-токены». У токена права по модулям: `модуль:read` даёт читать, `модуль:write` создавать, менять и удалять.',
            'Каждый запрос — с заголовками `Authorization: Bearer <токен>` и `Accept: application/json`.',
            '',
            '## Запросы',
            '',
            '| Что | Запрос |',
            '|---|---|',
            '| Список | `GET /{модуль}?page=1&per_page=20&search=текст&filter[поле]=значение&sort=поле&direction=asc` |',
            '| Одна запись | `GET /{модуль}/{id}` |',
            '| Создать | `POST /{модуль}` с JSON полей |',
            '| Изменить | `PATCH /{модуль}/{id}`: только те поля, что меняются |',
            '| Удалить | `DELETE /{модуль}/{id}` |',
            '| Загрузить файл | `POST /files`, multipart/form-data, поле `file` (jpg, png, gif, webp, pdf, doc, docx, xls, xlsx) |',
            '',
            '`per_page` не больше 100. `filter` и `sort` принимают только поля модуля.',
            'Ответ на запись: `{"data": {...}}`, в нём ещё `admin_url` (правка в админке) и `public_url` (страница на сайте, если она есть).',
            'Ответ на список: `{"data": [...], "meta": {"current_page", "last_page", "per_page", "total"}}`.',
            '',
            '## Ошибки',
            '',
            '- 401: нет токена, он отозван, истёк или его владелец отключён.',
            '- 403: у токена нет права на этот модуль.',
            '- 404: нет такого модуля или записи.',
            '- 422: поля не прошли проверку, разбор по полям в `errors`.',
            '',
            '## Картинки',
            '',
            'Поле-картинка хранит адрес файла. Сначала `POST /files` с файлом, затем `url` из ответа кладётся в поле. В галерею — список таких адресов.',
            '',
            '## Как не навредить',
            '',
            '- Новую запись создавайте с `active: false` и включайте после проверки человеком, если у модуля есть поле `active`.',
            '- Перед созданием поищите запись по коду (`filter[code]=...`), чтобы не завести дубль.',
            '- Значения полей со списком вариантов берите только из этих вариантов.',
            '',
            '## Модули',
            '',
            '| Модуль | Ключ |',
            '|---|---|',
        ];

        foreach ($modules as $key => $module) {
            $lines[] = '| '.$this->cell($module['label'])." | `{$key}` |";
        }

        foreach ($modules as $key => $module) {
            array_push($lines, '', "## {$module['label']}", '', "`GET|POST /{$key}`, `GET|PATCH|DELETE /{$key}/{id}`", '');
            $lines[] = '| Поле | Тип | Обязательное | Что это |';
            $lines[] = '|---|---|---|---|';

            $options = [];

            foreach ($module['fields'] as $field) {
                $about = array_filter([$field['label'], $field['hint'], $field['note']]);

                if ($field['options_from'] !== null && $field['options'] === null) {
                    $about[] = "Варианты: GET /{$field['options_from']}.";
                }

                $lines[] = sprintf(
                    '| `%s` | %s | %s | %s |',
                    $field['name'],
                    $this->typeName($field['schema']),
                    $field['required'] ? 'да' : '',
                    $this->cell(implode('. ', array_map(static fn ($s) => rtrim((string) $s, '.'), $about)).'.')
                );

                if (! empty($field['options'])) {
                    $options[$field['name']] = $field['options'];
                }
            }

            foreach ($options as $name => $list) {
                array_push($lines, '', "Варианты `{$name}`:", '');

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
            '401' => ['description' => 'Нет действующего токена'],
            '403' => ['description' => 'У токена нет права на модуль'],
            '404' => ['description' => 'Нет модуля или записи'],
        ];

        foreach ($modules as $key => $module) {
            $name = str_replace('_', '', ucwords($key, '_'));
            $properties = [];
            $required = [];

            foreach ($module['fields'] as $field) {
                $schema = $field['schema'];
                $description = implode('. ', array_filter([$field['label'], $field['hint'], $field['note']]));

                if (! empty($field['options']) && ! isset($schema['enum']) && ! isset($schema['items']['enum'])) {
                    $description .= '. Варианты: '.implode('; ', array_map(
                        static fn (array $option): string => $option['value'].' = '.$option['label'],
                        $field['options']
                    ));
                } elseif ($field['options_from'] !== null && $field['options'] === null) {
                    $description .= ". Варианты: GET /{$field['options_from']}";
                }

                $properties[$field['name']] = $schema + ['description' => $description];

                if ($field['required']) {
                    $required[] = $field['name'];
                }
            }

            $schemas[$name] = array_filter(['type' => 'object', 'properties' => (object) $properties, 'required' => $required]);
            // Правка частичная: те же поля, но обязательных нет.
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
                    'summary' => "{$module['label']}: список",
                    'tags' => $tag,
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'filter', 'in' => 'query', 'style' => 'deepObject', 'explode' => true, 'schema' => ['type' => 'object']],
                        ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                    ],
                    'responses' => ['200' => ['description' => 'Страница списка'] + $json(['type' => 'object', 'properties' => [
                        'data' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$name}Record"]],
                        'meta' => ['type' => 'object'],
                    ]])] + $errors,
                ],
                'post' => [
                    'operationId' => "create_{$key}",
                    'summary' => "{$module['label']}: создать",
                    'tags' => $tag,
                    'requestBody' => ['required' => true] + $json($ref),
                    'responses' => ['201' => ['description' => 'Создано'] + $record, '422' => ['description' => 'Поля не прошли проверку']] + $errors,
                ],
            ];

            $paths["/{$key}/{id}"] = [
                'get' => [
                    'operationId' => "get_{$key}",
                    'summary' => "{$module['label']}: запись",
                    'tags' => $tag,
                    'parameters' => [$idParam],
                    'responses' => ['200' => ['description' => 'Запись'] + $record] + $errors,
                ],
                'patch' => [
                    'operationId' => "update_{$key}",
                    'summary' => "{$module['label']}: изменить присланные поля",
                    'tags' => $tag,
                    'parameters' => [$idParam],
                    'requestBody' => ['required' => true] + $json(['$ref' => "#/components/schemas/{$name}Changes"]),
                    'responses' => ['200' => ['description' => 'Изменено'] + $record, '422' => ['description' => 'Поля не прошли проверку']] + $errors,
                ],
                'delete' => [
                    'operationId' => "delete_{$key}",
                    'summary' => "{$module['label']}: удалить",
                    'tags' => $tag,
                    'parameters' => [$idParam],
                    'responses' => ['204' => ['description' => 'Удалено'], '409' => ['description' => 'Запись нельзя удалить']] + $errors,
                ],
            ];
        }

        $paths['/files'] = ['post' => [
            'operationId' => 'upload_file',
            'summary' => 'Загрузить файл, вернёт url для поля-картинки или галереи',
            'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => [
                'type' => 'object',
                'properties' => ['file' => ['type' => 'string', 'format' => 'binary']],
                'required' => ['file'],
            ]]]],
            'responses' => ['200' => ['description' => 'Файл загружен'] + $json(['type' => 'object', 'properties' => [
                'url' => ['type' => 'string'],
                'path' => ['type' => 'string'],
            ]])] + $errors,
        ]];

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'API сайта '.$this->siteName(),
                'version' => '1',
                'description' => 'То же, что в админке. Подробно, с вариантами значений: '.url(config('atrium.api.prefix', 'api/v1')),
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
     * Сайт по домену: APP_NAME у проектов часто так и остаётся «Laravel».
     */
    private function siteName(): string
    {
        return (string) parse_url(url('/'), PHP_URL_HOST);
    }

    private function typeName(array $schema): string
    {
        $type = $schema['type'] ?? 'string';

        if ($type === 'array') {
            return match ($this->typeName($schema['items'] ?? [])) {
                'id' => 'список id',
                'число' => 'список чисел',
                'дата' => 'список дат',
                'дата и время' => 'список дат со временем',
                default => 'список строк',
            };
        }

        return match ($schema['format'] ?? $type) {
            'integer' => 'id',
            'number' => 'число',
            'boolean' => 'да/нет',
            'date' => 'дата',
            'date-time' => 'дата и время',
            default => 'строка',
        };
    }

    private function cell(string $text): string
    {
        return trim(str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], strip_tags($text)));
    }
}
