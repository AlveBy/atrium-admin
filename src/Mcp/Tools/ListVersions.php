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

#[Name('list_versions')]
#[Description('The versions of a record, newest (the record as it is now) first: when, by whom and from where each was saved, and the values of its fields.')]
#[IsReadOnly]
class ListVersions extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'id' => $schema->integer()->description('Record id.')->required(),
        ];
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $module = $this->module($request);

        return $module === null
            ? $this->unknownModule($request)
            : $api->call('GET', $module.'/'.(int) $request->get('id').'/versions', $this->siteQuery($request));
    }
}
