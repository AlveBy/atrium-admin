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

#[Name('run_bulk_action')]
#[Description('Run an action of a module on several records at once ("delete" among them), as the panel does for the records ticked in a list. See list_actions; ask the user before an action with "confirm" set.')]
#[IsDestructive]
class RunBulkAction extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'ids' => $schema->array()->items($schema->integer())->description('Ids of the records.')->required(),
            'action' => $this->actionArgument($schema),
        ];
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $module = $this->module($request);
        $action = $this->action($request);

        return match (true) {
            $module === null => $this->unknownModule($request),
            $action === null => $this->unknownAction($request),
            default => $api->call('POST', $module.'/bulk/'.$action, $this->siteQuery($request), ['ids' => (array) $request->get('ids', [])]),
        };
    }
}
