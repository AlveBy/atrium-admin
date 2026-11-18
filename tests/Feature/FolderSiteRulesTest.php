<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a folder may be, which addresses it may not take, and that a request
 * into a folder leaves nothing behind for the next one.
 */
final class FolderSiteRulesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://main.test');
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->get('assets', fn () => asset('app.css').' '.url('page'));
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function site(string $code, array $attributes = []): Site
    {
        return Site::query()->create(['code' => $code, 'name' => ucfirst($code), ...$attributes]);
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    /**
     * The message the save stops with, on the field it names.
     *
     * @param  array<string,mixed>  $attributes
     * @return array<string,list<string>>
     */
    private function refusal(array $attributes, ?Site $site = null): array
    {
        try {
            $site === null ? $this->site('other', $attributes) : $site->update($attributes);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Saved: '.json_encode($attributes));
    }

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function shapes(): array
    {
        return [
            'letters' => ['site', true],
            'digits' => ['2026', true],
            'mixed case' => ['Site2', true],
            'dash and underscore inside' => ['my-site_2', true],
            'nested' => ['a/b/c', true],
            'one character' => ['a', true],
            'longest allowed' => [str_repeat('a', 255), true],
            'one too long' => [str_repeat('a', 256), false],
            'nested, one too long' => [str_repeat('a', 127).'/'.str_repeat('b', 128), false],
            'a space' => ['my site', false],
            'starts with a dash' => ['-site', false],
            'starts with an underscore' => ['_site', false],
            'a part starts with a dash' => ['a/-b', false],
            'a dot' => ['site.html', false],
            'dots up' => ['../site', false],
            'Cyrillic' => ['сайт', false],
            'Chinese' => ['站点', false],
            'emoji' => ['site🪑', false],
            'HTML' => ['<b>x</b>', false],
            'a percent sign' => ['a%20b', false],
            'a trailing letter after a newline' => ["site\nx", false],
        ];
    }

    #[DataProvider('shapes')]
    public function test_a_folder_is_latin_letters_digits_dashes_and_underscores(string $folder, bool $valid): void
    {
        if ($valid) {
            $this->assertSame($folder, $this->site('second', ['folder' => $folder])->fresh()->folder);

            return;
        }

        $this->assertSame(
            ['folder' => [__('atrium::sites.errors.folder_invalid', ['folder' => $folder])]],
            $this->refusal(['folder' => $folder]),
        );
        $this->assertFalse(Site::query()->where('code', 'other')->exists());
    }

    /**
     * @return array<string,array{0:string,1:string|null}>
     */
    public static function reserved(): array
    {
        return [
            'the admin' => ['admin', 'admin'],
            'the admin, in capitals' => ['Admin', 'admin'],
            'inside the admin' => ['admin/x', 'admin'],
            'the API' => ['api', 'api'],
            'a version of the API' => ['api/v2', 'api'],
            'MCP' => ['mcp', 'mcp'],
            'MCP in capitals' => ['MCP/x', 'mcp'],
            'a longer word' => ['administration', null],
            'the word later' => ['x/admin', null],
            'apis' => ['apis', null],
        ];
    }

    #[DataProvider('reserved')]
    public function test_the_panels_own_addresses_are_not_folders(string $folder, ?string $taken): void
    {
        if ($taken === null) {
            $this->assertSame($folder, $this->site('second', ['folder' => $folder])->folder);

            return;
        }

        $this->assertSame(
            ['folder' => [__('atrium::sites.errors.folder_reserved', ['folder' => $taken])]],
            $this->refusal(['folder' => $folder]),
        );
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function prefixes(): array
    {
        return [
            'the admin elsewhere' => ['atrium.prefix', '/Panel/x/', 'panel'],
            'the API elsewhere' => ['atrium.api.prefix', '/REST/v2/', 'rest'],
            'MCP elsewhere' => ['atrium.mcp.path', '/AI/', 'ai'],
        ];
    }

    #[DataProvider('prefixes')]
    public function test_the_reserved_folders_follow_the_configuration(string $key, string $prefix, string $taken): void
    {
        config([$key => $prefix]);

        $this->assertSame(
            ['folder' => [__('atrium::sites.errors.folder_reserved', ['folder' => $taken])]],
            $this->refusal(['folder' => $taken.'/x']),
        );
    }

    public function test_the_default_site_stays_at_the_root(): void
    {
        $this->assertSame(
            ['folder' => [__('atrium::sites.errors.folder_default')]],
            $this->refusal(['folder' => 'main'], $this->main()),
        );
        $this->assertNull($this->main()->fresh()->folder);

        // Clearing the folder of the default site is fine.
        $this->main()->update(['folder' => '']);
        $this->assertNull($this->main()->fresh()->folder);
    }

    public function test_one_address_belongs_to_one_site(): void
    {
        $this->main()->update(['domains' => ['example.com']]);
        $this->site('a', ['domains' => ['example.com', 'example.org'], 'folder' => 'a']);
        $this->site('anywhere', ['folder' => 'c']);

        // The same folder on a domain of that site.
        $this->assertSame(
            ['folder' => [__('atrium::sites.errors.folder_taken', ['address' => 'example.org/a/', 'site' => 'A'])]],
            $this->refusal(['domains' => ['example.net', 'example.org'], 'folder' => 'a']),
        );

        // Two sites in one folder on every domain.
        $this->assertSame(
            ['folder' => [__('atrium::sites.errors.folder_taken', ['address' => '/c/', 'site' => 'Anywhere'])]],
            $this->refusal(['folder' => 'c']),
        );

        // The root of a domain.
        $this->assertSame(
            ['domains' => [__('atrium::sites.errors.domain_taken', ['domain' => 'example.com', 'site' => $this->main()->name])]],
            $this->refusal(['domains' => ['example.com']]),
        );

        // Fine: another folder, the same folder elsewhere, a folder next to
        // a site on every domain, the root of another domain, a site saved again.
        $this->site('b', ['domains' => ['example.com'], 'folder' => 'b']);
        $this->site('a2', ['domains' => ['example.net'], 'folder' => 'a']);
        $this->site('c2', ['domains' => ['example.com'], 'folder' => 'c']);
        $this->site('root', ['domains' => ['example.net']]);
        $this->site('a3', ['folder' => 'a']);
        Site::query()->where('code', 'a')->sole()->update(['name' => 'A again']);

        $this->assertSame(8, Site::query()->count());
    }

    public function test_the_address_of_a_site_is_inside_its_folder(): void
    {
        $this->get('http://example.com/');
        $site = new Site(['domains' => ['example.com'], 'folder' => 'site2']);

        $this->assertSame('http://example.com/site2', $site->url());
        $this->assertSame('http://example.com/site2/news', $site->url('news'));
        $this->assertSame('http://example.com/site2/news', $site->url('/news/'));
        $this->assertSame('http://example.com/news', (new Site(['domains' => ['example.com']]))->url('/news/'));
        $this->assertSame('http://example.com', (new Site(['domains' => ['example.com']]))->url('/'));
    }

    public function test_the_panel_stays_out_of_a_folder_whatever_slashes_its_prefix_has(): void
    {
        $this->site('second', ['folder' => 'site2']);
        config(['atrium.prefix' => '/admin/']);

        $this->get('http://main.test/site2/admin/login')->assertNotFound();
        $this->get('http://main.test/admin/login')->assertOk();
    }

    public function test_a_folder_request_leaves_nothing_behind(): void
    {
        $this->site('second', ['folder' => 'site2']);

        $this->get('http://main.test/site2/assets')->assertSeeText('http://main.test/app.css http://main.test/site2/page');
        // Another host next: its own root, not the one of the folder request.
        $this->get('http://other.test/assets')->assertSeeText('http://other.test/app.css http://other.test/page');
    }

    public function test_assets_keep_their_own_origin_inside_a_folder(): void
    {
        $this->site('second', ['folder' => 'site2']);
        $this->app['url']->useAssetOrigin('https://static.example.com');

        $this->get('http://main.test/site2/assets')->assertSeeText('https://static.example.com/app.css http://main.test/site2/page');
        $this->get('http://main.test/assets')->assertSeeText('https://static.example.com/app.css http://main.test/page');
    }
}
