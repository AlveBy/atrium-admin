<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Filters\BooleanFilter;
use Atrium\Admin\Filters\ModuleFilters;
use Atrium\Admin\Filters\SelectFilter;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tabs\Tab;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * A module adds a filter, a column or a tab to another module's list and
 * form, and the list, its API and MCP narrow by the filter alike.
 */
final class ListFiltersTest extends TestCase
{
    private const ACTIVE = ['name' => 'active', 'type' => 'boolean', 'label' => 'Shown', 'options' => [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']]];

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_a_select_filter_takes_only_its_options_read_when_needed(): void
    {
        $reads = 0;
        $filter = SelectFilter::make('sort')->label('Sort')->options(function () use (&$reads): array {
            $reads++;

            return [10 => 'Ten', 'b' => 'Bee'];
        });

        $this->assertSame(0, $reads);
        $this->assertSame('select', $filter->type());
        $this->assertSame('sort', $filter->getName());
        $this->assertSame('10', $filter->value(10));
        $this->assertSame('10', $filter->value('10'));
        $this->assertSame('b', $filter->value('b'));
        $this->assertNull($filter->value('c'));
        $this->assertNull($filter->value(['10']));
        $this->assertNull($filter->value(null));
        $this->assertSame(['name' => 'sort', 'type' => 'select', 'label' => 'Sort', 'options' => [['value' => '10', 'label' => 'Ten'], ['value' => 'b', 'label' => 'Bee']]], $filter->toArray());
        $this->assertSame(['name' => 'kind', 'type' => 'select', 'label' => '', 'options' => [['value' => 'a', 'label' => 'A']]], SelectFilter::make('kind')->options(['a' => 'A'])->toArray());
        $this->assertSame([], SelectFilter::make('kind')->toArray()['options']);
    }

    public function test_a_boolean_filter_is_yes_or_no_in_the_administrator_s_language(): void
    {
        $filter = BooleanFilter::make('active');

        $this->assertSame('boolean', $filter->type());
        $this->assertSame(['1', '1', '1', '0', '0', '0'], array_map($filter->value(...), ['1', 1, true, '0', 0, false]));
        $this->assertSame([null, null, null, null], array_map($filter->value(...), ['yes', 2, null, '']));

        $this->app->setLocale('ru');
        $this->assertSame([['value' => '1', 'label' => 'Да'], ['value' => '0', 'label' => 'Нет']], $filter->toArray()['options']);
    }

    public function test_a_permission_is_the_module_s_own_unless_named_in_full(): void
    {
        $this->assertNull(BooleanFilter::make('active')->permissionIn('posts'));
        $this->assertSame('posts.view', BooleanFilter::make('active')->permission('view')->permissionIn('posts'));
        $this->assertSame('orders.view', BooleanFilter::make('active')->permission('orders.view')->permissionIn('posts'));
        $this->assertNull(TextColumn::make('id')->permissionIn('posts'));
        $this->assertSame('posts.view', TextColumn::make('id')->permission('view')->permissionIn('posts'));
        $this->assertSame('orders.view', TextColumn::make('id')->permission('orders.view')->permissionIn('posts'));
    }

    public function test_someone_who_is_not_an_administrator_has_every_permission(): void
    {
        $filters = new ModuleFilters;

        $this->assertTrue($filters->allows(null, 'orders.view'));
        $editor = $this->editor(['posts.view']);
        $this->assertTrue($filters->allows($editor, null));
        $this->assertTrue($filters->allows($editor, 'posts.view'));
        $this->assertFalse($filters->allows($editor, 'orders.view'));
    }

    public function test_another_module_s_filter_is_drawn_above_the_list_and_narrows_it(): void
    {
        $this->posts();
        Hook::filter('atrium.module.filters', static fn (array $filters, string $model): array => $model === Post::class ? [...$filters, BooleanFilter::make('active')->label('Shown')] : $filters);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/posts')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filterControls', [self::ACTIVE])
            ->where('filters', ['search' => '', 'sort' => 'id', 'direction' => 'desc'])
            ->has('items.data', 3));
        $this->get('/admin/posts?active=0')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.active', '0')
            ->where('items.data.0.name', 'Hidden')
            ->has('items.data', 1));
        $this->get('/admin/posts?active=1')->assertInertia(fn (AssertableInertia $page) => $page->has('items.data', 2));
        // A value the filter does not know is no filter.
        $this->get('/admin/posts?active=maybe')->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('filters.active')
            ->has('items.data', 3));
    }

    public function test_a_filter_narrows_its_own_way_and_a_name_alone_still_filters_the_column(): void
    {
        $this->posts();
        Hook::filter('atrium.module.filters', static fn (array $filters): array => [
            ...$filters,
            'active',
            SelectFilter::make('size')->label('Size')->options(['small' => 'Small', 'big' => 'Big'])
                ->query(static fn (Builder $query, string $value) => $query->where('sort', $value === 'big' ? 20 : 10)),
        ]);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/posts?size=big')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filterControls.0.name', 'size')
            ->has('filterControls', 1)
            ->where('items.data.0.name', 'Big')
            ->has('items.data', 1));
        $this->get('/admin/posts?active=0')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.active', '0')
            ->where('items.data.0.name', 'Hidden')
            ->has('items.data', 1));
        $this->get('/admin/posts?size=small&active=0')->assertInertia(fn (AssertableInertia $page) => $page->has('items.data', 0));
    }

    public function test_a_select_filter_without_its_own_query_is_a_condition_on_its_column(): void
    {
        $this->posts();
        Hook::filter('atrium.module.filters', static fn (array $filters): array => [...$filters, SelectFilter::make('name')->options(['Big' => 'Big', 'Small' => 'Small'])]);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/posts?name=Small')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Small')
            ->has('items.data', 1));
    }

    public function test_a_hook_that_takes_over_a_column_takes_over_its_filter_too(): void
    {
        $this->posts();
        Hook::filter('atrium.module.filters', static fn (array $filters): array => [...$filters, SelectFilter::make('name')->options(['big' => 'Big'])]);
        Hook::filter('atrium.query.filter', static function (bool $done, Builder $query, string $name, mixed $value): bool {
            $query->where('name', ucfirst((string) $value));

            return true;
        });
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/posts?name=big')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Big')
            ->has('items.data', 1));
    }

    public function test_a_filter_or_a_column_needing_a_permission_is_not_there_without_it(): void
    {
        $this->posts();
        // The one they may not use comes first: the next is still theirs.
        Hook::filter('atrium.module.filters', static fn (array $filters): array => [
            ...$filters,
            BooleanFilter::make('active')->label('Shown')->permission('orders.view'),
            SelectFilter::make('size')->label('Size')->options(['big' => 'Big'])->permission('view')->query(static fn (Builder $query) => $query->where('sort', 20)),
        ]);
        // The hidden column comes first too: the others are still a list.
        Hook::filter('atrium.module.columns', static fn (array $columns): array => [TextColumn::make('sort')->label('Sort')->permission('orders.view'), ...$columns]);

        $this->actingAs($this->editor(['posts.view']), 'admin');
        $this->get('/admin/posts?active=0')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filterControls.0.name', 'size')
            ->has('filterControls', 1)
            ->where('columns', fn ($columns): bool => array_is_list($columns->all()) && ! in_array('sort', array_column($columns->all(), 'name'), true))
            ->has('items.data', 3));
        $this->get('/admin/posts?size=big')->assertInertia(fn (AssertableInertia $page) => $page->where('items.data.0.name', 'Big')->has('items.data', 1));

        $this->actingAs($this->editor(['posts.view', 'orders.view'], 'boss@example.com'), 'admin');
        $this->get('/admin/posts?active=0')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filterControls.0', self::ACTIVE)
            ->has('filterControls', 2)
            ->where('columns.0.name', 'sort')
            ->has('items.data', 1));
    }

    public function test_another_module_s_column_brings_its_own_value(): void
    {
        $this->posts();
        Hook::filter('atrium.module.columns', static fn (array $columns): array => [
            ...$columns,
            TextColumn::make('orders.count')->label('Orders')->value(static fn (Post $post): string => "{$post->sort} orders"),
        ]);
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/posts')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.3.name', 'orders.count')
            ->where('items.data.0', fn ($row): bool => $row['orders.count'] === '30 orders')
            ->where('items.data.2', fn ($row): bool => $row['orders.count'] === '10 orders'));

        $this->assertTrue(TextColumn::make('x')->value(static fn (): int => 1)->isComputed());
        $this->assertFalse(TextColumn::make('x')->isComputed());
    }

    public function test_the_api_narrows_by_the_list_s_filters_with_the_owner_s_rights(): void
    {
        $this->posts();
        Hook::filter('atrium.module.filters', static fn (array $filters): array => [
            ...$filters,
            SelectFilter::make('size')->options(['big' => 'Big'])->query(static fn (Builder $query) => $query->where('sort', '>=', 20)),
            SelectFilter::make('secret')->options(['big' => 'Big'])->permission('orders.view')->query(static fn (Builder $query) => $query->where('sort', '>=', 20)),
        ]);
        $token = $this->token(['posts.view']);

        $this->assertSame(['Big'], $this->withToken($token)->getJson('/api/v1/posts?filter[size]=big&filter[active]=1')->assertOk()->json('data.*.name'));
        // A value the filter does not know, a filter the owner may not use.
        $this->assertCount(3, $this->withToken($token)->getJson('/api/v1/posts?filter[size]=huge')->json('data'));
        $this->assertCount(3, $this->withToken($token)->getJson('/api/v1/posts?filter[secret]=big')->json('data'));
        // The columns filter as before.
        $this->assertSame(['Hidden'], $this->withToken($token)->getJson('/api/v1/posts?filter[active]=0')->json('data.*.name'));
    }

    public function test_an_assistant_reads_the_filters_it_may_use(): void
    {
        Hook::filter('atrium.module.filters', static fn (array $filters): array => [
            ...$filters,
            'module',
            BooleanFilter::make('active')->label('Shown'),
            BooleanFilter::make('secret')->permission('orders.view'),
        ]);

        $module = $this->mcp($this->token(['posts.view']), 'describe_module', ['module' => 'posts']);

        $this->assertSame([self::ACTIVE], $module['filters']);
    }

    public function test_another_module_adds_a_tab_for_the_fields_it_adds(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => [...$fields, TextField::make('delivery')->label('Delivery')->tab('shop')]);

        $this->assertArrayNotHasKey('shop', array_column(Chapter::getFieldsGroupedByTabs()['tabs'], null, 'name'));

        Hook::filter('atrium.module.tabs', static fn (array $tabs, string $model): array => $model === Chapter::class ? [...$tabs, Tab::make('shop')->label('Shop')->order(50)] : $tabs);
        $grouped = Chapter::getFieldsGroupedByTabs();

        $this->assertSame('Shop', array_column($grouped['tabs'], 'label', 'name')['shop']);
        $this->assertSame(['delivery'], array_map(static fn ($field): string => $field->getName(), $grouped['fields']['shop']));
    }

    private function posts(): void
    {
        foreach ([['Small', true, 10], ['Big', true, 20], ['Hidden', false, 30]] as [$name, $active, $sort]) {
            Post::query()->create(['name' => $name, 'active' => $active, 'sort' => $sort]);
        }
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $editor = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $editor->roles()->attach(AdminRole::query()->create(['name' => 'Role '.$email, 'permissions' => $permissions]));

        return $editor;
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function token(array $permissions): string
    {
        return $this->editor($permissions, 'owner@example.com')->createToken('assistant', ['posts:read'])->plainTextToken;
    }

    /**
     * @param  array<string,mixed>  $arguments
     * @return array<string,mixed>
     */
    private function mcp(string $token, string $tool, array $arguments): array
    {
        $this->app['auth']->forgetGuards();

        $response = $this->withToken($token)
            ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $tool, 'arguments' => $arguments]])
            ->assertOk();

        return json_decode((string) $response->json('result.content.0.text'), true);
    }
}
