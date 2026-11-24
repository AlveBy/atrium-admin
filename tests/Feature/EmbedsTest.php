<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Embeds\Embed;
use Atrium\Admin\Embeds\Embeds;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\PostEmbed;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\HtmlString;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * Embeds: a module registers a type, the editor leaves a placeholder in the
 * HTML, and the site shows what the type renders in its place.
 */
final class EmbedsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Hook::register(Embeds::REGISTRY, 'post', new PostEmbed);
    }

    private function embeds(): Embeds
    {
        return $this->app->make(Embeds::class);
    }

    private function makePost(string $name, bool $active = true): Post
    {
        return Post::query()->create(['name' => $name, 'active' => $active]);
    }

    private function placeholder(string $type, int|string|null $id): string
    {
        return '<div data-atrium-embed="'.$type.'"'.($id === null ? '' : ' data-id="'.$id.'"').'></div>';
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor'.AdminUser::query()->count().'@example.com', 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role '.AdminRole::query()->count(), 'permissions' => $permissions]));

        return $user->fresh();
    }

    public function test_a_placeholder_is_swapped_for_the_embed_and_the_text_around_it_stays(): void
    {
        $post = $this->makePost('Hello');

        $html = '<p>Before</p>'.$this->placeholder('post', $post->id).'<p>After</p>';

        $this->assertSame('<p>Before</p><b>Hello</b><p>After</p>', $this->embeds()->render($html));
    }

    public function test_several_placeholders_are_swapped_each_with_its_own_item(): void
    {
        $one = $this->makePost('One');
        $two = $this->makePost('Two');

        $html = $this->placeholder('post', $two->id).'<hr>'.$this->placeholder('post', $one->id);

        $this->assertSame('<b>Two</b><hr><b>One</b>', $this->embeds()->render($html));
    }

    public function test_a_text_without_placeholders_comes_back_as_it_is(): void
    {
        $html = '<p>Plain <div data-other="post" data-id="1"></div></p>';

        $this->assertSame($html, $this->embeds()->render($html));
        $this->assertSame('', $this->embeds()->render(null));
        $this->assertSame('', $this->embeds()->render(''));
    }

    public function test_html_that_is_htmlable_is_taken_too(): void
    {
        $post = $this->makePost('Hello');

        $this->assertSame('<b>Hello</b>', $this->embeds()->render(new HtmlString($this->placeholder('post', $post->id))));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function spellings(): array
    {
        return [
            'single quotes' => ["<div data-atrium-embed='post' data-id='%d'></div>"],
            'the other order' => ['<div data-id="%d" data-atrium-embed="post"></div>'],
            'more attributes' => ['<div class="x" data-atrium-embed="post" style="a:b" data-id="%d" contenteditable="false"></div>'],
            'spaces inside' => ['<div data-atrium-embed = "post" data-id = "%d" > </div>'],
            'capitals' => ['<DIV DATA-ATRIUM-EMBED="post" DATA-ID="%d"></DIV>'],
            'a line break inside' => ["<div data-atrium-embed=\"post\" data-id=\"%d\">\n</div>"],
            'entities in the value' => ['<div data-atrium-embed="po&#115;t" data-id="%d"></div>'],
        ];
    }

    #[DataProvider('spellings')]
    public function test_a_hand_written_placeholder_works_too(string $spelling): void
    {
        $post = $this->makePost('Hello');

        $this->assertSame('<b>Hello</b>', $this->embeds()->render(sprintf($spelling, $post->id)));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function nothing(): array
    {
        return [
            'an unknown type' => ['<div data-atrium-embed="nope" data-id="1"></div>'],
            'a missing item' => ['<div data-atrium-embed="post" data-id="9999"></div>'],
            'an id that is not a number' => ['<div data-atrium-embed="post" data-id="abc"></div>'],
            'an id with a tail' => ['<div data-atrium-embed="post" data-id="1 or 1=1"></div>'],
            'no id' => ['<div data-atrium-embed="post"></div>'],
            'an unquoted type' => ['<div data-atrium-embed=post data-id="1"></div>'],
        ];
    }

    #[DataProvider('nothing')]
    public function test_what_cannot_be_shown_leaves_nothing_and_no_error(string $placeholder): void
    {
        $this->makePost('Hello');

        $this->assertSame('<p>a</p><p>b</p>', $this->embeds()->render('<p>a</p>'.$placeholder.'<p>b</p>'));
    }

    public function test_a_div_with_content_is_not_a_placeholder(): void
    {
        $post = $this->makePost('Hello');
        $html = '<div data-atrium-embed="post" data-id="'.$post->id.'">typed by hand</div>';

        $this->assertSame($html, $this->embeds()->render($html));
    }

    public function test_an_embed_that_fails_is_reported_and_the_page_goes_on(): void
    {
        Exceptions::fake();
        Hook::register(Embeds::REGISTRY, 'broken', new class implements Embed
        {
            public function label(): string
            {
                return 'Broken';
            }

            public function available(): bool
            {
                return true;
            }

            public function permitted(?AdminUser $user): bool
            {
                return true;
            }

            public function options(string $search): array
            {
                return [];
            }

            public function labels(array $ids): array
            {
                return [];
            }

            public function render(string $id): string
            {
                throw new RuntimeException('it failed');
            }
        });

        $this->assertSame('<p>a</p>', $this->embeds()->render('<p>a</p>'.$this->placeholder('broken', 1)));

        Exceptions::assertReported(fn (RuntimeException $error): bool => $error->getMessage() === 'it failed');
    }

    public function test_a_type_whose_module_is_off_and_a_stranger_in_the_registry_are_not_used(): void
    {
        Hook::register(Embeds::REGISTRY, 'off', new class extends PostEmbed
        {
            public function available(): bool
            {
                return false;
            }
        });
        Hook::register(Embeds::REGISTRY, 'stranger', 'not an embed');
        $post = $this->makePost('Hello');

        $this->assertSame(['post'], array_keys($this->embeds()->all()));
        $this->assertNull($this->embeds()->get('off'));
        $this->assertSame('', $this->embeds()->render($this->placeholder('off', $post->id)));
        $this->assertInstanceOf(PostEmbed::class, $this->embeds()->get('post'));
    }

    public function test_the_blade_directive_swaps_the_placeholders(): void
    {
        $post = $this->makePost('Hello');

        $this->assertSame(
            '<p>x</p><b>Hello</b>',
            Blade::render('@embeds($html)', ['html' => new HtmlString('<p>x</p>'.$this->placeholder('post', $post->id))]),
        );
        $this->assertSame('<i>&lt;</i>', Blade::render('@embeds("<i>&lt;</i>")'));
    }

    public function test_the_picker_lists_the_types_an_administrator_may_use(): void
    {
        $this->assertSame([['type' => 'post', 'label' => 'Post', 'url' => '/admin/embeds/post']], $this->embeds()->types($this->admin()));
        $this->assertSame([], $this->embeds()->types($this->editor([])));
        $this->assertSame([['type' => 'post', 'label' => 'Post', 'url' => '/admin/embeds/post']], $this->embeds()->types($this->editor(['posts.view'])));
        $this->assertSame([['type' => 'post', 'label' => 'Post', 'url' => '/admin/embeds/post']], $this->embeds()->types(null));
    }

    public function test_every_admin_page_carries_the_types_for_the_insert_button(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin')->assertInertia(fn (AssertableInertia $page) => $page->where('embeds', [['type' => 'post', 'label' => 'Post', 'url' => '/admin/embeds/post']]));
    }

    public function test_the_picker_endpoint_wants_a_signed_in_administrator(): void
    {
        $this->getJson('/admin/embeds/post')->assertUnauthorized();
    }

    public function test_the_picker_endpoint_knows_only_the_registered_types(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/embeds/nope')->assertNotFound();
    }

    public function test_the_picker_endpoint_is_closed_to_one_who_may_not_see_the_module(): void
    {
        $this->makePost('Hello');
        $this->actingAs($this->editor([]), 'admin');

        $this->getJson('/admin/embeds/post')->assertForbidden();
    }

    public function test_the_picker_endpoint_finds_items_by_a_few_words(): void
    {
        $hello = $this->makePost('Hello world');
        $this->makePost('Goodbye');
        $this->actingAs($this->editor(['posts.view']), 'admin');

        $this->getJson('/admin/embeds/post?q=hello')->assertOk()->assertExactJson(['data' => [['id' => (string) $hello->id, 'label' => 'Hello world']]]);
        $this->getJson('/admin/embeds/post?q=zzz')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/admin/embeds/post')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/admin/embeds/post?q[]=x')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_the_picker_offers_twenty_items_at_most(): void
    {
        foreach (range(1, 25) as $number) {
            $this->makePost('Post '.$number);
        }
        $this->actingAs($this->admin(), 'admin');

        $this->getJson('/admin/embeds/post')->assertOk()->assertJsonCount(20, 'data');
    }

    public function test_the_names_of_known_ids_come_for_the_chips(): void
    {
        $one = $this->makePost('One');
        $two = $this->makePost('Two');
        $this->makePost('Three');
        $this->actingAs($this->admin(), 'admin');

        $this->getJson("/admin/embeds/post?ids={$two->id},{$one->id},zzz,9999")
            ->assertOk()
            ->assertExactJson(['data' => [['id' => (string) $one->id, 'label' => 'One'], ['id' => (string) $two->id, 'label' => 'Two']]]);
    }
}
