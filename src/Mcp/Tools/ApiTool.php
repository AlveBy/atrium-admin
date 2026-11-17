<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Mcp\Tools;

use Atrium\Admin\Api\ApiResources;
use Atrium\Admin\Models\AdminUser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Shared bits of the record tools: the module and site arguments, and what
 * the token behind the call may do.
 */
abstract class ApiTool extends Tool
{
    /**
     * @return array<string,mixed>
     */
    protected function moduleArgument(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()->description('Module key from list_modules, e.g. "articles".')->required(),
            'site' => $schema->string()->description('Site code, when the record belongs to another site than this address.'),
        ];
    }

    /**
     * The module key, only if it is one of the API's modules: the argument
     * becomes part of the API address and must not point anywhere else.
     */
    protected function module(Request $request): ?string
    {
        $module = (string) $request->get('module', '');

        return app(ApiResources::class)->model($module) === null ? null : $module;
    }

    protected function unknownModule(Request $request): Response
    {
        return Response::error('No module "'.$request->get('module', '').'". See list_modules.');
    }

    /**
     * @return array<string,string>
     */
    protected function siteQuery(Request $request): array
    {
        $site = (string) $request->get('site', '');

        return $site === '' ? [] : ['site' => $site];
    }

    protected function owner(): ?AdminUser
    {
        $user = auth()->guard((string) config('atrium.auth.guard', 'admin'))->user();

        return $user instanceof AdminUser ? $user : null;
    }

    /**
     * What the token may do in a module: its abilities and, behind them, its
     * owner's permissions today.
     *
     * @return array{view:bool,create:bool,update:bool,delete:bool}
     */
    protected function allowed(string $module): array
    {
        $owner = $this->owner();
        $known = app(ApiResources::class)->model($module) !== null;
        $can = fn (string $mode, string $action): bool => $known
            && $owner !== null
            && $owner->tokenCan("{$module}:{$mode}")
            && $owner->hasAdminPermission("{$module}.{$action}");

        return [
            'view' => $can('read', 'view'),
            'create' => $can('write', 'create'),
            'update' => $can('write', 'update'),
            'delete' => $can('write', 'delete'),
        ];
    }
}
