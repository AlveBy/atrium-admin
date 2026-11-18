<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Api;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API documentation — public, no token.
 *
 * It is read by link: a person in a browser, a chat assistant, an agent. A
 * token header cannot be passed in a link, and the documentation holds only
 * the field structure and section labels, which are visible on the site
 * anyway. The records themselves require a token.
 */
class DocsController extends Controller
{
    public function markdown(Request $request, ApiDocumentation $docs): Response
    {
        return response($docs->markdown($this->only($request)), 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    public function openApi(Request $request, ApiDocumentation $docs): JsonResponse
    {
        return response()->json($docs->openApi($this->only($request)), 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * `?only=articles,news` — the full description is large
     * (every module of the site), an agent usually needs two or three.
     *
     * @return array<int,string>
     */
    private function only(Request $request): array
    {
        return array_filter(array_map('trim', explode(',', (string) $request->query('only', ''))));
    }
}
