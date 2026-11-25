<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Http\Controllers\AdminController;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Tests\Fixtures\Bulletin;
use Atrium\Admin\Tests\Fixtures\Chapter;
use Atrium\Admin\Tests\Fixtures\Notice;
use Atrium\Admin\Tests\Fixtures\Page;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;

/**
 * The filter hooks a package (custom fields) uses to add to any module:
 * its fields, list columns, translations, buttons, and how the list is
 * filtered, searched and sorted, in the panel and through the API. Each
 * one both ways: a hook that acts, and the core's own behaviour without it.
 */
final class ModuleHooksTest extends TestCase
{
    private ?AdminUser $admin = null;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Bulletin::class, Page::class, Notice::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * @return array<string,array{string,list<string>,array<string,string>}>
     */
    public static function listAddresses(): array
    {
        return [
            'a declared filter narrows the list' => ['?name=Apple', ['Apple'], ['name' => 'Apple']],
            'two declared filters narrow it together' => ['?name=Apple&active=0', [], ['name' => 'Apple', 'active' => '0']],
            'the second filter alone' => ['?active=0', ['Pear'], ['active' => '0']],
            'a column the module does not declare is ignored' => ['?updated_at=2020-01-01', ['Pear', 'Apple'], []],
            'an empty value is ignored' => ['?name=', ['Pear', 'Apple'], []],
            'an array is ignored' => ['?name[]=Apple', ['Pear', 'Apple'], []],
        ];
    }

