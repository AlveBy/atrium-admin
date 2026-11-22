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

#[Name('run_record_action')]
#[Description('Run an action of a module on one record, as its button in the panel does. See list_actions; ask the user before an action with "confirm" set.')]
#[IsDestructive]
class RunRecordAction extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'id' => $schema->integer()->description('Record id.')->required(),
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
            default => $api->call('POST', $module.'/'.(int) $request->get('id').'/actions/'.$action, $this->siteQuery($request), []),
        };
    }
}
