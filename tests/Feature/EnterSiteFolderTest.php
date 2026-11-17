<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Http\Middleware\EnterSiteFolder;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * The middleware on its own, with a request of its own: what the next layer
 * sees while the folder is moved into the base URL, and what is back after.
 */
final class EnterSiteFolderTest extends TestCase
{
    private function site(string $code, ?string $folder): Site
    {
        return Site::query()->create(['code' => $code, 'name' => ucfirst($code), 'folder' => $folder]);
    }

    /**
     * @param  array<string,string>  $server
     */
    private function request(string $uri, array $server = []): Request
    {
        $request = Request::create($uri, 'GET', server: $server);
        $this->app->instance('request', $request);

        return $request;
    }

    /**
     * @return array<string,mixed> what the next layer saw
     */
    private function enter(Request $request): array
    {
        $seen = [];

        app(EnterSiteFolder::class)->handle($request, function (Request $r) use (&$seen): Response {
            $seen = [
                'site' => $r->attributes->get(EnterSiteFolder::SITE),
                'folder' => $r->attributes->get(EnterSiteFolder::FOLDER),
                'path' => $r->getPathInfo(),
                'base' => $r->getBaseUrl(),
                'basePath' => $r->getBasePath(),
                'generator' => url()->getRequest() === $r,
                'url' => url('x'),
                'asset' => asset('a.css'),
            ];

            return new Response('ok');
        });

        return $seen;
    }

    public function test_a_site_at_the_root_is_noted_and_nothing_moves(): void
    {
        $main = Site::query()->where('code', 'main')->sole();
        $this->site('second', 'site2');

        $seen = $this->enter($this->request('http://main.test/notes/a'));

        $this->assertSame($main->id, $seen['site']);
        $this->assertNull($seen['folder']);
        $this->assertSame(['/notes/a', '', ''], [$seen['path'], $seen['base'], $seen['basePath']]);
    }

    public function test_a_site_in_a_folder_is_noted_with_its_folder_and_the_rest_of_the_path_is_left(): void
    {
        $second = $this->site('second', 'site2');

        $seen = $this->enter($this->request('http://main.test/site2/notes/a'));

        $this->assertSame([$second->id, 'site2'], [$seen['site'], $seen['folder']]);
        $this->assertSame(['/notes/a', '/site2', '/site2'], [$seen['path'], $seen['base'], $seen['basePath']]);

        $seen = $this->enter($this->request('http://main.test/site2'));
        $this->assertSame(['/', '/site2'], [$seen['path'], $seen['base']]);
    }

    public function test_the_folder_is_added_to_a_base_the_request_already_has(): void
    {
        $this->site('second', 'site2');

        $seen = $this->enter($this->request('http://main.test/sub/site2/notes/a', [
            'SCRIPT_NAME' => '/sub/index.php',
            'SCRIPT_FILENAME' => '/var/www/sub/index.php',
            'PHP_SELF' => '/sub/index.php',
        ]));

        $this->assertSame(['/notes/a', '/sub/site2', '/sub/site2'], [$seen['path'], $seen['base'], $seen['basePath']]);
    }

    public function test_the_admin_the_api_and_the_mcp_are_not_inside_a_folder(): void
    {
        $this->site('second', 'site2');

        foreach (['/site2/admin', '/site2/admin/', '/site2/admin/login', '/site2/api/v1/posts', '/site2/mcp'] as $path) {
            $request = $this->request('http://main.test'.$path);
            $seen = $this->enter($request);

            $this->assertSame([null, null, $path, ''], [$seen['site'], $seen['folder'], $seen['path'], $seen['base']], $path.' went on as a page of the site.');
        }
    }

    public function test_a_page_that_only_starts_like_a_prefix_is_a_page_of_the_site(): void
    {
        $second = $this->site('second', 'site2');

        foreach (['/site2/administrators', '/site2/apiary', '/site2/mcp-news', '/site2/adm'] as $path) {
            $seen = $this->enter($this->request('http://main.test'.$path));

            $this->assertSame([$second->id, 'site2'], [$seen['site'], $seen['folder']], $path);
        }
    }

    public function test_prefixes_are_taken_without_their_slashes(): void
    {
        config()->set('atrium.prefix', '/cp/');
        config()->set('atrium.api.prefix', '/rest/v2/');
        config()->set('atrium.mcp.path', '/ai/');
        $second = $this->site('second', 'site2');

        foreach (['/site2/cp', '/site2/cp/x', '/site2/rest/v2/posts', '/site2/ai'] as $path) {
            $seen = $this->enter($this->request('http://main.test'.$path));
            $this->assertSame([null, null, ''], [$seen['site'], $seen['folder'], $seen['base']], $path);
        }
        $this->assertSame($second->id, $this->enter($this->request('http://main.test/site2/admin/x'))['site']);
    }

    public function test_an_empty_prefix_claims_nothing(): void
    {
        config()->set('atrium.mcp.path', '');
        $second = $this->site('second', 'site2');

        $this->assertSame($second->id, $this->enter($this->request('http://main.test/site2/anything'))['site']);
    }

    public function test_the_next_layer_builds_addresses_inside_the_folder_with_assets_at_the_root(): void
    {
        $this->site('second', 'site2');
        $request = $this->request('http://main.test/site2/notes/a');
        url('warm-up'); // the generator has cached the root as it was before the folder

        $seen = $this->enter($request);

        $this->assertTrue($seen['generator']);
        $this->assertSame('http://main.test/site2/x', $seen['url']);
        $this->assertSame('http://main.test/a.css', $seen['asset']);
    }

    public function test_an_asset_origin_that_is_already_set_stays_while_the_folder_is_entered(): void
    {
        $this->site('second', 'site2');
        $request = $this->request('http://main.test/site2/notes/a');
        url()->useAssetOrigin('https://cdn.test');

        $seen = $this->enter($request);

        $this->assertSame('https://cdn.test/a.css', $seen['asset']);
        $this->assertSame('https://cdn.test/a.css', asset('a.css'), 'And after: the origin of before.');
    }

    public function test_nothing_of_the_folder_outlives_the_request(): void
    {
        $this->site('second', 'site2');
        $request = $this->request('http://main.test/site2/notes/a');
        url('warm-up');

        $this->enter($request);

        $outside = url()->getRequest();
        $this->assertNotSame($request, $outside);
        $this->assertSame(['/site2/notes/a', ''], [$outside->getPathInfo(), $outside->getBaseUrl()]);
        $this->assertSame('http://main.test/x', url('x'));
        $this->assertSame('http://main.test/a.css', asset('a.css'));

        // The asset origin worked out for the folder is gone too: the next request is on another host.
        $this->request('http://other.test/');
        $this->assertSame('http://other.test/a.css', asset('a.css'));
    }

    public function test_nothing_of_the_folder_outlives_a_request_that_failed(): void
    {
        $this->site('second', 'site2');
        $request = $this->request('http://main.test/site2/notes/a');
        url('warm-up');
        url()->useAssetOrigin('https://cdn.test');

        try {
            app(EnterSiteFolder::class)->handle($request, fn () => throw new RuntimeException('boom'));
            $this->fail('The exception must go on.');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame('http://main.test/x', url('x'));
        $this->assertSame('https://cdn.test/a.css', asset('a.css'));
        $this->assertSame('', url()->getRequest()->getBaseUrl());
    }
}
