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

#[Name('list_trash')]
#[Description('Records of a module that were deleted and wait in the trash, newest first: id, title, when and by whom deleted.')]
#[IsReadOnly]
class ListTrash extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'page' => $schema->integer()->description('Page number, from 1.'),
            'per_page' => $schema->integer()->description('Records per page, 1 to 100. Default: 20.'),
        ];
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $module = $this->module($request);
        $query = [
            'page' => $request->get('page'),
            'per_page' => $request->get('per_page'),
        ];

        return $module === null
            ? $this->unknownModule($request)
            : $api->call('GET', $module.'/trash', $query + $this->siteQuery($request));
    }
}
