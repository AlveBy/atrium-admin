<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Tests\Concerns\PinsExtensionSurface;
use Atrium\Admin\Tests\Fixtures\Handbook;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Traits\HasAdminTabs;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;

final class AdminTraitsTest extends TestCase
{
    use PinsExtensionSurface;

    /**
     * @param  array<string,array<int,Field>>  $grouped
     * @return array<string,array<int,string>>
     */
    private function names(array $grouped): array
    {
        return array_map(static fn (array $fields): array => array_map(static fn (Field $field): string => $field->getName(), $fields), $grouped);
    }

    public function test_fields_are_grouped_by_tab_in_their_order_for_the_create_form(): void
    {
        $grouped = TabbedArticle::getFieldsGroupedByTabs();

        $this->assertSame(['main', 'seo', 'additional'], array_column($grouped['tabs'], 'name'));
        $this->assertSame(['General', 'SEO', 'Additional'], array_column($grouped['tabs'], 'label'));
        $this->assertSame([0, 10, 20], array_column($grouped['tabs'], 'order'));

        $names = $this->names($grouped['fields']);
        $this->assertSame(['code', 'name'], $names['main']);
        $this->assertSame(['draft'], $names['additional']);
        $this->assertSame(array_keys(Handbook::seoFieldMap()), $names['seo']);
        $this->assertSame(['main', 'additional', 'seo'], array_keys($names));
    }

    public function test_the_edit_form_shows_its_own_fields(): void
    {
        $names = $this->names(TabbedArticle::getFieldsGroupedByTabs('edit')['fields']);

        $this->assertSame(['code', 'name'], $names['main']);
        $this->assertSame(['secret'], $names['additional']);
    }

    public function test_seo_fields_join_only_models_with_seo_and_go_to_the_seo_tab(): void
    {
        $withSeo = TabbedArticle::getAdminFieldsWithTabs();
        $this->assertCount(4 + count(Handbook::seoFieldMap()), $withSeo);
        $this->assertSame('seo', $withSeo[4]->getTab());
        $this->assertSame('seo', $withSeo[count($withSeo) - 1]->getTab());

        $plain = PlainTabbed::getFieldsGroupedByTabs();
        $this->assertCount(1, PlainTabbed::getAdminFieldsWithTabs());
        $this->assertSame(['main'], array_column($plain['tabs'], 'name'));
        $this->assertSame(['main' => ['title']], $this->names($plain['fields']));
    }

    public function test_tabs_given_as_arrays_are_kept_and_sorted(): void
    {
        $grouped = ArrayTabbed::getFieldsGroupedByTabs();

        $this->assertSame([
            ['name' => 'first', 'label' => 'First', 'order' => 1],
            ['name' => 'second', 'label' => 'Second', 'order' => 5],
        ], $grouped['tabs']);
        $this->assertTrue(ArrayTabbed::usesTabs());
    }

    public function test_the_admin_defaults_of_a_model(): void
    {
        $this->assertSame('posts', Post::getAdminRoutePrefix());
        $this->assertSame('admin.posts', Post::getAdminRouteName());
        $this->assertSame('blog_entries', BlogEntry::getAdminRoutePrefix());
        $this->assertSame('Add item', Post::getAdminCreateLabel());
        $this->assertSame([], Post::getAdministrationFilters());
        $this->assertSame([], Post::getAdministrationActions());
        $this->assertSame('id', Post::getDefaultSortColumn());
        $this->assertSame('desc', Post::getDefaultSortDirection());
        $this->assertSame(20, Post::getAdminPerPage());
        $this->assertSame([], Post::getCreateValidationRules());
        $this->assertSame([], Post::getUpdateValidationRules());
        $this->assertSame(['name' => ['required']], BlogEntry::getUpdateValidationRules());
        $this->assertTrue(Post::isCreatableInAdmin());
        $this->assertTrue(Post::isEditableInAdmin());

        $post = new Post(['name' => 'Hello']);
        $this->assertSame(['name' => 'Hello'], $post->transformAdminData(['name' => 'Hello']));
        $post->afterAdminSave(['name' => 'Hello']);
        $this->assertSame(['name' => 'Hello'], $post->getAttributes());
        $this->assertTrue($post->beforeAdminDelete());
        $this->assertTrue($post->canEdit());
        $this->assertTrue($post->canDelete());
    }

    public function test_the_admin_list_scope_changes_nothing(): void
    {
        Post::query()->create(['name' => 'One']);
        Post::query()->create(['name' => 'Two']);

        $query = Post::query()->adminList();

        $this->assertSame(Post::query()->toSql(), $query->toSql());
        $this->assertSame(2, $query->count());
    }

    public function test_the_breadcrumbs_lead_from_the_list_to_the_record(): void
    {
        $this->assertSame([
            ['label' => 'Posts', 'url' => route('admin.posts.index')],
            ['label' => 'New'],
        ], (new Post)->getAdminBreadcrumbs());

        $post = Post::query()->create(['name' => 'Hello']);
        $this->assertSame([
            ['label' => 'Posts', 'url' => route('admin.posts.index')],
            ['label' => 'Hello'],
        ], $post->getAdminBreadcrumbs());
    }

    public function test_the_title_of_a_record_is_its_name_then_title_then_id(): void
    {
        $this->assertSame('Hello', (new BlogEntry(['name' => 'Hello', 'title' => 'Title']))->getAdminTitle());
        $this->assertSame('Title', (new BlogEntry(['title' => 'Title']))->getAdminTitle());
        $this->assertSame('#7', (new BlogEntry(['id' => 7]))->getAdminTitle());
    }
}

class TabbedArticle extends Handbook
{
    use HasAdminTabs;

    public static function getAdministrationFields(): array
    {
        return [
            TextField::make('name')->tab('main')->tabOrder(2),
            TextField::make('code')->tab('main')->tabOrder(1),
            TextField::make('secret')->tab('additional')->onlyOnEdit(),
            TextField::make('draft')->tab('additional')->hideOnEdit(),
        ];
    }
}

final class PlainTabbed extends Model
{
    use HasAdminTabs;

    public static function getAdministrationFields(): array
    {
        return [TextField::make('title')->tab('main')];
    }
}

final class ArrayTabbed extends Model
{
    use HasAdminTabs;

    public static function getAdminTabs(): array
    {
        return [
            ['name' => 'second', 'label' => 'Second', 'order' => 5],
            ['name' => 'empty', 'label' => 'Empty', 'order' => 0],
            ['name' => 'first', 'label' => 'First', 'order' => 1],
        ];
    }

    public static function getAdministrationFields(): array
    {
        return [TextField::make('a')->tab('second'), TextField::make('b')->tab('first')];
    }
}

final class BlogEntry extends Model
{
    use InteractsWithAdmin;

    protected $guarded = [];

    public static function getAdminLabel(): string
    {
        return 'Blog';
    }

    public static function getCreateValidationRules(): array
    {
        return ['name' => ['required']];
    }
}
