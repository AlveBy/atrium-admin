<?php

declare(strict_types=1);

namespace Atrium\Admin\Http\Controllers\Api;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Документация API — открытая, без токена.
 *
 * Её читают по ссылке: человек в браузере, ChatGPT, агент. Заголовок
 * с токеном по ссылке не передать, а в документации только устройство
 * полей и подписи разделов, которые и так видны на сайте. Сами записи —
 * только по токену.
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
     * `?only=articles,news` — описание целиком велико
     * (все модули сайта), агенту обычно нужны два-три.
     *
     * @return array<int,string>
     */
    private function only(Request $request): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $request->query('only', '')))));
    }
}
