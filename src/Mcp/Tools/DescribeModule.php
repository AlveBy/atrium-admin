<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Mcp\Tools;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Filters\ModuleFilters;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('describe_module')]
#[Description('The fields of a module: name, label, whether required, JSON type, allowed values and notes, and the filters of its list with their values. Read it before creating or updating records.')]
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

        $description = $docs->module($module);

        return Response::json([
            'module' => $module,
            'label' => $description['label'],
            'allowed' => array_keys(array_filter($allowed)),
            'fields' => $description['fields'],
            // What list_records takes in "filter" besides the columns.
            'filters' => app(ModuleFilters::class)->describe((string) app(ApiResources::class)->model($module), $this->owner()),
        ]);
    }
}
