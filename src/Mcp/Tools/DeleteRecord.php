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
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_record')]
#[Description('Delete a record. A record of a module with the trash goes there and can be brought back (restore_from_trash); any other is gone for good. Ask the user before calling it.')]
#[IsDestructive]
class DeleteRecord extends ApiTool
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
            : $api->call('DELETE', $module.'/'.(int) $request->get('id'), $this->siteQuery($request));
    }
}
