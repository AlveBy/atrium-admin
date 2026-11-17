<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Site model in exact form: its tabs, fields, saved addresses, the url,
 * the robots.txt text and the messages of the checks.
 */
final class SiteExactnessTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.url', 'http://main.test');
    }

    private function main(): Site
    {
        return Site::query()->where('code', 'main')->sole();
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function site(string $code, array $attributes = []): Site
    {
        return Site::query()->create(['code' => $code, 'name' => ucfirst($code), ...$attributes]);
    }

    /**
     * @return array<string,Field>
     */
    private function fields(): array
    {
        $fields = [];

        foreach (Site::getAdministrationFields() as $field) {
            $fields[$field->getName().($field->shouldShowOnCreate() && ! $field->shouldShowOnEdit() ? ':create' : '')] = $field;
        }

        return $fields;
    }

    public function test_the_tabs_come_in_this_order_with_these_numbers(): void
    {
        $this->assertSame([
            ['name' => 'params', 'label' => __('atrium::sites.tabs.site'), 'icon' => null, 'order' => 0, 'visible' => true, 'badge' => null],
            ['name' => 'codes', 'label' => __('atrium::sites.tabs.codes'), 'icon' => null, 'order' => 10, 'visible' => true, 'badge' => null],
            ['name' => 'robots', 'label' => 'robots.txt', 'icon' => null, 'order' => 20, 'visible' => true, 'badge' => null],
        ], array_map(fn ($tab) => $tab->toArray(), Site::getAdminTabs()));
    }

    public function test_the_fields_come_in_this_order(): void
    {
        $this->assertSame([
            'name', 'code:create', 'code', 'domains', 'folder', 'languages', 'default_language', 'active', 'is_default', 'sort',
            'google_tag_id', 'yandex_metrika_id', 'yandex_webvisor', 'counters_skip_admins',
            'google_verification', 'yandex_verification', 'bing_verification',
            'head_code', 'body_start_code', 'body_end_code', 'robots_txt',
        ], array_keys($this->fields()));
    }

    public function test_the_site_fields_hold_their_limits_and_defaults(): void
    {
        $fields = $this->fields();

        $this->assertInstanceOf(TextField::class, $fields['name']);
        $this->assertTrue($fields['name']->isRequired());
        $this->assertSame(255, $fields['name']->getMaxLength());
        $this->assertSame(255, $fields['folder']->getMaxLength());
        $this->assertSame('site2', $fields['folder']->toArray()['placeholder']);

        $this->assertTrue($fields['active']->getDefault());
        $this->assertFalse($fields['is_default']->getDefault());
        $this->assertSame(500, $fields['sort']->getDefault());

        $rules = $fields['code:create']->getValidationRules();
        $this->assertContains('string', $rules);
        $this->assertContains('max:32', $rules);
        $this->assertContains('regex:/^'.Site::CODE_PATTERN.'$/', $rules);
    }

    public function test_every_field_of_the_codes_tab_sits_on_it_and_the_rest_on_params(): void
    {
        foreach ($this->fields() as $name => $field) {
            $expected = match (true) {
                in_array($name, ['google_tag_id', 'yandex_metrika_id', 'yandex_webvisor', 'counters_skip_admins', 'google_verification', 'yandex_verification', 'bing_verification', 'head_code', 'body_start_code', 'body_end_code'], true) => 'codes',
                $name === 'robots_txt' => 'robots',
                default => 'params',
            };

            $this->assertSame($expected, $field->getTab(), $name);
        }
    }

    public function test_the_code_fields_have_their_defaults_rows_and_rules(): void
    {
        $fields = $this->fields();

        $this->assertFalse($fields['yandex_webvisor']->getDefault());
        $this->assertTrue($fields['counters_skip_admins']->getDefault());

        foreach (['head_code', 'body_start_code', 'body_end_code'] as $name) {
            $this->assertInstanceOf(TextareaField::class, $fields[$name]);
            $this->assertSame(6, $fields[$name]->toArray()['rows'], $name);
        }

        foreach (['google_tag_id' => 32, 'yandex_metrika_id' => 20, 'google_verification' => 1000, 'yandex_verification' => 1000, 'bing_verification' => 1000] as $name => $max) {
            $rules = $fields[$name]->getValidationRules();
            $this->assertContains('string', $rules, $name);
            $this->assertContains('max:'.$max, $rules, $name);
        }
    }

    public function test_the_verification_hints_name_the_meta_tag(): void
    {
        foreach (['google_verification' => 'google-site-verification', 'yandex_verification' => 'yandex-verification', 'bing_verification' => 'msvalidate.01'] as $name => $meta) {
            $this->assertSame(__('atrium::sites.hints.verification', ['name' => $meta]), $this->fields()[$name]->toArray()['hint']);
            $this->assertStringContainsString($meta, $this->fields()[$name]->toArray()['hint']);
        }
    }

    public function test_the_robots_field_is_there_only_when_the_option_is_on(): void
    {
        $this->assertSame('robots_txt', array_key_last($this->fields()));

        config(['atrium.robots' => false]);

        $this->assertArrayNotHasKey('robots_txt', $this->fields());
        $this->assertSame('body_end_code', array_key_last($this->fields()));
    }

    public function test_the_robots_hint_says_what_stops_the_text_from_being_served(): void
    {
        $file = public_path('robots.txt');
        $environment = $this->app['env'];
        @unlink($file);

        try {
            $this->app['env'] = 'production';
            $plain = $this->fields()['robots_txt']->toArray();
            $this->assertSame(__('atrium::sites.hints.robots_txt'), $plain['hint']);
            $this->assertSame(12, $plain['rows']);

            $this->app['env'] = 'staging';
            $this->assertSame(
                __('atrium::sites.hints.robots_txt').' '.__('atrium::sites.hints.robots_not_production', ['env' => 'staging']),
                $this->fields()['robots_txt']->toArray()['hint'],
            );

            $this->app['env'] = 'production';
            file_put_contents($file, "User-agent: *\n");
            $this->assertSame(
                __('atrium::sites.hints.robots_txt').' '.__('atrium::sites.hints.robots_file_exists'),
                $this->fields()['robots_txt']->toArray()['hint'],
            );
        } finally {
            $this->app['env'] = $environment;
            @unlink($file);
        }
    }

    /**
     * Fill the three caches, change what they hold behind the model's back,
     * and see what each of them answers after $change.
     *
     * @return array{sites:string,languages:string,sitemap:string}
     */
    private function afterChange(callable $change): array
    {
        app(Sites::class)->all();
        app(ContentLanguages::class)->all();
        $before = Cache::get('atrium.sitemap.version');
        DB::table('admin_sites')->where('code', 'main')->update(['name' => 'Changed']);
        DB::table('admin_languages')->where('code', 'en')->update(['name' => 'Changed']);

        $change();

        return [
            'sites' => (string) app(Sites::class)->all()->firstWhere('code', 'main')?->name,
            'languages' => (string) app(ContentLanguages::class)->all()->first()?->name,
            'sitemap' => $before === Cache::get('atrium.sitemap.version') ? 'stale' : 'fresh',
        ];
    }

    public function test_saving_a_site_flushes_the_three_caches(): void
    {
        $this->assertSame(
            ['sites' => 'Changed', 'languages' => 'Changed', 'sitemap' => 'fresh'],
            $this->afterChange(fn () => $this->site('other')),
        );
    }

    public function test_deleting_a_site_flushes_the_three_caches(): void
    {
        $site = $this->site('other');

        $this->assertSame(
            ['sites' => 'Changed', 'languages' => 'Changed', 'sitemap' => 'fresh'],
            $this->afterChange(fn () => $site->delete()),
        );
    }

    public function test_the_code_of_a_saved_site_cannot_be_changed(): void
    {
        $site = $this->site('other');
        $site->code = 'renamed';
        $site->save();

        $this->assertSame('other', $site->fresh()->code);
        $this->assertSame('other', $site->code);
    }

    /**
     * @return array<string,array{0:string,1:int|null,2:string}>
     */
    public static function addresses(): array
    {
        return [
            'https on 443' => ['https', 443, 'https://example.de'],
            'http on 80' => ['http', 80, 'http://example.de'],
            'https on 8443' => ['https', 8443, 'https://example.de:8443'],
            'http on 8080' => ['http', 8080, 'http://example.de:8080'],
            'http on 443' => ['http', 443, 'http://example.de:443'],
            'https on 80' => ['https', 80, 'https://example.de:80'],
            'no port at all' => ['http', null, 'http://example.de'],
        ];
    }

    #[DataProvider('addresses')]
    public function test_a_site_of_another_domain_is_opened_on_the_port_of_the_request(string $scheme, ?int $port, string $root): void
    {
        $site = $this->site('de', ['domains' => ['example.de']]);

        $server = ['SERVER_NAME' => 'panel.test', 'HTTPS' => $scheme === 'https' ? 'on' : 'off'];

        if ($port !== null) {
            $server['SERVER_PORT'] = $port;
            $server['HTTP_HOST'] = 'panel.test:'.$port;
        }

        $this->app->instance('request', new Request([], [], [], [], [], $server));

        $this->assertSame($root, $site->url());
        $this->assertSame($root.'/blog/post', $site->url('/blog/post/'));
    }

    public function test_the_address_of_a_page_sits_inside_the_folder_of_the_site(): void
    {
        $this->app->instance('request', Request::create('http://main.test/'));
        $site = $this->site('folder', ['folder' => 'f']);

        $this->assertSame('http://main.test/f', $site->url());
        $this->assertSame('http://main.test/f/blog', $site->url('/blog/'));
        $this->assertSame('http://main.test/f/a/b', $site->url('a/b'));
        $this->assertSame('http://main.test/blog', $this->main()->url('/blog/'));
        $this->assertSame('http://main.test', $this->main()->url());
    }

    public function test_the_site_of_this_domain_uses_the_root_of_the_request(): void
    {
        $this->app->instance('request', Request::create('http://example.de:8080/'));
        $site = $this->site('de', ['domains' => ['example.de']]);

        $this->assertSame('http://example.de:8080/x', $site->url('x'));
    }

    public function test_robots_txt_is_trimmed_and_gets_the_sitemap_when_it_has_none(): void
    {
        $this->app['env'] = 'production';
        $site = $this->main();

        $site->update(['robots_txt' => "\r\n  User-agent: *\r\nDisallow: /x\r\n\r\n"]);
        $this->assertSame("User-agent: *\nDisallow: /x\n\nSitemap: ".url('sitemap.xml')."\n", $site->robotsTxt());

        // "sitemap:" in the middle of a line is not a Sitemap line.
        $site->update(['robots_txt' => 'Disallow: /sitemap: x']);
        $this->assertSame("Disallow: /sitemap: x\n\nSitemap: ".url('sitemap.xml')."\n", $site->robotsTxt());

        $site->update(['robots_txt' => "User-agent: *\n  SITEMAP: https://cdn.test/map.xml"]);
        $this->assertSame("User-agent: *\n  SITEMAP: https://cdn.test/map.xml\n", $site->robotsTxt());

        // The sites in folders of this domain add their own sitemaps.
        $site->update(['robots_txt' => 'Disallow: /sitemap: x']);
        $this->assertSame("Disallow: /sitemap: x\n\nSitemap: ".url('sitemap.xml')."\nSitemap: ".url('f/sitemap.xml')."\n", $site->robotsTxt([new Site(['folder' => 'f'])]));
    }

    public function test_a_host_is_matched_without_regard_to_case_and_trailing_dot(): void
    {
        $site = $this->site('de', ['domains' => ['example.de']]);

        $this->assertTrue($site->hasDomain('EXAMPLE.DE'));
        $this->assertTrue($site->hasDomain('Example.de.'));
        $this->assertFalse($site->hasDomain('example.com'));
    }

    public function test_domains_are_cleaned_of_scheme_path_port_case_and_the_trailing_dot(): void
    {
        $site = $this->site('de', ['domains' => [' HTTPS://Example.DE:8080/path?x=1 ', 'example.de.', 'Other.test.', 'a.test/b', 'c.test?x', 'd.test#frag', '']]);

        $this->assertSame(['example.de', 'other.test', 'a.test', 'c.test', 'd.test'], $site->domains);
    }

    public function test_a_folder_is_cleaned_of_scheme_host_query_and_surplus_slashes(): void
    {
        $this->assertSame('a/b', Site::normalizeFolder(' HTTP://Host.test//a///b/?x=1#frag '));
        $this->assertSame('a', Site::normalizeFolder('a#frag'));
        $this->assertSame('a', Site::normalizeFolder('/a?x'));
        $this->assertNull(Site::normalizeFolder('https://host.test'));
        $this->assertNull(Site::normalizeFolder(null));
        $this->assertNull(Site::normalizeFolder(' / '));
    }

    public function test_the_panel_prefixes_are_reserved_whatever_their_slashes_and_case(): void
    {
        $site = $this->site('other');

        foreach (['atrium.prefix' => ['/Panel/x/', 'panel'], 'atrium.api.prefix' => ['/Rest/v2/', 'rest'], 'atrium.mcp.path' => ['/Tools/', 'tools']] as $key => [$configured, $folder]) {
            config([$key => $configured]);

            foreach ([$folder, strtoupper($folder).'/sub'] as $try) {
                try {
                    $site->update(['folder' => $try]);
                    $this->fail("The folder [{$try}] was accepted.");
                } catch (ValidationException $e) {
                    $this->assertSame([__('atrium::sites.errors.folder_reserved', ['folder' => strtolower(strtok($try, '/'))])], $e->errors()['folder'], $try);
                }
            }
        }

        // And a folder that is nobody's prefix passes.
        $site->update(['folder' => 'plain']);
        $this->assertSame('plain', $site->fresh()->folder);
    }

    public function test_the_message_for_a_bad_domain_names_it(): void
    {
        try {
            $this->site('bad', ['domains' => ['bad domain']]);
            $this->fail('A bad domain was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(['domains' => [__('atrium::sites.errors.domain_invalid', ['domain' => 'bad domain'])]], $e->errors());
        }
    }

    public function test_the_message_for_a_taken_domain_names_the_domain_and_the_site(): void
    {
        $this->site('de', ['domains' => ['example.de'], 'name' => 'Deutschland']);

        try {
            $this->site('again', ['domains' => ['other.test', 'example.de']]);
            $this->fail('A taken domain was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(['domains' => [__('atrium::sites.errors.domain_taken', ['domain' => 'example.de', 'site' => 'Deutschland'])]], $e->errors());
        }
    }

    public function test_the_message_for_a_taken_folder_gives_the_address_and_the_site(): void
    {
        $this->site('a', ['domains' => ['example.de'], 'folder' => 'f', 'name' => 'Alpha']);
        $this->site('b', ['folder' => 'g', 'name' => 'Beta']);

        try {
            $this->site('again', ['domains' => ['example.de'], 'folder' => 'f']);
            $this->fail('A taken address was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(['folder' => [__('atrium::sites.errors.folder_taken', ['address' => 'example.de/f/', 'site' => 'Alpha'])]], $e->errors());
        }

        // Two sites without domains share every domain, so a folder is theirs alone.
        try {
            $this->site('third', ['folder' => 'g']);
            $this->fail('A taken folder was accepted.');
        } catch (ValidationException $e) {
            $this->assertSame(['folder' => [__('atrium::sites.errors.folder_taken', ['address' => '/g/', 'site' => 'Beta'])]], $e->errors());
        }
    }

    public function test_a_row_with_no_domains_at_all_still_takes_its_folder(): void
    {
        DB::table('admin_sites')->insert(['code' => 'raw', 'name' => 'Raw', 'folder' => 'r', 'domains' => null, 'active' => true, 'is_default' => false, 'sort' => 500, 'created_at' => now(), 'updated_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->site('again', ['folder' => 'r']);
    }
}
