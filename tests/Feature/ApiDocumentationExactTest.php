<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\Fixtures\ApiAisle;
use Atrium\Admin\Tests\Fixtures\ApiEdges;
use Atrium\Admin\Tests\Fixtures\Bare;
use Atrium\Admin\Tests\TestCase;

/**
 * The OpenAPI document of a module in full, key by key and in order: what an
 * outside program reads is exactly this.
 */
final class ApiDocumentationExactTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://shop.test');
        $app['config']->set('atrium.resources', [Bare::class, ApiEdges::class]);
    }

    private function encoded(mixed $value): string
    {
        return (string) json_encode($value);
    }

    public function test_a_module_without_required_fields_has_no_required_list(): void
    {
        $schemas = app(ApiDocumentation::class)->openApi(['bares'])['components']['schemas'];

        $this->assertSame(
            $this->encoded(['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'maxLength' => 65535, 'description' => 'Name']]]),
            $this->encoded($schemas['Bares']),
        );
        $this->assertSame(
            $this->encoded(['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'maxLength' => 65535, 'description' => 'Name']]]),
            $this->encoded($schemas['BaresChanges']),
        );
        $this->assertSame(
            $this->encoded(['allOf' => [
                ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'admin_url' => ['type' => 'string'],
                    'public_url' => ['type' => ['string', 'null']],
                ]],
                ['$ref' => '#/components/schemas/Bares'],
            ]]),
            $this->encoded($schemas['BaresRecord']),
        );
    }

    public function test_the_paths_of_a_module_in_full(): void
    {
        $errors = [
            '401' => ['description' => 'No valid token'],
            '403' => ['description' => 'The token has no ability for the module'],
            '404' => ['description' => 'No such module or record'],
        ];
        $json = static fn (array $schema): array => ['content' => ['application/json' => ['schema' => $schema]]];
        $record = $json(['type' => 'object', 'properties' => ['data' => ['$ref' => '#/components/schemas/BaresRecord']]]);
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];

        $paths = app(ApiDocumentation::class)->openApi(['bares'])['paths'];

        $this->assertSame(['/bares', '/bares/{id}', '/files', '/{module}/actions', '/{module}/{id}/actions/{action}', '/{module}/bulk/{action}', '/{module}/{id}/versions', '/{module}/{id}/versions/{version}/restore', '/{module}/trash', '/{module}/trash/{id}/restore', '/{module}/trash/{id}'], array_keys($paths));

        $this->assertSame($this->encoded([
            'get' => [
                'operationId' => 'list_bares',
                'summary' => 'Bare: list',
                'tags' => ['Bare'],
                'parameters' => [
                    ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]],
                    ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ['name' => 'filter', 'in' => 'query', 'style' => 'deepObject', 'explode' => true, 'schema' => ['type' => 'object']],
                    ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc']]],
                ],
                'responses' => ['200' => ['description' => 'List page'] + $json(['type' => 'object', 'properties' => [
                    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/BaresRecord']],
                    'meta' => ['type' => 'object'],
                ]])] + $errors,
            ],
            'post' => [
                'operationId' => 'create_bares',
                'summary' => 'Bare: create',
                'tags' => ['Bare'],
                'requestBody' => ['required' => true] + $json(['$ref' => '#/components/schemas/Bares']),
                'responses' => ['201' => ['description' => 'Created'] + $record, '422' => ['description' => 'Fields failed validation']] + $errors,
            ],
        ]), $this->encoded($paths['/bares']));

        $this->assertSame($this->encoded([
            'get' => [
                'operationId' => 'get_bares',
                'summary' => 'Bare: record',
                'tags' => ['Bare'],
                'parameters' => [$id],
                'responses' => ['200' => ['description' => 'Record'] + $record] + $errors,
            ],
            'patch' => [
                'operationId' => 'update_bares',
                'summary' => 'Bare: update the fields sent',
                'tags' => ['Bare'],
                'parameters' => [$id],
                'requestBody' => ['required' => true] + $json(['$ref' => '#/components/schemas/BaresChanges']),
                'responses' => ['200' => ['description' => 'Updated'] + $record, '422' => ['description' => 'Fields failed validation']] + $errors,
            ],
            'delete' => [
                'operationId' => 'delete_bares',
                'summary' => 'Bare: delete',
                'tags' => ['Bare'],
                'parameters' => [$id],
                'responses' => ['204' => ['description' => 'Deleted'], '409' => ['description' => 'The record cannot be deleted']] + $errors,
            ],
        ]), $this->encoded($paths['/bares/{id}']));

        $this->assertSame($this->encoded(['post' => [
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
        ]]), $this->encoded($paths['/files']));

        $module = ['name' => 'module', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        $action = ['name' => 'action', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
        $result = $json(['type' => 'object', 'properties' => ['message' => ['type' => 'string'], 'redirect' => ['type' => ['string', 'null']]]]);

        $this->assertSame($this->encoded(['get' => [
            'operationId' => 'list_actions',
            'summary' => 'Actions of a module the token may run',
            'parameters' => [$module],
            'responses' => ['200' => ['description' => 'Actions on one record and on several'] + $json(['type' => 'object', 'properties' => [
                'data' => ['type' => 'object', 'properties' => ['record' => ['type' => 'array'], 'bulk' => ['type' => 'array']]],
            ]])] + $errors,
        ]]), $this->encoded($paths['/{module}/actions']));

        $this->assertSame($this->encoded(['post' => [
            'operationId' => 'run_record_action',
            'summary' => 'Run an action on a record',
            'parameters' => [$module, $id, $action],
            'responses' => ['200' => ['description' => 'Done'] + $result, '422' => ['description' => 'The action failed'] + $result] + $errors,
        ]]), $this->encoded($paths['/{module}/{id}/actions/{action}']));

        $this->assertSame($this->encoded(['post' => [
            'operationId' => 'run_bulk_action',
            'summary' => 'Run an action on several records',
            'parameters' => [$module, $action],
            'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => [
                'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 1000],
            ], 'required' => ['ids']]),
            'responses' => ['200' => ['description' => 'Done'] + $result, '422' => ['description' => 'The action failed or no record was found'] + $result] + $errors,
        ]]), $this->encoded($paths['/{module}/bulk/{action}']));

        $version = ['name' => 'version', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']];
        $this->assertSame($this->encoded(['get' => [
            'operationId' => 'list_versions',
            'summary' => 'Versions of a record, newest first',
            'parameters' => [$module, $id],
            'responses' => ['200' => ['description' => 'The versions'] + $json(['type' => 'object', 'properties' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'at' => ['type' => ['string', 'null'], 'format' => 'date-time'],
                    'admin' => ['type' => ['string', 'null']],
                    'source' => ['type' => ['string', 'null']],
                    'values' => ['type' => 'object'],
                ]]],
            ]])] + $errors,
        ]]), $this->encoded($paths['/{module}/{id}/versions']));

        $this->assertSame($this->encoded(['post' => [
            'operationId' => 'restore_version',
            'summary' => 'Put a record back as one of its versions holds it',
            'parameters' => [$module, $id, $version],
            'responses' => ['200' => ['description' => 'The record as restored'] + $json(['type' => 'object', 'properties' => ['data' => ['type' => 'object']]]), '422' => ['description' => 'The version no longer passes validation']] + $errors,
        ]]), $this->encoded($paths['/{module}/{id}/versions/{version}/restore']));

        $this->assertSame($this->encoded(['get' => [
            'operationId' => 'list_trash',
            'summary' => 'Records of a module in the trash, newest first',
            'parameters' => [$module],
            'responses' => ['200' => ['description' => 'List page'] + $json(['type' => 'object', 'properties' => [
                'data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => ['string', 'null']],
                    'deleted_at' => ['type' => 'string', 'format' => 'date-time'],
                    'deleted_by' => ['type' => ['string', 'null']],
                ]]],
                'meta' => ['type' => 'object'],
            ]])] + $errors,
        ]]), $this->encoded($paths['/{module}/trash']));

        $this->assertSame($this->encoded(['post' => [
            'operationId' => 'restore_from_trash',
            'summary' => 'Bring a record back from the trash',
            'parameters' => [$module, $id],
            'responses' => ['200' => ['description' => 'The record as restored'] + $json(['type' => 'object', 'properties' => ['data' => ['type' => 'object']]])] + $errors,
        ]]), $this->encoded($paths['/{module}/trash/{id}/restore']));

        $this->assertSame($this->encoded(['delete' => [
            'operationId' => 'delete_forever',
            'summary' => 'Delete a record in the trash for good',
            'parameters' => [$module, $id],
            'responses' => ['204' => ['description' => 'Deleted for good'], '409' => ['description' => 'The record cannot be deleted']] + $errors,
        ]]), $this->encoded($paths['/{module}/trash/{id}']));
    }

    public function test_a_translatable_field_keeps_its_own_note_and_is_typed_by_its_plain_value(): void
    {
        Hook::filter('atrium.module.translatable', static fn (array $names): array => [...$names, 'starts', 'limit', 'name']);

        $fields = collect(app(ApiDocumentation::class)->modules(['api_edges'])['api_edges']['fields'])->keyBy('name');
        $translatable = ' Translatable: a plain value sets the default language (en), an object {"en": …} sets several. Read back as that object.';

        $this->assertSame('Edit only. Date: YYYY-MM-DD.'.$translatable, $fields['starts']['note']);
        $this->assertSame('Create only.'.$translatable, $fields['limit']['note']);
        $this->assertSame(trim($translatable), $fields['name']['note']);

        $markdown = app(ApiDocumentation::class)->markdown(['api_edges']);
        $this->assertStringContainsString('| `limit` | number per language |', $markdown);
        $this->assertStringContainsString('| `starts` | date per language |', $markdown);
        $this->assertStringContainsString('| `name` | string per language |', $markdown);
    }

    public function test_the_note_of_a_field_comes_before_the_text_the_api_adds(): void
    {
        $fields = collect(app(ApiDocumentation::class)->modules(['api_edges'])['api_edges']['fields'])->keyBy('name');
        $this->assertSame(['name', 'agree', 'tags', 'section_id', 'limit', 'starts'], $fields->keys()->all());
        $this->assertSame([0, 1, 2, 3, 4, 5], array_keys(app(ApiDocumentation::class)->modules(['api_edges'])['api_edges']['fields']), 'A list, though a field before them is left out.');
        $this->assertSame('Create only.', $fields['limit']['note']);
        $this->assertSame('Edit only. Date: YYYY-MM-DD.', $fields['starts']['note']);

        $this->assertSame('Create only. Send true/false; the response contains "Y" or null.', $fields['agree']['note']);
        $this->assertSame('Edit only. List of values.', $fields['tags']['note']);
        $this->assertSame('Create only. Record id.', $fields['section_id']['note']);
        $this->assertSame(['type' => 'integer'], $fields['section_id']['schema'], 'A tree without "multiple" is a single choice.');

        $aisle = ApiAisle::query()->create(['name' => '123', 'sort' => 1]);
        $this->assertSame([['value' => $aisle->id, 'label' => '123']], collect(app(ApiDocumentation::class)->modules(['api_edges'])['api_edges']['fields'])->keyBy('name')['section_id']['options']);
    }
}
