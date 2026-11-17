<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Mcp\ApiGateway;
use Atrium\Admin\Tests\TestCase;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Request as RequestFacade;
use Laravel\Mcp\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What the gateway hands the router, what it restores afterwards, and how it
 * turns the API's answer into an MCP result. The router is a stand-in that
 * records the sub-request and answers whatever the test says.
 */
final class ApiGatewayTest extends TestCase
{
    public function test_the_sub_request_goes_to_the_api_prefix_on_the_same_host_with_the_same_token(): void
    {
        $this->app->instance('request', Request::create('https://example.test/mcp', 'POST'));
        request()->headers->set('Authorization', 'Bearer abc');
        config()->set('atrium.api.prefix', '/api/v1/');

        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('GET', '/posts/5', ['page' => 2]);

        $sub = $router->seen;
        $this->assertSame('https://example.test/api/v1/posts/5?page=2', $sub->fullUrl());
        $this->assertSame('GET', $sub->getMethod());
        $this->assertSame('Bearer abc', $sub->headers->get('Authorization'));
        $this->assertSame('application/json', $sub->headers->get('Content-Type'));
        $this->assertSame('application/json', $sub->headers->get('Accept'));
    }

    public function test_a_prefix_without_slashes_gets_none_doubled(): void
    {
        config()->set('atrium.api.prefix', 'v2');

        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('GET', 'posts');

        $this->assertSame('/v2/posts', $router->seen->getPathInfo());
    }

    public function test_a_missing_token_stays_empty(): void
    {
        $this->app->instance('request', Request::create('/mcp', 'POST'));

        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('GET', 'posts');

        $this->assertSame('', $router->seen->headers->get('Authorization'));
    }

    public function test_the_body_goes_as_json_with_unicode_and_slashes_as_they_are(): void
    {
        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('POST', 'posts', [], ['name' => 'Café / bar']);

        $this->assertSame('{"name":"Café / bar"}', $router->seen->getContent());
    }

    public function test_no_body_means_no_content(): void
    {
        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('GET', 'posts');

        $this->assertSame('', $router->seen->getContent());
    }

    public function test_a_body_that_cannot_be_encoded_is_sent_empty_instead_of_failing(): void
    {
        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('POST', 'posts', [], ['name' => "\xB1"]);

        $this->assertSame('', $router->seen->getContent());
    }

    public function test_the_api_sees_the_sub_request_as_the_current_one_while_it_runs(): void
    {
        $original = Request::create('/mcp', 'POST');
        $this->app->instance('request', $original);
        RequestFacade::path(); // the facade has resolved the original by now

        $inside = [];
        $router = $this->router(function (Request $sub) use (&$inside) {
            $inside = [app('request') === $sub, RequestFacade::path(), url()->getRequest() === $sub];

            return new HttpResponse('{}', 200);
        });
        (new ApiGateway($router))->call('GET', 'posts');

        $this->assertSame([true, 'api/v1/posts', true], $inside);
    }

    public function test_the_original_request_is_back_afterwards_for_everyone(): void
    {
        $original = Request::create('/mcp', 'POST');
        $this->app->instance('request', $original);

        $router = $this->router(fn (Request $r) => new HttpResponse('{}', 200));
        (new ApiGateway($router))->call('GET', 'posts');

        $this->assertSame($original, app('request'));
        $this->assertSame('mcp', RequestFacade::path());
        $this->assertSame($original, url()->getRequest());
    }

    public function test_the_original_request_is_back_even_when_the_api_throws(): void
    {
        $original = Request::create('/mcp', 'POST');
        $this->app->instance('request', $original);
        $router = $this->router(fn () => throw new RuntimeException('boom'));

        try {
            (new ApiGateway($router))->call('GET', 'posts');
            $this->fail('The exception must go on.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame($original, app('request'));
        $this->assertSame('mcp', RequestFacade::path());
        $this->assertSame($original, url()->getRequest());
    }

    public function test_an_answer_comes_back_as_json(): void
    {
        $result = $this->gateway(new HttpResponse('{"data":{"a":"é/b"}}', 200));

        $this->assertFalse($result->isError());
        $this->assertSame('{"data":{"a":"é/b"}}', (string) $result->content());
    }

    public function test_204_is_done_and_its_neighbours_are_not(): void
    {
        $this->assertSame('Done.', (string) $this->gateway(new HttpResponse('', 204))->content());
        $this->assertFalse($this->gateway(new HttpResponse('', 204))->isError());
        $this->assertSame('null', (string) $this->gateway(new HttpResponse('', 203))->content());
        $this->assertSame('null', (string) $this->gateway(new HttpResponse('', 205))->content());
    }

    public function test_a_streamed_answer_has_no_content_and_counts_as_empty(): void
    {
        $result = $this->gateway(new StreamedResponse(static function (): void {}, 200));

        $this->assertFalse($result->isError());
        $this->assertSame('null', (string) $result->content());
    }

    public function test_400_and_above_is_an_error_and_399_is_not(): void
    {
        $this->assertTrue($this->gateway(new HttpResponse('{"message":"Bad"}', 400))->isError());
        $this->assertSame('HTTP 400. Bad', (string) $this->gateway(new HttpResponse('{"message":"Bad"}', 400))->content());
        $this->assertFalse($this->gateway(new HttpResponse('{"a":1}', 399))->isError());
    }

    public function test_an_error_without_a_message_says_the_request_failed(): void
    {
        $this->assertSame('HTTP 500. The request failed.', (string) $this->gateway(new HttpResponse('', 500))->content());
        $this->assertSame('HTTP 500. The request failed.', (string) $this->gateway(new HttpResponse('[1,2]', 500))->content());
        $this->assertSame('HTTP 500. The request failed.', (string) $this->gateway(new HttpResponse('"text"', 500))->content());
    }

    public function test_every_invalid_field_is_listed_with_all_its_messages(): void
    {
        $body = json_encode(['message' => 'Invalid.', 'errors' => ['name' => ['Required.', 'Too short.'], 'sort' => ['Not a number.']]]);

        $this->assertSame(
            "HTTP 422. Invalid.\n- name: Required. Too short.\n- sort: Not a number.",
            (string) $this->gateway(new HttpResponse((string) $body, 422))->content(),
        );
    }

    public function test_errors_that_are_not_a_list_are_still_shown(): void
    {
        $this->assertSame(
            "HTTP 422. Invalid.\n- 0: Oops",
            (string) $this->gateway(new HttpResponse('{"message":"Invalid.","errors":"Oops"}', 422))->content(),
        );
        $this->assertSame(
            "HTTP 422. Invalid.\n- name: Required.",
            (string) $this->gateway(new HttpResponse('{"message":"Invalid.","errors":{"name":"Required."}}', 422))->content(),
        );
    }

    private function gateway(HttpResponse $answer): Response
    {
        return (new ApiGateway($this->router(fn () => $answer)))->call('GET', 'posts');
    }

    /**
     * @param  Closure(Request):HttpResponse  $answer
     */
    private function router(Closure $answer): Router
    {
        return new class($this->app['events'], $answer) extends Router
        {
            public ?Request $seen = null;

            public function __construct($events, private readonly Closure $answer)
            {
                parent::__construct($events);
            }

            public function dispatch(Request $request)
            {
                $this->seen = $request;

                return ($this->answer)($request);
            }
        };
    }
}
