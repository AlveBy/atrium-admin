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

#[Name('list_actions')]
#[Description('The actions of a module this token may run: "record" ones on one record (run_record_action) and "bulk" ones on several (run_bulk_action). An action with "confirm" set asks the user first in the panel: ask them too.')]
#[IsReadOnly]
class ListActions extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return $this->moduleArgument($schema);
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $module = $this->module($request);

        return $module === null
            ? $this->unknownModule($request)
            : $api->call('GET', $module.'/actions', $this->siteQuery($request));
    }
}
