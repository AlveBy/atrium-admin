<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Templates\RecordTemplate;
use Atrium\Admin\Templates\Template;
use Atrium\Admin\Templates\Templates;
use Atrium\Admin\Tests\Fixtures\Flyer;
use Atrium\Admin\Tests\Fixtures\Modules\Label;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Tabbed;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\View\ViewFinderInterface;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * The template an editor picks for a record of a module with a public page:
 * found as Blade files, offered in the form, kept per record.
 */
final class TemplatesTest extends TestCase
{
    private string $views;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.public_urls', [Post::class => ['prefix' => 'posts']]);
        $app->resolving(Modules::class, static fn (Modules $modules) => $modules->add(Label::module()));
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->views = sys_get_temp_dir().'/atrium-templates-'.uniqid();
        mkdir($this->views, 0777, true);
        view()->addLocation($this->views);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->views);

        parent::tearDown();
    }

    private function templates(): Templates
    {
        return $this->app->make(Templates::class);
    }

    private function template(string $path, string $contents = '{{-- Template: Wide --}}'): void
    {
        $file = $this->views.'/'.$path.'.blade.php';
        is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
        file_put_contents($file, $contents);
        $this->templates()->forget();
    }

    /**
     * @return list<string>
     */
    private function formFields(string $url = '/admin/posts/create'): array
    {
        $names = [];

        $this->actingAs($this->admin(), 'admin')->get($url)->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$names): void {
                $names = array_column($page->toArray()['props']['fields'], 'name');
            });

        return $names;
    }

    // Finding the templates

    public function test_the_files_of_the_modules_folder_and_the_shared_one_are_the_templates(): void
    {
        $this->template('templates/posts/wide');
        $this->template('templates/all/full', '{{-- Template: Full --}}');
        $this->template('templates/news/other');

        $this->assertSame(['wide', 'full'], array_keys($this->templates()->available(Post::class)));
        $this->assertSame('templates.posts.wide', $this->templates()->available(Post::class)['wide']->view);
        $this->assertSame('templates.all.full', $this->templates()->available(Post::class)['full']->view);
        $this->assertInstanceOf(Template::class, $this->templates()->available(Post::class)['wide']);
    }

    public function test_a_module_without_a_template_has_none(): void
    {
        $this->assertSame([], $this->templates()->available(Post::class));
        $this->assertFalse($this->templates()->offers(Post::class));
    }

    public function test_the_folder_is_named_by_the_module_key_or_else_by_the_table(): void
    {
        $this->assertSame('posts', $this->templates()->module(Post::class));
        $this->assertSame('labels', $this->templates()->module(Label::class));
    }

    public function test_the_folder_of_a_module_package_is_its_key_not_the_table_of_its_model(): void
    {
        $folder = $this->views.'/gallery-module';
        mkdir($folder, 0777, true);
        file_put_contents($folder.'/composer.json', json_encode([
            'name' => 'acme/atrium-gallery',
            'type' => 'atrium-module',
            'extra' => ['atrium' => ['resources' => [Tabbed::class]]],
        ]));
        $this->app->make(Modules::class)->add(Module::fromPath($folder) ?? throw new \RuntimeException('No module.'));
        $this->template('templates/gallery/wide');
        $this->template('templates/posts/other');

        $this->assertSame('gallery', $this->templates()->module(Tabbed::class));
        $this->assertSame(['wide'], array_keys($this->templates()->available(Tabbed::class)));
    }

    public function test_a_file_named_by_digits_is_a_template_too(): void
    {
        $this->template('templates/posts/404', '{{-- Template: Not found --}}');

        $template = $this->templates()->available(Post::class)[404];

        $this->assertSame('404', $template->key);
        $this->assertSame('templates.posts.404', $template->view);
        $this->assertSame(['default' => 'Default', '404' => 'Not found'], $this->templates()->options(Post::class));
    }

    public function test_the_names_of_every_place_the_views_are_in_come_in_one_order(): void
    {
        $second = $this->views.'/second';
        mkdir($second.'/templates/posts', 0777, true);
        file_put_contents($second.'/templates/posts/alpha.blade.php', '{{-- Template: Alpha --}}');
        view()->addLocation($second);
        $this->template('templates/posts/zeta');
        $this->template('templates/posts/alpha');

        $this->assertSame(['alpha', 'zeta'], array_keys($this->templates()->available(Post::class)));
    }

    public function test_names_that_are_not_view_names_and_the_default_are_left_out(): void
    {
        $this->template('templates/posts/with.dot');
        $this->template('templates/posts/with space');
        $this->template('templates/posts/default');
        $this->template('templates/posts/ok_1-b');

        $this->assertSame(['ok_1-b'], array_keys($this->templates()->available(Post::class)));
        $this->assertNull($this->templates()->defaultView(Post::class));
    }

    public function test_a_file_that_is_not_blade_is_not_a_template(): void
    {
        mkdir($this->views.'/templates/posts', 0777, true);
        file_put_contents($this->views.'/templates/posts/plain.php', 'x');
        file_put_contents($this->views.'/templates/posts/notes.txt', 'x');

        $this->assertSame([], $this->templates()->available(Post::class));
    }

    public function test_the_same_name_in_the_modules_folder_wins_over_the_shared_one(): void
    {
        $this->template('templates/all/wide', '{{-- Template: Shared --}}');
        $this->template('templates/posts/wide', '{{-- Template: Own --}}');

        $this->assertSame('Own', $this->templates()->available(Post::class)['wide']->label);
        $this->assertSame('templates.posts.wide', $this->templates()->available(Post::class)['wide']->view);
    }

    public function test_the_sites_views_come_before_a_theme_s_and_a_skin_s_and_they_come_in_order(): void
    {
        $theme = $this->views.'/theme';
        $skin = $theme.'/skins/shop';
        mkdir($theme.'/templates/posts', 0777, true);
        mkdir($skin.'/templates/posts', 0777, true);
        file_put_contents($theme.'/templates/posts/wide.blade.php', '{{-- Template: Theme --}}');
        file_put_contents($theme.'/templates/posts/theme-only.blade.php', '{{-- Template: Theme only --}}');
        file_put_contents($skin.'/templates/posts/wide.blade.php', '{{-- Template: Skin --}}');
        file_put_contents($skin.'/templates/posts/skin-only.blade.php', '{{-- Template: Skin only --}}');
        view()->addNamespace('ac', $theme);
        $this->templates()->roots(static fn (): array => ['ac::skins.shop.', 'ac::']);
        $this->template('templates/posts/site-only', '{{-- Template: Site only --}}');

        $templates = $this->templates()->available(Post::class);

        $this->assertSame(['site-only', 'skin-only', 'wide', 'theme-only'], array_keys($templates));
        $this->assertSame('ac::skins.shop.templates.posts.wide', $templates['wide']->view);
        $this->assertSame('Skin', $templates['wide']->label);

        $this->template('templates/posts/wide', '{{-- Template: Site --}}');
        $this->assertSame('templates.posts.wide', $this->templates()->available(Post::class)['wide']->view);
    }

    public function test_a_root_without_the_folder_or_with_an_unknown_namespace_adds_nothing(): void
    {
        $this->templates()->roots(static fn (): array => ['nowhere::', 'nowhere::skins.x.']);
        $this->template('templates/posts/wide');

        $this->assertSame(['wide'], array_keys($this->templates()->available(Post::class)));
    }

    public function test_the_templates_are_looked_for_again_after_forget(): void
    {
        $this->template('templates/posts/wide');
        $this->assertSame(['wide'], array_keys($this->templates()->available(Post::class)));

        file_put_contents($this->views.'/templates/posts/second.blade.php', '{{-- Template: Second --}}');
        $this->assertSame(['wide'], array_keys($this->templates()->available(Post::class)));

        $this->templates()->forget();
        $this->assertSame(['second', 'wide'], array_keys($this->templates()->available(Post::class)));
    }

    // Names

    public function test_the_name_is_the_comment_at_the_top_of_the_file(): void
    {
        $this->template('templates/posts/a', "{{-- Template: Full width --}}\n<p>x</p>");
        $this->template('templates/posts/b', "{{--\n    This file is part of Atrium.\n--}}\n{{--   Template:   Wide page   --}}\n<p>x</p>");
        $this->template('templates/posts/c', "{{-- Just a comment --}}\n{{-- Template: After another --}}");
        $this->template('templates/posts/d', "<p>x</p>\n{{-- Template: Too late --}}");
        $this->template('templates/posts/e', '{{-- Template: --}}');
        $this->template('templates/posts/f', '{{-- Template: Multi word - and "quotes" --}}');
        $this->template('templates/posts/my-landing_page', '<p>x</p>');

        $labels = array_map(static fn (Template $template): string => $template->label, $this->templates()->available(Post::class));

        $this->assertSame([
            'a' => 'Full width',
            'b' => 'Wide page',
            'c' => 'After another',
            'd' => 'D',
            'e' => 'E',
            'f' => 'Multi word - and "quotes"',
            'my-landing_page' => 'My Landing Page',
        ], $labels);
    }

    public function test_the_name_may_be_a_translation_key_or_a_text_of_the_sites_translations(): void
    {
        app('translator')->setLoaded(['*' => ['*' => ['en' => ['Wide page' => 'Wide, translated']]]]);
        app('translator')->addLines(['templates.nice' => 'Nice one'], 'en', 'atrium');
        $this->template('templates/posts/a', '{{-- Template: atrium::templates.nice --}}');
        $this->template('templates/posts/b', '{{-- Template: Wide page --}}');
        $this->template('templates/posts/c', '{{-- Template: atrium::templates.none --}}');

        $labels = array_map(static fn (Template $template): string => $template->label, $this->templates()->available(Post::class));

        $this->assertSame(['a' => 'Nice one', 'b' => 'Wide, translated', 'c' => 'atrium::templates.none'], $labels);
    }

    public function test_a_name_that_is_the_key_of_a_group_of_translations_stays_as_it_is(): void
    {
        $this->template('templates/posts/a', '{{-- Template: atrium::templates --}}');

        $this->assertSame('atrium::templates', $this->templates()->available(Post::class)['a']->label);
    }

    public function test_a_template_whose_view_is_not_found_is_named_by_its_key(): void
    {
        config(['atrium.templates.posts' => ['gone' => 'nowhere.gone']]);

        $this->assertSame('Gone', $this->templates()->available(Post::class)['gone']->label);
    }

    public function test_the_default_comes_first_in_what_the_editor_picks_from(): void
    {
        $this->template('templates/posts/wide', '{{-- Template: Wide --}}');
        $this->template('templates/posts/another', '{{-- Template: Another --}}');

        $this->assertSame(['default' => 'Default', 'another' => 'Another', 'wide' => 'Wide'], $this->templates()->options(Post::class));
    }

    // Packages and the config

    public function test_a_package_adds_templates_in_code_and_the_default_view(): void
    {
        $this->templates()->source(Post::class, static fn (): array => ['default' => 'pkg.default', 'plain' => 'pkg.plain', 'broken' => ['x']]);
        $this->template('templates/posts/wide');

        $this->assertSame(['plain', 'wide'], array_keys($this->templates()->available(Post::class)));
        $this->assertSame('pkg.default', $this->templates()->defaultView(Post::class));
        $this->assertTrue($this->templates()->offers(Post::class));
    }

    public function test_the_config_adds_replaces_and_hides_templates(): void
    {
        $this->templates()->source(Post::class, static fn (): array => ['plain' => 'pkg.plain', 'second' => 'pkg.second']);
        $this->template('templates/posts/wide');
        $this->template('templates/posts/hidden');
        config(['atrium.templates.posts' => [
            'plain' => 'site.plain',
            'extra' => 'site.extra',
            'hidden' => null,
            'second' => false,
            'wide' => 'site.wide',
        ]]);

        $views = array_map(static fn (Template $template): string => $template->view, $this->templates()->available(Post::class));

        $this->assertSame(['plain' => 'site.plain', 'extra' => 'site.extra', 'wide' => 'site.wide'], $views);
    }

    public function test_a_file_wins_over_a_package_and_the_config_over_a_file(): void
    {
        $this->templates()->source(Post::class, static fn (): array => ['wide' => 'pkg.wide', 'plain' => 'pkg.plain']);
        $this->template('templates/posts/wide');
        $this->template('templates/posts/plain');
        config(['atrium.templates.posts.plain' => 'site.plain']);

        $views = array_map(static fn (Template $template): string => $template->view, $this->templates()->available(Post::class));

        $this->assertSame(['wide' => 'templates.posts.wide', 'plain' => 'site.plain'], $views);
    }

    // The form

    public function test_a_module_without_a_template_has_no_field_in_its_form(): void
    {
        $this->assertNotContains('template', $this->formFields());
    }

    public function test_a_module_with_a_template_has_the_field_on_its_form_with_the_default_first(): void
    {
        $this->template('templates/posts/wide');

        $this->actingAs($this->admin(), 'admin')->get('/admin/posts/create')->assertOk()
            ->assertInertia(function (AssertableInertia $page): void {
                $field = collect($page->toArray()['props']['fields'])->firstWhere('name', 'template');

                $this->assertSame('select', $field['type']);
                $this->assertSame('Template', $field['label']);
                $this->assertSame('default', $field['default']);
                $this->assertSame([['value' => 'default', 'label' => 'Default'], ['value' => 'wide', 'label' => 'Wide']], $field['options']);
                $this->assertSame('params', $field['tab']);
            });
    }

    public function test_a_module_without_a_page_has_no_field_even_with_templates(): void
    {
        $this->template('templates/posts/wide');
        config(['atrium.public_urls' => []]);

        $this->assertNotContains('template', $this->formFields());
    }

    public function test_a_model_with_a_column_of_its_own_is_left_to_its_own_field(): void
    {
        config(['atrium.public_urls' => [Flyer::class => ['prefix' => 'flyers']]]);
        $this->template('templates/posts/wide');

        $this->assertNull($this->templates()->ownColumn(Post::class));
        $this->assertSame('name', $this->templates()->ownColumn(Flyer::class));
        $this->assertTrue($this->templates()->offers(Flyer::class));
        $this->assertSame([], Hook::apply('atrium.module.fields', [], Flyer::class));
    }

    /**
     * @return array<string,array{0:array<int,mixed>,1:bool,2:string}>
     */
    public static function tabs(): array
    {
        return [
            'the first of the tabs' => [[Tab::make('wide')->label('Wide'), Tab::make('other')->label('Other')], true, 'wide'],
            'the first of the tabs given as arrays' => [[['name' => 'arrayed', 'label' => 'Arrayed']], true, 'arrayed'],
            'a tab with no name' => [[['label' => 'Nameless']], true, 'params'],
            'no tabs at all' => [[], true, 'params'],
            'a form without tabs' => [[Tab::make('wide')->label('Wide')], false, 'params'],
        ];
    }

    /**
     * @param  array<int,mixed>  $tabs
     */
    #[DataProvider('tabs')]
    public function test_the_field_is_on_the_first_tab_of_the_form(array $tabs, bool $uses, string $expected): void
    {
        config(['atrium.public_urls' => [Tabbed::class => ['prefix' => 'tabbed']]]);
        $this->template('templates/posts/wide');
        Tabbed::$tabs = $tabs;
        Tabbed::$uses = $uses;

        $fields = Hook::apply('atrium.module.fields', ['first'], Tabbed::class);

        $this->assertSame('first', $fields[0], 'the fields of the module stay');
        $this->assertSame($expected, $fields[1]->getTab());
    }

    public function test_a_finder_that_knows_no_folders_finds_no_templates(): void
    {
        $this->template('templates/posts/wide');
        $this->assertSame(['wide'], array_keys($this->templates()->available(Post::class)));

        view()->setFinder(Mockery::mock(ViewFinderInterface::class));
        $this->templates()->forget();

        $this->assertSame([], $this->templates()->available(Post::class));
    }

    public function test_the_hook_ignores_what_is_not_a_model(): void
    {
        $this->template('templates/posts/wide');

        $this->assertSame([], Hook::apply('atrium.module.fields', [], 'No\\Such\\Model'));
        $this->assertSame([], Hook::apply('atrium.module.fields', [], AdminUser::class));
    }

    // Saving

    public function test_the_choice_is_saved_with_the_record_and_shown_when_it_is_opened_again(): void
    {
        $this->template('templates/posts/wide');
        $panel = $this->actingAs($this->admin(), 'admin');

        $panel->post('/admin/posts', ['name' => 'First', 'active' => true, 'sort' => 10, 'template' => 'wide'])->assertSessionHasNoErrors();
        $post = Post::query()->firstOrFail();

        $this->assertSame(['templatable_type' => Post::class, 'templatable_id' => $post->id, 'template' => 'wide'], RecordTemplate::query()->first()?->only(['templatable_type', 'templatable_id', 'template']));
        $this->assertSame('wide', $this->templates()->key($post));
        $this->assertArrayNotHasKey('template', $post->getAttributes());
        $this->assertSame(10, $post->sort, 'the rest of the form is saved as it is');
        $this->assertTrue($post->active);

        $panel->get("/admin/posts/{$post->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->where('item.template', 'wide'));

        $panel->put("/admin/posts/{$post->id}", ['name' => 'First', 'active' => true, 'sort' => 10, 'template' => 'default'])->assertSessionHasNoErrors();
        $this->assertSame(0, RecordTemplate::query()->count());
        $panel->get("/admin/posts/{$post->id}/edit")->assertInertia(fn (AssertableInertia $page) => $page->where('item.template', 'default'));
    }

    public function test_the_choice_is_changed_without_a_second_row(): void
    {
        $this->template('templates/posts/wide');
        $this->template('templates/posts/narrow');
        $post = Post::query()->create(['name' => 'First', 'active' => true, 'sort' => 10]);
        $panel = $this->actingAs($this->admin(), 'admin');

        $panel->put("/admin/posts/{$post->id}", ['name' => 'First', 'active' => true, 'sort' => 10, 'template' => 'wide']);
        $panel->put("/admin/posts/{$post->id}", ['name' => 'First', 'active' => true, 'sort' => 10, 'template' => 'narrow']);

        $this->assertSame(['narrow'], RecordTemplate::query()->pluck('template')->all());
    }

    public function test_the_choice_of_the_other_records_stays(): void
    {
        $this->template('templates/posts/wide');
        $first = Post::query()->create(['name' => 'First', 'active' => true, 'sort' => 10]);
        $second = Post::query()->create(['name' => 'Second', 'active' => true, 'sort' => 10]);
        $this->templates()->pick($first, 'wide');
        $this->templates()->pick($second, 'wide');

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/posts/{$first->id}", ['name' => 'First', 'active' => true, 'sort' => 10, 'template' => 'default']);

        $this->assertSame('default', $this->templates()->key($first));
        $this->assertSame('wide', $this->templates()->key($second));
    }

    public function test_a_template_that_is_not_offered_is_refused_and_nothing_is_saved(): void
    {
        $this->template('templates/posts/wide');

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/posts', ['name' => 'First', 'active' => true, 'sort' => 10, 'template' => 'nope'])
            ->assertSessionHasErrors('template');

        $this->assertSame(0, Post::query()->count());
        $this->assertSame(0, RecordTemplate::query()->count());
    }

    public function test_a_save_that_does_not_send_the_template_leaves_the_choice(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First', 'active' => true, 'sort' => 10]);
        $this->templates()->pick($post, 'wide');

        $this->withToken($this->token())->putJson("/api/v1/posts/{$post->id}", ['name' => 'Renamed'])->assertOk();

        $this->assertSame('Renamed', $post->fresh()->name);
        $this->assertSame('wide', $this->templates()->key($post));
    }

    public function test_the_api_saves_and_shows_the_choice_too(): void
    {
        $this->template('templates/posts/wide');

        $this->withToken($this->token())->postJson('/api/v1/posts', ['name' => 'Via API', 'active' => true, 'sort' => 10, 'template' => 'wide'])
            ->assertCreated()
            ->assertJsonPath('data.template', 'wide');

        $this->assertSame('wide', $this->templates()->key(Post::query()->firstOrFail()));
    }

    public function test_the_choice_goes_with_the_record(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First', 'active' => true, 'sort' => 10]);
        $other = Post::query()->create(['name' => 'Other', 'active' => true, 'sort' => 10]);
        $this->templates()->pick($post, 'wide');
        $this->templates()->pick($other, 'wide');

        $post->delete();

        $this->assertSame([$other->id], RecordTemplate::query()->pluck('templatable_id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_deleting_a_model_without_a_page_or_with_a_column_of_its_own_asks_nothing_of_the_table(): void
    {
        $this->template('templates/posts/wide');
        config(['atrium.public_urls' => [Flyer::class => ['prefix' => 'flyers']]]);
        $flyer = Flyer::query()->create(['name' => 'wide']);
        $row = RecordTemplate::query()->create(['templatable_type' => Flyer::class, 'templatable_id' => $flyer->id, 'template' => 'wide']);

        $flyer->delete();
        $this->assertTrue($row->fresh()?->exists ?? false);

        config(['atrium.public_urls' => []]);
        $post = Post::query()->create(['name' => 'First']);
        $other = RecordTemplate::query()->create(['templatable_type' => Post::class, 'templatable_id' => $post->id, 'template' => 'wide']);
        $post->delete();
        $this->assertTrue($other->fresh()?->exists ?? false);
    }

    public function test_a_held_choice_is_written_for_the_model_it_was_held_for_only(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First']);
        $other = new Label(['name' => 'Label']);

        $this->templates()->hold(Post::class, 'wide');
        $this->templates()->release($other);
        $this->assertSame(0, RecordTemplate::query()->count());

        $this->templates()->release($post);
        $this->assertSame('wide', $this->templates()->key($post));

        $this->templates()->release($post);
        $this->templates()->pick($post, null);
        $this->templates()->release($post);
        $this->assertSame('default', $this->templates()->key($post));
    }

    public function test_a_held_choice_is_dropped_when_none_is_held(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First']);

        $this->templates()->hold(Post::class, 'wide');
        $this->templates()->hold(Post::class, null);
        $this->templates()->release($post);

        $this->assertSame(0, RecordTemplate::query()->count());
    }

    public function test_a_template_sent_as_something_else_than_text_means_the_default(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First']);
        $this->templates()->pick($post, 'wide');

        $data = Hook::apply('atrium.record.data', ['template' => ['x'], 'name' => 'Kept'], $post, 'edit');
        Hook::fire('atrium.record.saved', $post, 'edit');

        $this->assertSame(['name' => 'Kept'], $data);
        $this->assertSame('wide', $this->templates()->key($post));
    }

    public function test_other_hooks_do_nothing_for_a_record_without_the_field(): void
    {
        $post = Post::query()->create(['name' => 'First']);

        $this->assertSame(['template' => 'x'], Hook::apply('atrium.record.data', ['template' => 'x'], $post, 'create'));
        $this->assertSame(['a' => 1], Hook::apply('atrium.record.present', ['a' => 1], $post, 'form'));
    }

    public function test_the_choice_is_shown_on_a_form_only_for_a_record_that_exists(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First']);
        $this->templates()->pick($post, 'wide');

        $this->assertSame(['template' => 'wide'], Hook::apply('atrium.record.present', [], $post, 'form'));
        $this->assertSame([], Hook::apply('atrium.record.present', [], $post, 'list'));
        $this->assertSame([], Hook::apply('atrium.record.present', [], null, 'form'));
        $this->assertSame([], Hook::apply('atrium.record.present', [], new Post, 'form'));
    }

    // Drawing

    public function test_a_record_is_drawn_with_the_view_of_its_template(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First']);

        $this->assertSame('plain.view', $this->templates()->viewOf($post, 'plain.view'));

        $this->templates()->pick($post, 'wide');
        $this->assertSame('templates.posts.wide', $this->templates()->viewOf($post, 'plain.view'));
        $this->assertSame('templates.posts.wide', $this->templates()->view(Post::class, 'wide', 'plain.view'));
    }

    public function test_a_template_that_is_gone_means_the_default_view_without_an_error(): void
    {
        $this->template('templates/posts/wide');
        $post = Post::query()->create(['name' => 'First']);
        $this->templates()->pick($post, 'wide');

        unlink($this->views.'/templates/posts/wide.blade.php');
        $this->templates()->forget();

        $this->assertSame('default', $this->templates()->key($post));
        $this->assertSame('plain.view', $this->templates()->viewOf($post, 'plain.view'));
        $this->assertSame('plain.view', $this->templates()->view(Post::class, 'wide', 'plain.view'));
        $this->assertSame(['default' => 'Default'], $this->templates()->options(Post::class));
    }

    public function test_a_template_named_by_the_config_whose_file_is_gone_means_the_default_view(): void
    {
        config(['atrium.templates.posts' => ['gone' => 'nowhere.gone']]);

        $this->assertSame('plain.view', $this->templates()->view(Post::class, 'gone', 'plain.view'));
        $this->assertSame('plain.view', $this->templates()->view(Post::class, null, 'plain.view'));
    }

    public function test_the_default_view_a_package_names_replaces_the_one_given(): void
    {
        $this->templates()->source(Post::class, static fn (): array => ['default' => 'pkg.default']);

        $this->assertSame('pkg.default', $this->templates()->view(Post::class, null, 'plain.view'));
        $this->assertSame('pkg.default', $this->templates()->view(Post::class, 'unknown', 'plain.view'));
    }

    public function test_a_record_with_a_column_of_its_own_is_drawn_by_it(): void
    {
        $this->template('own/wide');
        $this->templates()->source(Flyer::class, static fn (): array => ['wide' => 'own.wide']);
        config(['atrium.public_urls' => [Flyer::class => ['prefix' => 'flyers']]]);

        $this->assertSame('own.wide', $this->templates()->view(Flyer::class, 'wide', 'plain.view'));
        $this->assertSame('plain.view', $this->templates()->viewOf(new Flyer(['name' => 'nope']), 'plain.view'));
        $this->assertSame('plain.view', $this->templates()->viewOf(new Flyer, 'plain.view'));
        $this->assertSame('default', $this->templates()->key(new Flyer(['name' => 'nope'])));
        $this->assertSame('wide', $this->templates()->key(new Flyer(['name' => 'wide'])));
        $this->assertSame('default', $this->templates()->key(new Flyer(['name' => 'default'])));
    }

    public function test_a_record_of_a_module_without_a_template_costs_no_query(): void
    {
        $post = Post::query()->create(['name' => 'First']);

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this->assertSame('plain.view', $this->templates()->viewOf($post, 'plain.view'));
        $this->assertSame(0, $queries);
    }

    private function token(): string
    {
        $owner = AdminUser::query()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $owner->roles()->attach(AdminRole::query()->create(['name' => 'Editor', 'permissions' => ['posts.view', 'posts.create', 'posts.update', 'posts.delete']]));

        return $owner->createToken('assistant', ['posts:read', 'posts:write'])->plainTextToken;
    }
}
