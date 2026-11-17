<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Mcp\ApiGateway;
use Atrium\Admin\Mcp\Tools\CreateRecord;
use Atrium\Admin\Mcp\Tools\DeleteRecord;
use Atrium\Admin\Mcp\Tools\GetRecord;
use Atrium\Admin\Mcp\Tools\ListRecords;
use Atrium\Admin\Mcp\Tools\UpdateRecord;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\Request;
use Laravel\Mcp\Request as McpRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exactly what each record tool sends to the API: method, address, query and
 * body, with nothing but what the assistant asked for.
 */
final class McpToolQueryTest extends TestCase
{
    public function test_list_records_sends_every_argument_that_has_a_value(): void
    {
        $seen = $this->sent(new ListRecords, [
            'module' => 'posts', 'site' => 'shop', 'search' => 'tea', 'filter' => ['active' => 1], 'sort' => 'name',
            'direction' => 'desc', 'page' => 2, 'per_page' => 5,
        ]);

        $this->assertSame('GET', $seen->getMethod());
        $this->assertSame('/api/v1/posts', $seen->getPathInfo());
        $this->assertSame(
            ['search' => 'tea', 'filter' => ['active' => 1], 'sort' => 'name', 'direction' => 'desc', 'page' => 2, 'per_page' => 5, 'site' => 'shop'],
            $seen->query->all(),
        );
    }

    public function test_list_records_leaves_out_what_is_null_empty_or_an_empty_list(): void
    {
        $this->assertSame([], $this->sent(new ListRecords, ['module' => 'posts'])->query->all());
        $this->assertSame([], $this->sent(new ListRecords, [
            'module' => 'posts', 'site' => '', 'search' => '', 'filter' => [], 'sort' => null, 'direction' => '', 'page' => null, 'per_page' => null,
        ])->query->all());
    }

    public function test_the_site_is_sent_as_text_and_only_when_there_is_one(): void
    {
        $this->assertSame([], $this->sent(new GetRecord, ['module' => 'posts', 'id' => 1, 'site' => null])->query->all());
        $this->assertSame(['site' => '5'], $this->sent(new GetRecord, ['module' => 'posts', 'id' => 1, 'site' => 5])->query->all());
        $this->assertSame(['site' => '0'], $this->sent(new GetRecord, ['module' => 'posts', 'id' => 1, 'site' => '0'])->query->all());
    }

    public function test_list_records_keeps_values_that_are_falsy_but_not_empty(): void
    {
        $this->assertSame(
            ['search' => '0', 'page' => 0],
            $this->sent(new ListRecords, ['module' => 'posts', 'search' => '0', 'page' => 0])->query->all(),
        );
    }

    public function test_get_and_delete_address_one_record(): void
    {
        $seen = $this->sent(new GetRecord, ['module' => 'posts', 'id' => 7, 'site' => 'shop']);
        $this->assertSame(['GET', '/api/v1/posts/7', ['site' => 'shop']], [$seen->getMethod(), $seen->getPathInfo(), $seen->query->all()]);

        $seen = $this->sent(new DeleteRecord, ['module' => 'posts', 'id' => 7]);
        $this->assertSame(['DELETE', '/api/v1/posts/7', []], [$seen->getMethod(), $seen->getPathInfo(), $seen->query->all()]);
    }

    public function test_create_and_update_send_the_data_as_the_body(): void
    {
        $seen = $this->sent(new CreateRecord, ['module' => 'posts', 'data' => ['name' => 'Tea'], 'site' => 'shop']);
        $this->assertSame(['POST', '/api/v1/posts', ['site' => 'shop'], '{"name":"Tea"}'], [$seen->getMethod(), $seen->getPathInfo(), $seen->request->all(), $seen->getContent()]);

        $seen = $this->sent(new UpdateRecord, ['module' => 'posts', 'id' => 7, 'data' => ['name' => 'Tea']]);
        $this->assertSame(['PATCH', '/api/v1/posts/7', [], '{"name":"Tea"}'], [$seen->getMethod(), $seen->getPathInfo(), $seen->request->all(), $seen->getContent()]);

        $seen = $this->sent(new CreateRecord, ['module' => 'posts']);
        $this->assertSame('[]', $seen->getContent());

        // Data that is not an object is still wrapped into a list, never handed on as a bare scalar.
        $this->assertSame('["x"]', $this->sent(new CreateRecord, ['module' => 'posts', 'data' => 'x'])->getContent());
        $this->assertSame('["x"]', $this->sent(new UpdateRecord, ['module' => 'posts', 'id' => 1, 'data' => 'x'])->getContent());
        $this->assertSame('[]', $this->sent(new UpdateRecord, ['module' => 'posts', 'id' => 1])->getContent());
    }

    /**
     * @param  array<string,mixed>  $arguments
     */
    private function sent(ListRecords|GetRecord|DeleteRecord|CreateRecord|UpdateRecord $tool, array $arguments): Request
    {
        $router = $this->recordingRouter(fn () => new Response('{}', 200));
        $tool->handle(new McpRequest($arguments), new ApiGateway($router));

        return $router->seen;
    }
}