    /**
     * @param  list<string>  $names
     * @param  array<string,string>  $filters
     */
    #[DataProvider('listAddresses')]
    public function test_the_list_is_narrowed_only_by_the_declared_filters(string $query, array $names, array $filters): void
    {
        Bulletin::query()->create(['name' => 'Apple']);
        Bulletin::query()->create(['name' => 'Pear', 'active' => false]);

        $this->panel()->get('/admin/bulletins'.$query)
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($names, $filters): void {
                $props = $page->toArray()['props'];
                $this->assertSame($names, array_column($props['items']['data'], 'name'));
                $this->assertSame('', $props['filters']['search']);
                $this->assertSame($filters, array_diff_key($props['filters'], array_flip(['search', 'sort', 'direction'])));
            });
    }

    public function test_the_create_form_opened_from_a_narrowed_list_starts_with_its_value(): void
    {
        $this->panel()->get('/admin/bulletins/create?name=Apple&sort=7')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fields.0.name', 'name')
                ->where('fields.0.default', 'Apple')
                ->where('fields.1.name', 'sort')
                ->where('fields.1.default', 500));

        $this->panel()->get('/admin/bulletins/create')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('fields.0.default', null));

        $this->assertArrayHasKey(AdminController::class.'::create', $this->assertRouteActionsArePublic());
    }

    public function test_a_module_s_controller_can_change_how_its_list_is_narrowed_and_shown(): void
    {
        foreach (['listFilters', 'translatableAttributes', 'listRow'] as $method) {
            $this->assertTrue((new ReflectionMethod(AdminController::class, $method))->isProtected(), $method);
        }
    }

    public function test_a_filter_hook_applies_the_value_itself(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => '7', 'sort' => 1]);
        $heard = [];
        Hook::filter('atrium.query.filter', static function (bool $handled, Builder $query, string $name, mixed $value) use (&$heard): bool {
            $heard[] = [$handled, $name, $value];
            $query->where('sort', (int) $value);

            return true;
        });

        $this->assertSame(['Apple'], $this->listNames('/admin/bulletins?name=7'));
        $this->assertSame([[false, 'name', '7']], $heard);
    }

    public function test_a_filter_hook_that_passes_leaves_the_core_filter(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => '7', 'sort' => 1]);
        Hook::filter('atrium.query.filter', static fn (bool $handled): bool => $handled);

        $this->assertSame(['7'], $this->listNames('/admin/bulletins?name=7'));
    }

    public function test_a_search_hook_searches_a_column_its_own_way(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Seven', 'sort' => 1]);
        $heard = [];
        Hook::filter('atrium.query.search', static function (bool $handled, Builder $query, string $column, string $search) use (&$heard): bool {
            $heard[] = [$handled, $column, $search];
            $query->orWhere('sort', (int) $search);

            return true;
        });

        $this->assertSame(['Apple'], $this->listNames('/admin/bulletins?search=7'));
        $this->assertSame([[false, 'name', '7']], $heard);
    }

    public function test_a_search_hook_that_takes_one_column_leaves_the_next_to_the_core(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Pear 7', 'sort' => 1]);
        // As text the sort would match too: the hook compares it as a number.
        Bulletin::query()->create(['name' => 'Plum', 'sort' => 17]);
        $this->searchSortToo();

        $this->assertSame(['Pear 7', 'Apple'], $this->listNames('/admin/bulletins?search=7'));
        $this->assertSame(['Apple', 'Pear 7'], $this->apiNames('/api/v1/bulletins?search=7'));
    }

    public function test_without_a_search_hook_the_column_is_searched_as_text(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Seven', 'sort' => 1]);
        Hook::filter('atrium.query.search', static fn (bool $handled): bool => $handled);

        $this->assertSame(['Seven'], $this->listNames('/admin/bulletins?search=Sev'));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function directions(): array
    {
        return [
            'descending' => ['desc', 'desc'],
            'ascending' => ['asc', 'asc'],
            'anything else is ascending' => ['sideways', 'asc'],
        ];
    }

    #[DataProvider('directions')]
    public function test_a_sort_hook_orders_the_list_itself(string $direction, string $heardDirection): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 2]);
        Bulletin::query()->create(['name' => 'Pear', 'sort' => 1]);
        $heard = [];
        Hook::filter('atrium.query.sort', static function (bool $handled, Builder $query, string $column, string $direction) use (&$heard): bool {
            $heard[] = [$handled, $column, $direction];
            $query->orderBy('sort', $direction);

            return true;
        });

        $names = $this->listNames('/admin/bulletins?sort=weight&direction='.$direction);

        $this->assertSame([[false, 'weight', $heardDirection]], $heard);
        $this->assertSame($heardDirection === 'desc' ? ['Apple', 'Pear'] : ['Pear', 'Apple'], $names);
    }

    public function test_without_a_sort_hook_the_list_is_sorted_by_its_column(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 2]);
        Bulletin::query()->create(['name' => 'Pear', 'sort' => 1]);
        Hook::filter('atrium.query.sort', static fn (bool $handled): bool => $handled);

        $this->assertSame(['Pear', 'Apple'], $this->listNames('/admin/bulletins?sort=sort&direction=asc'));
        $this->assertSame(['Apple', 'Pear'], $this->listNames('/admin/bulletins?sort=sort&direction=desc'));
    }

    public function test_a_package_adds_list_columns_and_buttons_to_a_module(): void
    {
        Hook::filter('atrium.module.columns', static fn (array $columns, string $model): array => $model === Post::class
            ? [...$columns, TextColumn::make('weight')->label('Weight')]
            : $columns);
        Hook::filter('atrium.module.links', static fn (array $links, string $model): array => $model === Post::class
            ? [...$links, ['label' => 'Fields', 'url' => '/admin/custom-fields?module=post']]
            : $links);

        $this->panel()->get('/admin/posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('columns.3.name', 'weight')
            ->where('links', [['label' => 'Fields', 'url' => '/admin/custom-fields?module=post']]));

        $this->panel()->get('/admin/bulletins')->assertInertia(fn (AssertableInertia $page) => $page
            ->count('columns', 3)
            ->where('links', []));
    }

    public function test_a_package_presents_each_list_row_and_the_form(): void
    {
        $post = Post::query()->create(['name' => 'Hello']);
        Hook::filter('atrium.record.present', static fn (array $data, Model $record, string $context): array => [...$data, 'extra' => $context.' '.$record->getKey()]);

        $this->panel()->get('/admin/posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Hello')
            ->where('items.data.0.extra', 'list '.$post->id));

        $this->panel()->get('/admin/posts/'.$post->id.'/edit')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('item.name', 'Hello')
            ->where('item.extra', 'form '.$post->id));
    }

    public function test_without_a_present_hook_the_rows_are_left_as_they_are(): void
    {
        Post::query()->create(['name' => 'Hello']);

        $this->panel()->get('/admin/posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Hello')
            ->missing('items.data.0.extra'));
    }

    public function test_a_package_adds_fields_to_a_module_form(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields, string $model): array => in_array($model, [Post::class, Chapter::class], true)
            ? [...$fields, TextField::make('weight')->label('Weight')]
            : $fields);

        $this->assertSame('weight', last(AdminController::fieldsOf(Post::class))->getName());
        $this->assertSame('weight', last(Chapter::getAdminFieldsWithTabs())->getName());
        // Public, not only reachable through Eloquent's __callStatic().
        $this->assertTrue((new ReflectionMethod(Chapter::class, 'getAdminFieldsWithTabs'))->isPublic());
        $this->assertNotContains('weight', array_map(static fn ($field): string => $field->getName(), AdminController::fieldsOf(Bulletin::class)));

        $this->panel()->get('/admin/posts/create')->assertInertia(fn (AssertableInertia $page) => $page->where('fields.5.name', 'weight'));
    }

    public function test_a_package_marks_its_fields_translatable_in_the_api_documentation(): void
    {
        $note = fn (): string => (string) collect(app(ApiDocumentation::class)->modules(['posts'])['posts']['fields'])->firstWhere('name', 'name')['note'];

        $this->assertStringNotContainsString('Translatable', $note());

        Hook::filter('atrium.module.translatable', static fn (array $names, string $model): array => $model === Post::class ? [...$names, 'name'] : $names);

        $this->assertStringContainsString('Translatable', $note());
    }

    public function test_only_the_model_s_own_translations_are_read_in_one_language_on_the_list(): void
    {
        Page::query()->create(['name' => ['en' => 'Alpha', 'de' => 'Alfa'], 'sort' => 3]);
        Post::query()->create(['name' => 'Hello']);
        // A package's own names: not the model's translations.
        Hook::filter('atrium.module.translatable', static fn (array $names): array => [...$names, 'sort', 'name']);

        $this->panel()->get('/admin/pages')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Alpha')
            ->where('items.data.0.sort', 3));

        $this->panel()->get('/admin/posts')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Hello'));
    }

    public function test_a_hidden_translation_stays_out_of_the_list_row(): void
    {
        Notice::query()->create(['name' => ['en' => 'Alpha'], 'code' => ['en' => 'alpha']]);

        $this->panel()->get('/admin/notices')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.data.0.name', 'Alpha')
            ->missing('items.data.0.code'));
    }

    public function test_an_api_filter_hook_filters_by_a_field_that_is_not_a_column(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Pear', 'sort' => 1]);
        $heard = [];
        // Typed: a numeric key reaches it as a string.
        Hook::filter('atrium.query.filter', static function (bool $handled, Builder $query, string $name, mixed $value) use (&$heard): bool {
            $heard[] = [$handled, $name, $value];

            if (in_array($name, ['weight', '0'], true)) {
                $query->where('sort', (int) $value);

                return true;
            }

            return $handled;
        });

        $this->assertSame(['Apple'], $this->apiNames('/api/v1/bulletins?filter[weight]=7'));
        $this->assertSame(['Pear'], $this->apiNames('/api/v1/bulletins?filter[0]=1'));
        $this->assertSame(['Apple', 'Pear'], $this->apiNames('/api/v1/bulletins?filter[height]=7'), 'Neither a column nor the hook\'s: ignored.');
        $this->assertSame(['Pear'], $this->apiNames('/api/v1/bulletins?filter[name]=Pear'), 'The hook passes on a column: the core filters.');
        $this->assertSame([[false, 'weight', '7'], [false, '0', '1'], [false, 'height', '7'], [false, 'name', 'Pear']], $heard);
    }

    public function test_an_api_filter_hook_leaves_the_next_filter_to_the_core(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Pear', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Plum', 'sort' => 1]);
        Hook::filter('atrium.query.filter', static function (bool $handled, Builder $query, string $name, mixed $value): bool {
            if ($name !== 'weight') {
                return $handled;
            }

            $query->where('sort', (int) $value);

            return true;
        });

        $this->assertSame(['Pear'], $this->apiNames('/api/v1/bulletins?filter[weight]=7&filter[name]=Pear'));
    }

    public function test_the_list_is_sorted_by_a_translation_in_the_default_language(): void
    {
        Page::query()->create(['name' => ['en' => 'Beta', 'de' => 'Alfa']]);
        Page::query()->create(['name' => ['en' => 'Alpha', 'de' => 'Zeta']]);

        $this->assertSame(['Alpha', 'Beta'], $this->listNames('/admin/pages?sort=name&direction=asc'));
        $this->assertSame(['Beta', 'Alpha'], $this->listNames('/admin/pages?sort=name&direction=desc'));
    }

    public function test_api_search_follows_the_column_and_search_hooks(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 7]);
        Bulletin::query()->create(['name' => 'Seven', 'sort' => 1]);

        $this->assertSame(['Seven'], $this->apiNames('/api/v1/bulletins?search=Sev'));

        Hook::filter('atrium.query.search', static function (bool $handled, Builder $query, string $column, string $search): bool {
            $query->orWhere('sort', (int) $search);

            return true;
        });

        $this->assertSame(['Apple'], $this->apiNames('/api/v1/bulletins?search=7'));

        // Nothing searchable left: the search narrows nothing.
        Hook::filter('atrium.module.columns', static fn (array $columns): array => [TextColumn::make('name')->label('Name')], 20);

        $this->assertSame(['Apple', 'Seven'], $this->apiNames('/api/v1/bulletins?search=nothing-like-it'));
    }

    public function test_an_api_sort_hook_orders_by_a_field_that_is_not_a_column(): void
    {
        Bulletin::query()->create(['name' => 'Apple', 'sort' => 2]);
        Bulletin::query()->create(['name' => 'Pear', 'sort' => 1]);

        $this->assertSame(['Pear', 'Apple'], $this->apiNames('/api/v1/bulletins?sort=weight&direction=desc'), 'Unknown column: by id.');

        $heard = [];
        Hook::filter('atrium.query.sort', static function (bool $handled, Builder $query, string $column, string $direction) use (&$heard): bool {
            $heard[] = [$handled, $column, $direction];
            $query->orderBy('sort', $direction);

            return true;
        });

        $this->assertSame(['Apple', 'Pear'], $this->apiNames('/api/v1/bulletins?sort=weight&direction=desc'));
        $this->assertSame(['Pear', 'Apple'], $this->apiNames('/api/v1/bulletins?sort=weight'));
        $this->assertSame([[false, 'weight', 'desc'], [false, 'weight', 'asc']], $heard);
    }

    private function panel(): static
    {
        return $this->actingAs($this->admin ??= $this->admin(), 'admin');
    }

    /**
     * @return list<string>
     */
    private function listNames(string $url): array
    {
        $names = [];
        $this->panel()->get($url)->assertOk()->assertInertia(function (AssertableInertia $page) use (&$names): void {
            $names = array_column($page->toArray()['props']['items']['data'], 'name');
        });

        return $names;
    }

    /**
     * Bulletins searched by their sort first, and a search hook that takes
     * the sort column and compares it as a number.
     */
    private function searchSortToo(): void
    {
        Hook::filter('atrium.module.columns', static fn (array $columns, string $model): array => $model === Bulletin::class
            ? [TextColumn::make('sort')->label('Sort')->searchable(), ...$columns]
            : $columns);
        Hook::filter('atrium.query.search', static function (bool $handled, Builder $query, string $column, string $search): bool {
            if ($column !== 'sort') {
                return $handled;
            }

            $query->orWhere('sort', (int) $search);

            return true;
        });
    }

    /**
     * @return list<string>
     */
    private function apiNames(string $url): array
    {
        $this->app['auth']->forgetGuards();
        $owner = AdminUser::query()->firstOrCreate(['email' => 'owner@example.com'], ['name' => 'Owner', 'password' => 'secret-password', 'is_active' => true]);
        $owner->forceFill(['is_superadmin' => true])->save();

        return $this->withToken($owner->createToken('agent', ['bulletins:read'])->plainTextToken)
            ->getJson($url)->assertOk()->json('data.*.name');
    }
}
