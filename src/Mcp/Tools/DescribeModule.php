<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Mcp\Tools;

use Atrium\Admin\Api\ApiDocumentation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('describe_module')]
#[Description('The fields of a module: name, label, whether required, JSON type, allowed values and notes. Read it before creating or updating records.')]
#[IsReadOnly]
class DescribeModule extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return ['module' => $this->moduleArgument($schema)['module']];
    }

    public function handle(Request $request, ApiDocumentation $docs): Response
    {
        $module = (string) $request->get('module', '');
        $allowed = $this->allowed($module);

        if (! $allowed['view']) {
            return Response::error("No module \"{$module}\" for this token. See list_modules.");
        }

        $description = $docs->modules([$module])[$module];

        return Response::json([
            'module' => $module,
            'label' => $description['label'],
            'allowed' => array_keys(array_filter($allowed)),
            'fields' => $description['fields'],
        ]);
    }
}
