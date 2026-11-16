<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Mcp\Tools;

use Atrium\Admin\Api\ApiResources;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_modules')]
#[Description('The admin modules this token may use, with what it may do in each: view, create, update, delete.')]
#[IsReadOnly]
class ListModules extends ApiTool
{
    public function handle(ApiResources $resources): Response
    {
        $modules = [];

        foreach (array_keys($resources->all()) as $key) {
            $allowed = $this->allowed($key);

            if (in_array(true, $allowed, true)) {
                $modules[] = ['module' => $key, 'label' => $resources->label($key), 'allowed' => array_keys(array_filter($allowed))];
            }
        }

        return $modules === []
            ? Response::error('This token may not use any module. Ask an administrator to issue one with access to the modules you need.')
            : Response::json($modules);
    }
}
