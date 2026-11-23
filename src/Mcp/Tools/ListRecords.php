<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Mcp\Tools;

use Atrium\Admin\Mcp\ApiGateway;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_records')]
#[Description('Records of a module, page by page: search, filter by column values, sort.')]
#[IsReadOnly]
class ListRecords extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'search' => $schema->string()->description('Text to look for in the searchable columns.'),
            'filter' => $schema->object()->description('Exact column values, e.g. {"active": 1, "section_id": 4}, and the list\'s filters that describe_module names, with one of their values.'),
            'sort' => $schema->string()->description('Column to sort by. Default: id.'),
            'direction' => $schema->string()->enum(['asc', 'desc'])->description('Default: asc.'),
            'page' => $schema->integer()->description('Page number, from 1.'),
            'per_page' => $schema->integer()->description('Records per page, 1 to 100. Default: 20.'),
        ];
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $query = array_filter([
            'search' => $request->get('search'),
            'filter' => $request->get('filter'),
            'sort' => $request->get('sort'),
            'direction' => $request->get('direction'),
            'page' => $request->get('page'),
            'per_page' => $request->get('per_page'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $module = $this->module($request);

        return $module === null
            ? $this->unknownModule($request)
            : $api->call('GET', $module, $query + $this->siteQuery($request));
    }
}
