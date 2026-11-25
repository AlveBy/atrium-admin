<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\ApiShowcase;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The public API documentation: what an outside program is told about every
 * module and every kind of field, in Markdown and as OpenAPI.
 */
final class ApiDocumentationTest extends TestCase
{
    use PinsExtensionSurface;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://shop.test');
        $app['config']->set('atrium.resources', [Post::class, ApiShowcase::class]);
        $app['config']->set('atrium.maps.google_api_key', 'AIza-very-secret-maps-key');
        $app['config']->set('atrium.menu', [
            ['label' => 'Blog', 'route' => 'admin.posts.index'],
            ['label' => 'Каталог', 'children' => [
                ['label' => 'Витрина', 'route' => 'admin.api_showcases.index'],
            ]],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);

        $europe = ApiAisle::query()->create(['name' => 'Europe', 'sort' => 1]);
        ApiAisle::query()->create(['name' => 'Day trips', 'parent_id' => $europe->id, 'sort' => 1]);
        ApiAisle::query()->create(['name' => 'Asia', 'sort' => 2]);
    }

    public function test_the_documentation_is_public_markdown(): void
    {
        $response = $this->get('http://shop.test/api/v1')->assertOk();

        $this->assertSame('text/markdown; charset=UTF-8', $response->headers->get('Content-Type'));
        $response->assertSee('# shop.test API', false);
        $response->assertSee('Base URL: `http://shop.test/api/v1`', false);
        $response->assertSee('OpenAPI 3.1 schema: `http://shop.test/api/v1/openapi.json`', false);
        $response->assertSeeInOrder([
            "| Upload a file | `POST /files`, multipart/form-data, field `file` (jpg, png, gif, webp, pdf, doc, docx, xls, xlsx) |\n",
            "| Actions the token may run | `GET /{module}/actions` |\n",
            "| Run an action on a record | `POST /{module}/{id}/actions/{action}` |\n",
            "| Run an action on several records | `POST /{module}/bulk/{action}` with `{\"ids\": [1, 2]}`, at most 1000 |\n",
            "| Versions of a record | `GET /{module}/{id}/versions` |\n",
            "| Put a version back | `POST /{module}/{id}/versions/{version}/restore` |\n",
            "| What is in the trash | `GET /{module}/trash?page=1&per_page=20` |\n",
            "| Bring a record back from the trash | `POST /{module}/trash/{id}/restore` |\n",
            "| Delete a record in the trash for good | `DELETE /{module}/trash/{id}` |\n\n",
            'An action answers `{"message", "redirect"}`: 200 when it worked, 422 when it did not. `bulk/delete` deletes the records that may be deleted. An action with `confirm` set asks first in the admin panel: ask a human too.'."\n\n",
            'Every save keeps a version of the record: `versions` lists them newest first (the first is the record as it is), each with `id`, `at`, `admin`, `source` and the `values` of its fields. `restore` saves those values as `PATCH` would and answers with the record; 404 for a module that keeps no versions. Restoring takes a `write` token, listing a `read` one.'."\n\n",
            '`DELETE` puts a record of a content module in the trash, where it is hidden everywhere. `trash` lists those records newest first, each with `id`, `title`, `deleted_at` and `deleted_by`. `restore` brings one back and answers with the record; `DELETE /{module}/trash/{id}` deletes it for good (409 when the module refuses). Both take a `write` token and the owner\'s permission `{module}.restore` or `{module}.force_delete`; 404 for a module whose records are deleted at once.'."\n\n".
            '`publish_at` and `unpublish_at` of a content module plan when the record switches itself on and off: send ISO 8601 times, or null to cancel. The record shows them until they come. `unpublish_at` must come after `publish_at`.'."\n\n`per_page`",
        ], false);
    }

    public function test_the_openapi_schema_is_public_json_with_unescaped_text(): void
    {
        $response = $this->get('http://shop.test/api/v1/openapi.json')->assertOk();

        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'shop.test API')
            ->assertJsonPath('info.version', '1')
            ->assertJsonPath('info.description', 'The same as the admin panel. Full details with allowed values: http://shop.test/api/v1')
            ->assertJsonPath('servers', [['url' => 'http://shop.test/api/v1']])
            ->assertJsonPath('security', [['bearer' => []]])
            ->assertJsonPath('components.securitySchemes.bearer', ['type' => 'http', 'scheme' => 'bearer']);

