<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit;

use Atrium\Admin\Http\Middleware\EnterSiteFolder;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The folder of a site as an editor types it, the paths it holds and the
 * root of the application seen from inside it.
 */
final class SiteFolderTest extends TestCase
{
    /**
     * @return array<string,array{0:mixed,1:string|null}>
     */
    public static function folders(): array
    {
        return [
            'plain' => ['site2', 'site2'],
            'slashes around' => ['/site2/', 'site2'],
            'spaces around' => ['  site2  ', 'site2'],
            'nested' => ['/a/b/', 'a/b'],
            'doubled slashes' => ['//a///b//', 'a/b'],
            'a whole address' => ['https://Example.com/site2/', 'site2'],
            'an address with a port' => ['http://example.com:8080/site2', 'site2'],
            'another scheme' => ['ftp://example.com/files', 'files'],
            'a query' => ['site2?page=2', 'site2'],
            'a fragment' => ['site2#top', 'site2'],
            'a query after a slash' => ['/site2/?a=1#b', 'site2'],
            'a colon inside, not a scheme' => ['site2/a:b', 'site2/a:b'],
            'a scheme later in the path' => ['site2/http://x', 'site2/http:/x'],
            'a domain only' => ['https://example.com', null],
            'a domain and a slash' => ['https://example.com/', null],
            'empty' => ['', null],
            'null' => [null, null],
            'only spaces' => ['   ', null],
            'only a slash' => ['/', null],
            'only slashes' => ['///', null],
            'only a query' => ['?a=1', null],
            'Cyrillic is kept for the validator' => ['/сайт/', 'сайт'],
            'HTML is kept for the validator' => ['<b>x</b>', '<b>x</b>'],
        ];
    }

    #[DataProvider('folders')]
    public function test_a_folder_is_normalized(mixed $typed, ?string $folder): void
    {
        $this->assertSame($folder, Site::normalizeFolder($typed));
    }

    /**
     * @return array<string,array{0:string|null,1:string,2:bool}>
     */
    public static function paths(): array
    {
        return [
            'the folder itself' => ['site2', '/site2', true],
            'the folder with a slash' => ['site2', '/site2/', true],
            'a page inside' => ['site2', '/site2/news/one', true],
            'without a leading slash' => ['site2', 'site2/news', true],
            'a longer name' => ['site2', '/site22', false],
            'a longer name inside' => ['site2', '/site22/news', false],
            'a shorter name' => ['site2', '/site', false],
            'the root' => ['site2', '/', false],
            'an empty path' => ['site2', '', false],
            'the folder deeper' => ['site2', '/de/site2', false],
            'another case' => ['site2', '/Site2', false],
            'a nested folder' => ['a/b', '/a/b/c', true],
            'half of a nested folder' => ['a/b', '/a', false],
            'no folder, the root' => [null, '/', false],
            'an empty folder, the root' => ['', '/', false],
            'an empty folder, a page' => ['', '/news', false],
            'no folder, a page' => [null, '/news', false],
        ];
    }

    #[DataProvider('paths')]
    public function test_a_site_holds_the_paths_inside_its_folder(?string $folder, string $path, bool $holds): void
    {
        $this->assertSame($holds, (new Site(['folder' => $folder]))->holdsPath($path));
    }

    /**
     * @return array<string,array{0:string|null,1:string|null,2:string}>
     */
    public static function roots(): array
    {
        return [
            'no folder' => [null, null, 'http://example.com'],
            'moved into the folder' => ['/site2', 'site2', 'http://example.com'],
            'a nested folder' => ['/a/b', 'a/b', 'http://example.com'],
            'installed in a subfolder itself' => ['/app/site2', 'site2', 'http://example.com/app'],
            'a folder, not moved' => [null, 'site2', 'http://example.com'],
            'the root only ends like the folder' => ['/mysite2', 'site2', 'http://example.com/mysite2'],
            'another folder in the root' => ['/site3', 'site2', 'http://example.com/site3'],
        ];
    }

    #[DataProvider('roots')]
    public function test_the_root_url_leaves_the_folder_out(?string $baseUrl, ?string $folder, string $root): void
    {
        $request = Request::create('http://example.com/news');

        if ($baseUrl !== null) {
            // What EnterSiteFolder leaves: the folder in the base URL.
            (function (string $baseUrl): void {
                $this->baseUrl = $baseUrl;
            })->call($request, $baseUrl);
        }

        if ($folder !== null) {
            $request->attributes->set(EnterSiteFolder::FOLDER, $folder);
        }

        $this->assertSame($root, Sites::rootUrl($request));
    }

    public function test_the_root_url_keeps_its_port(): void
    {
        $request = Request::create('http://example.com:8000/site2/news');
        (function (): void {
            $this->baseUrl = '/site2';
        })->call($request);
        $request->attributes->set(EnterSiteFolder::FOLDER, 'site2');

        $this->assertSame('http://example.com:8000', Sites::rootUrl($request));
    }
}
