<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
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

        $this->assertSame(['/bares', '/bares/{id}', '/files'], array_keys($paths));

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
    }

    public function test_the_note_of_a_field_comes_before_the_text_the_api_adds(): void
    {
        $fields = collect(app(ApiDocumentation::class)->modules(['api_edges'])['api_edges']['fields'])->keyBy('name');
        $this->assertSame(['name', 'agree', 'tags', 'section_id'], $fields->keys()->all());

        $this->assertSame('Create only. Send true/false; the response contains "Y" or null.', $fields['agree']['note']);
        $this->assertSame('Edit only. List of values.', $fields['tags']['note']);
        $this->assertSame('Create only. Record id.', $fields['section_id']['note']);
        $this->assertSame(['type' => 'integer'], $fields['section_id']['schema'], 'A tree without "multiple" is a single choice.');

        $aisle = ApiAisle::query()->create(['name' => '123', 'sort' => 1]);
        $this->assertSame([['value' => $aisle->id, 'label' => '123']], collect(app(ApiDocumentation::class)->modules(['api_edges'])['api_edges']['fields'])->keyBy('name')['section_id']['options']);
    }
}