        $content = (string) $response->getContent();
        $this->assertStringContainsString('"url":"http://shop.test/api/v1"', $content);
        $this->assertStringContainsString('Каталог / Витрина', $content);
    }

    /**
     * @return array<string,array{0:string,1:array<int,string>}>
     */
    public static function onlyLists(): array
    {
        return [
            'nothing asked: every module' => ['', ['api_showcases', 'languages', 'posts', 'sites']],
            'one module' => ['posts', ['posts']],
            'two, with spaces' => [' posts , api_showcases ', ['api_showcases', 'posts']],
            'empty items are skipped' => ['posts,,', ['posts']],
            'an unknown module' => ['nope', []],
            'administrators are never in the API' => ['users', []],
            'Unicode' => ['посты', []],
        ];
    }

    /**
     * @param  array<int,string>  $modules
     */
    #[DataProvider('onlyLists')]
    public function test_only_the_modules_asked_for_are_described(string $only, array $modules): void
    {
        $paths = array_keys($this->getJson('/api/v1/openapi.json?only='.urlencode($only))->json('paths'));
        $expected = array_merge(...array_map(static fn (string $key): array => ["/{$key}", "/{$key}/{id}"], $modules), ...[['/files', '/{module}/actions', '/{module}/bulk/{action}', '/{module}/{id}/actions/{action}', '/{module}/{id}/versions', '/{module}/{id}/versions/{version}/restore', '/{module}/trash', '/{module}/trash/{id}/restore', '/{module}/trash/{id}']]);
        sort($paths);
        sort($expected);

        $this->assertSame($expected, $paths);

        $markdown = (string) $this->get('/api/v1?only='.urlencode($only))->getContent();

        foreach (['api_showcases', 'posts', 'languages', 'sites'] as $key) {
            $this->assertSame(in_array($key, $modules, true), str_contains($markdown, "`GET|POST /{$key}`"), $key);
        }
    }

    public function test_the_administrators_stay_out_of_the_api_unless_the_app_says_otherwise(): void
    {
        $make = fn (): ApiResources => new ApiResources($this->app['router'], $this->app->make(ResourceRegistry::class));

        $this->assertArrayHasKey('users', $make()->modules());
        $this->assertArrayNotHasKey('users', $make()->all());

        // An app that sets its own api block without "except" keeps the default.
        config(['atrium.api' => ['prefix' => 'v1']]);
        $this->assertArrayNotHasKey('users', $make()->all());

        config(['atrium.api.except' => []]);
        $this->assertArrayHasKey('users', $make()->all());

        $resources = $make();
        $before = $resources->all();
        config(['atrium.api.except' => ['posts']]);
        $this->assertSame($before, $resources->all(), 'The list is worked out once.');
    }

    public function test_modules_are_named_as_in_the_menu(): void
    {
        $resources = app(ApiResources::class);

        $this->assertSame('Blog', $resources->label('posts'));
        $this->assertSame('Каталог / Витрина', $resources->label('api_showcases'));
        $this->assertSame('Sites', $resources->label('sites'), 'Not in the menu: the model label.');
        $this->assertSame('api_showcases', $resources->keyOf(ApiShowcase::class));
        $this->assertNull($resources->keyOf(ApiAisle::class));
        $this->assertSame(['api_showcases:read', 'api_showcases:write', 'languages:read', 'languages:write', 'posts:read', 'posts:write', 'sites:read', 'sites:write'], $resources->abilities());
    }

    public function test_every_kind_of_field_is_described(): void
    {
        $fields = collect(app(ApiDocumentation::class)->modules(['api_showcases'])['api_showcases']['fields'])->keyBy('name');

        $this->assertSame([
            'name', 'code', 'notes', 'price', 'active', 'booking', 'starts_on', 'starts_at', 'slug', 'body', 'intro', 'colour',
            'location', 'picture', 'leaflet', 'photos', 'dates', 'times', 'amounts', 'fragments', 'words', 'kind', 'mood',
            'extras', 'aisle_id', 'aisles', 'post_id', 'owner_id', 'posts',
        ], $fields->keys()->all(), 'A field hidden on both forms is not in the API.');

        $translatable = ' Translatable: a plain value sets the default language (en), an object {"en": …, "de": …} sets several. Read back as that object.';
        $text = ['type' => 'string', 'maxLength' => 120];
        $this->assertSame([
            'name' => 'name', 'label' => 'Name', 'required' => true, 'hint' => null,
            'schema' => ['oneOf' => [$text, ['type' => 'object', 'properties' => ['en' => $text, 'de' => $text], 'additionalProperties' => false]]],
            'note' => trim($translatable), 'options' => null, 'options_from' => null,
        ], $fields['name']);

        $this->assertSame([
            'name' => 'code', 'label' => 'Code', 'required' => false, 'hint' => 'Unique | per site',
            'schema' => ['type' => 'string', 'maxLength' => 65535], 'note' => 'Create only.', 'options' => null, 'options_from' => null,
        ], $fields['code']);

        $expected = [
            'notes' => [['type' => 'string'], 'Edit only.'],
            'price' => [['type' => 'number'], null],
            'active' => [['type' => 'boolean'], null],
            'booking' => [['type' => 'boolean'], 'Send true/false; the response contains "YES" or null.'],
            'starts_on' => [['type' => 'string', 'format' => 'date'], 'Date: YYYY-MM-DD.'],
            'starts_at' => [['type' => 'string', 'format' => 'date-time'], 'Date and time: YYYY-MM-DD HH:MM:SS.'],
            'slug' => [['type' => 'string', 'pattern' => '^[a-z0-9-]+$'], 'For the page URL: Latin letters, digits and hyphens. Empty — built from the name.'],
            'body' => [['type' => 'string'], 'HTML: p, h2, h3, ul, ol, li, a, b, i, img, table.'],
            'intro' => [['type' => 'string'], 'HTML: p, h2, h3, ul, ol, li, a, b, i, img, table.'],
            'colour' => [['type' => 'string'], 'Colour, e.g. #1e88e5.'],
            'location' => [['type' => 'string'], 'Coordinates "latitude,longitude", e.g. 51.5074,-0.1278.'],
            'picture' => [['type' => 'string'], 'File URL: upload the file via POST /files and use `url` from the response.'],
            'leaflet' => [['type' => 'string'], 'File URL: upload the file via POST /files and use `url` from the response.'],
            'photos' => [['type' => 'array', 'items' => ['type' => 'string']], 'List of file URLs, each uploaded via POST /files.'],
            'dates' => [['type' => 'array', 'items' => ['type' => 'string', 'format' => 'date']], 'List of dates YYYY-MM-DD.'],
            'times' => [['type' => 'array', 'items' => ['type' => 'string', 'format' => 'date-time']], 'List of dates with time YYYY-MM-DD HH:MM:SS.'],
            'amounts' => [['type' => 'array', 'items' => ['type' => 'number']], 'List of numbers.'],
            'fragments' => [['type' => 'array', 'items' => ['type' => 'string']], 'List of HTML fragments.'],
            'words' => [['type' => 'array', 'items' => ['type' => 'string']], 'List of strings.'],
            'kind' => [['type' => 'string', 'enum' => ['tour', 'cruise']], null],
            'mood' => [['type' => 'string'], null],
            'extras' => [['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['wifi', 'pool']]], 'List of values.'],
            'aisle_id' => [['type' => 'integer'], 'Record id.'],
            'aisles' => [['type' => 'array', 'items' => ['type' => 'integer']], 'List of ids.'],
            'owner_id' => [['type' => 'integer'], 'Record id.'],
        ];

        foreach ($expected as $name => [$schema, $note]) {
            $this->assertSame($schema, $fields[$name]['schema'], $name);
            $this->assertSame($note, $fields[$name]['note'], $name);
            $this->assertFalse($fields[$name]['required'], $name);
        }

        $this->assertSame([['value' => 'tour', 'label' => 'Tour'], ['value' => 'cruise', 'label' => 'Cruise | sea']], $fields['kind']['options']);
        $this->assertSame([], $fields['mood']['options']);
        $this->assertNull($fields['notes']['options']);

        // A section tree comes flat, each with its whole path.
        $tree = ApiAisle::query()->orderBy('id')->pluck('id')->all();
        $flat = [['value' => $tree[0], 'label' => 'Europe'], ['value' => $tree[1], 'label' => 'Europe / Day trips'], ['value' => $tree[2], 'label' => 'Asia']];
        $this->assertSame($flat, $fields['aisle_id']['options']);
        $this->assertSame($flat, $fields['aisles']['options']);
        $this->assertNull($fields['aisle_id']['options_from'], 'Not an API module: nowhere to fetch it from.');
        $this->assertCount(3, $fields['owner_id']['options']);
        $this->assertNull($fields['owner_id']['options_from']);
    }

    public function test_a_relation_lists_its_options_and_where_they_come_from(): void
    {
        $hello = Post::query()->create(['name' => 'Hello']);

        $fields = collect(app(ApiDocumentation::class)->modules(['api_showcases'])['api_showcases']['fields'])->keyBy('name');

        $this->assertSame(['type' => 'integer'], $fields['post_id']['schema']);
        $this->assertSame([['value' => $hello->id, 'label' => 'Hello']], $fields['post_id']['options']);
        $this->assertSame('posts', $fields['post_id']['options_from']);
        $this->assertSame(['type' => 'array', 'items' => ['type' => 'integer']], $fields['posts']['schema']);
        $this->assertSame('List of ids.', $fields['posts']['note']);
        $this->assertSame([['value' => $hello->id, 'label' => 'Hello']], $fields['posts']['options']);
        $this->assertSame('posts', $fields['posts']['options_from']);
    }

    public function test_eighty_options_are_listed_and_eighty_one_are_referred_to(): void
    {
        foreach (range(1, 80) as $i) {
            Post::query()->create(['name' => "Post {$i}"]);
        }

        $field = fn (): array => collect(app(ApiDocumentation::class)->modules(['api_showcases'])['api_showcases']['fields'])->firstWhere('name', 'post_id');

        $this->assertCount(80, $field()['options']);
        $this->assertSame('posts', $field()['options_from']);

        Post::query()->create(['name' => 'Post 81']);

        $this->assertNull($field()['options']);
        $this->assertSame('posts', $field()['options_from']);

        $markdown = app(ApiDocumentation::class)->markdown(['api_showcases']);
        $this->assertStringContainsString('| `post_id` | id |  | Post. Record id. Options: GET /posts. |', $markdown);
        $this->assertStringNotContainsString('Options for `post_id`', $markdown);

        $schema = app(ApiDocumentation::class)->openApi(['api_showcases'])['components']['schemas']['ApiShowcases'];
        $this->assertSame('Post. Record id.. Options: GET /posts', ((array) $schema['properties'])['post_id']['description']);
    }

    public function test_the_markdown_has_a_row_per_field_and_the_options_below(): void
    {
        $markdown = app(ApiDocumentation::class)->markdown(['api_showcases', 'posts']);

        foreach ([
            '| Каталог / Витрина | `api_showcases` |',
            '| Blog | `posts` |',
            '## Каталог / Витрина',
            '`GET|POST /api_showcases`, `GET|PATCH|DELETE /api_showcases/{id}`',
            '| `name` | string per language | yes | Name. Translatable: a plain value sets the default language (en), an object {"en": …, "de": …} sets several. Read back as that object. |',
            '| `code` | string |  | Code. Unique \| per site. Create only. |',
            '| `price` | number |  | Price. |',
            '| `active` | yes/no |  | Active. |',
            '| `starts_on` | date |  | Starts on. Date: YYYY-MM-DD. |',
            '| `starts_at` | date and time |  | Starts at. Date and time: YYYY-MM-DD HH:MM:SS. |',
            '| `photos` | list of strings |',
            '| `dates` | list of dates |',
            '| `times` | list of dates with time |',
            '| `amounts` | list of numbers |',
            '| `aisle_id` | id |',
            '| `aisles` | list of ids |',
            "Options for `kind`:\n\n- `tour`: Tour\n- `cruise`: Cruise \\| sea",
            "Options for `extras`:\n\n- `wifi`: Wi-Fi\n- `pool`: Pool",
            "Options for `aisle_id`:\n\n- `",
        ] as $line) {
            $this->assertStringContainsString($line, $markdown);
        }

        $this->assertStringNotContainsString('Options for `mood`', $markdown, 'No options, no list.');
        $this->assertStringNotContainsString('`secret`', $markdown);
        $this->assertStringEndsWith("\n", $markdown);
    }

    public function test_html_and_pipes_in_labels_are_kept_as_text_and_do_not_break_the_markdown(): void
    {
        config()->set('atrium.menu', []);

        $markdown = app(ApiDocumentation::class)->markdown(['api_showcases']);

        // The panel shows a label as text, so do the docs: kept, not stripped,
        // and escaped so no viewer takes it for HTML.
        $this->assertStringContainsString('| Showcase \| of \<b\>fields\</b\> | `api_showcases` |', $markdown);
        $this->assertStringContainsString("\n## Showcase | of \\<b\\>fields\\</b\\>\n", $markdown);
        $this->assertStringNotContainsString('<b>', $markdown);
    }

    public function test_a_label_on_several_lines_stays_on_its_row(): void
    {
        config()->set('atrium.menu', []);

        $markdown = app(ApiDocumentation::class)->markdown(['api_showcases']);

        $this->assertStringContainsString("\n| `notes` | string |  | Notes for the team. Edit only. |\n", $markdown);
    }

    public function test_the_maps_key_never_reaches_the_public_documentation(): void
    {
        $this->assertStringNotContainsString('AIza-very-secret-maps-key', (string) $this->get('/api/v1')->getContent());
        $this->assertStringNotContainsString('AIza-very-secret-maps-key', (string) $this->get('/api/v1/openapi.json')->getContent());
    }

    public function test_the_openapi_schema_describes_each_module(): void
    {
        $doc = app(ApiDocumentation::class)->openApi(['api_showcases']);
        $schemas = $doc['components']['schemas'];

        $this->assertSame(['ApiShowcases', 'ApiShowcasesChanges', 'ApiShowcasesRecord'], array_keys($schemas));
        $this->assertSame('object', $schemas['ApiShowcases']['type']);
        $this->assertSame(['name'], $schemas['ApiShowcases']['required']);
        $this->assertArrayNotHasKey('required', $schemas['ApiShowcasesChanges'], 'Updates are partial.');
        $this->assertEquals($schemas['ApiShowcases']['properties'], $schemas['ApiShowcasesChanges']['properties']);
        $this->assertSame('#/components/schemas/ApiShowcases', $schemas['ApiShowcasesRecord']['allOf'][1]['$ref']);
        $this->assertSame(['type' => ['string', 'null']], $schemas['ApiShowcasesRecord']['allOf'][0]['properties']['public_url']);

        $properties = (array) $schemas['ApiShowcases']['properties'];
        $this->assertSame('Kind', $properties['kind']['description'], 'Options with an enum are not repeated in the text.');
        $this->assertSame('Extras. List of values.', $properties['extras']['description']);
        $this->assertSame('Mood', $properties['mood']['description']);
        $this->assertSame(['type' => 'string', 'format' => 'date', 'description' => 'Starts on. Date: YYYY-MM-DD.'], $properties['starts_on']);
        $ids = ApiAisle::query()->orderBy('id')->pluck('id')->all();
        $this->assertSame("Aisle. Record id.. Options: {$ids[0]} = Europe; {$ids[1]} = Europe / Day trips; {$ids[2]} = Asia", $properties['aisle_id']['description']);
        $this->assertSame('Code. Unique | per site. Create only.', $properties['code']['description']);

        $list = $doc['paths']['/api_showcases']['get'];
        $this->assertSame('list_api_showcases', $list['operationId']);
        $this->assertSame('Каталог / Витрина: list', $list['summary']);
        $this->assertSame(['Каталог / Витрина'], $list['tags']);
        $this->assertSame(['page', 'per_page', 'search', 'filter', 'sort', 'direction'], array_column($list['parameters'], 'name'));
        $this->assertSame(['200', '401', '403', '404'], array_map('strval', array_keys($list['responses'])));
        $this->assertSame('#/components/schemas/ApiShowcasesRecord', $list['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref']);

        $create = $doc['paths']['/api_showcases']['post'];
        $this->assertSame('create_api_showcases', $create['operationId']);
        $this->assertSame(['$ref' => '#/components/schemas/ApiShowcases'], $create['requestBody']['content']['application/json']['schema']);
        $this->assertSame(['201', '422', '401', '403', '404'], array_map('strval', array_keys($create['responses'])));

        $one = $doc['paths']['/api_showcases/{id}'];
        $this->assertSame(['get', 'patch', 'delete'], array_keys($one));
        $this->assertSame('get_api_showcases', $one['get']['operationId']);
        $this->assertSame('update_api_showcases', $one['patch']['operationId']);
        $this->assertSame('Каталог / Витрина: update the fields sent', $one['patch']['summary']);
        $this->assertSame(['$ref' => '#/components/schemas/ApiShowcasesChanges'], $one['patch']['requestBody']['content']['application/json']['schema']);
        $this->assertSame('delete_api_showcases', $one['delete']['operationId']);
        $this->assertSame(['204', '409', '401', '403', '404'], array_map('strval', array_keys($one['delete']['responses'])));
        $this->assertSame([['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]], $one['get']['parameters']);

        $files = $doc['paths']['/files']['post'];
        $this->assertSame('upload_file', $files['operationId']);
        $this->assertSame(['file'], $files['requestBody']['content']['multipart/form-data']['schema']['required']);
    }
}
