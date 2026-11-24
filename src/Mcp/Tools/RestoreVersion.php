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

#[Name('restore_version')]
#[Description('Put a record back as one of its versions (from list_versions) holds it. What the record holds now stays as a version of its own.')]
class RestoreVersion extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'id' => $schema->integer()->description('Record id.')->required(),
            'version' => $schema->integer()->description('Version id from list_versions.')->required(),
        ];
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $module = $this->module($request);

        return $module === null
            ? $this->unknownModule($request)
            : $api->call('POST', $module.'/'.(int) $request->get('id').'/versions/'.(int) $request->get('version').'/restore', $this->siteQuery($request));
    }
}
