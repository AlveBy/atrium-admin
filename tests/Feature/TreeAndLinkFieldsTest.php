<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\LinkField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeField;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Resources\ResourceRegistry;
use Atrium\Admin\Support\Sites;
use Atrium\Admin\Tests\Fixtures\Note;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Story;
use Atrium\Admin\Tests\TestCase;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The fields the menus module builds on: TreeField (items at any depth, each
 * checked by its own fields) and LinkField (an address or a record of a
 * module that has pages).
 */
final class TreeAndLinkFieldsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Post::class, Story::class, Note::class]);
        $app['config']->set('atrium.public_urls', [Post::class => ['prefix' => 'posts']]);
    }

    private function makePost(string $name, string $code): Post
    {
        $post = new Post(['name' => $name, 'active' => true]);
        $post->forceFill(['code' => $code])->save();

        return $post;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,array<int,string>>
     */
    private function errors(array $data, LinkField|TreeField $field): array
    {
        return Validator::make($data, [$field->getName() => $field->getValidationRules()])->errors()->toArray();
    }

    private function tree(int $maxDepth = 0, int $maxItems = 500): TreeField
    {
        return TreeField::make('tree')
            ->fields([
                TextField::make('title')->label('Title')->required()->maxLength(12),
                CheckboxField::make('active')->label('Active'),
            ])
            ->maxDepth($maxDepth)
            ->maxItems($maxItems);
    }

    /**
     * A chain of items, each the only child of the one above: `$levels` deep.
     *
     * @return array<int,array<string,mixed>>
     */
    private function chain(int $levels): array
    {
        $item = ['title' => 'Level '.$levels, 'children' => []];

        for ($level = $levels - 1; $level >= 1; $level--) {
            $item = ['title' => 'Level '.$level, 'children' => [$item]];
        }

        return [$item];
    }

    // ---- TreeField: what the form is told ----

    public function test_a_tree_tells_the_form_its_fields_and_limits(): void
    {
        $props = $this->tree(maxDepth: 3, maxItems: 40)->titleField('label')->addLabel('Add a menu item')->toArray();

        $this->assertSame('tree', $props['type']);
        $this->assertSame('treeField', $props['component']);
        $this->assertSame('label', $props['titleField']);
        $this->assertSame(3, $props['maxDepth']);
        $this->assertSame(40, $props['maxItems']);
        $this->assertSame('Add a menu item', $props['addLabel']);
        $this->assertSame(['title', 'active'], array_column($props['fields'], 'name'));
        $this->assertSame(['text', 'checkbox'], array_column($props['fields'], 'type'));
    }

    public function test_a_tree_by_default_is_titled_by_title_has_no_depth_limit_and_a_translated_add_label(): void
    {
        $props = TreeField::make('tree')->toArray();

        $this->assertSame('title', $props['titleField']);
        $this->assertSame(0, $props['maxDepth']);
        $this->assertSame(500, $props['maxItems']);
        $this->assertSame('Add item', $props['addLabel']);
        $this->assertSame([], $props['fields']);
    }

    /**
     * @return array<string,array{0:int,1:int}>
     */
    public static function depths(): array
    {
        return [
            'flat' => [1, 1],
            'deep' => [7, 7],
            'unlimited' => [0, 0],
            'negative is unlimited' => [-3, 0],
        ];
    }

    #[DataProvider('depths')]
    public function test_the_depth_of_a_tree_is_never_negative(int $given, int $kept): void
    {
        $this->assertSame($kept, TreeField::make('tree')->maxDepth($given)->toArray()['maxDepth']);
    }

    /**
     * @return array<string,array{0:int,1:int}>
     */
    public static function sizes(): array
    {
        return [
            'one' => [1, 1],
            'many' => [250, 250],
            'zero keeps one' => [0, 1],
            'negative keeps one' => [-5, 1],
        ];
    }

    #[DataProvider('sizes')]
    public function test_a_tree_holds_at_least_one_item(int $given, int $kept): void
    {
        $this->assertSame($kept, TreeField::make('tree')->maxItems($given)->toArray()['maxItems']);
    }

    public function test_the_fields_of_an_item_are_kept_in_the_order_given_without_their_keys(): void
    {
        $tree = TreeField::make('tree')->fields(['b' => TextField::make('b'), 'a' => TextField::make('a')]);

        $this->assertSame([0, 1], array_keys($tree->getFields()));
        $this->assertSame(['b', 'a'], array_map(static fn (Field $field): string => $field->getName(), $tree->getFields()));
    }

    // ---- TreeField: what is saved ----

    /**
     * @return array<string,array{0:mixed,1:array<mixed>}>
     */
    public static function values(): array
    {
        return [
            'a list stays' => [[['title' => 'A'], ['title' => 'B']], [['title' => 'A'], ['title' => 'B']]],
            'keys are dropped' => [[5 => ['title' => 'A'], 9 => ['title' => 'B']], [['title' => 'A'], ['title' => 'B']]],
            'string keys are dropped' => [['x' => 1, 'y' => 2], [1, 2]],
            'empty list' => [[], []],
            'null' => [null, []],
            'a string' => ['not a list', []],
            'a number' => [5, []],
            'true' => [true, []],
        ];
    }

    /**
     * @param  array<mixed>  $expected
     */
    #[DataProvider('values')]
    public function test_a_tree_is_saved_as_a_list(mixed $value, array $expected): void
    {
        $this->assertSame($expected, $this->tree()->transformValue($value));
    }

    // ---- TreeField: validation ----

    public function test_a_tree_of_valid_items_passes(): void
    {
        $tree = [
            ['title' => 'About', 'active' => true, 'children' => [
                ['title' => 'Team', 'children' => [['title' => 'Jobs', 'children' => []]]],
            ]],
            ['title' => 'Contacts', 'children' => []],
        ];

        $this->assertSame([], $this->errors(['tree' => $tree], $this->tree()));
    }

    /**
     * @return array<string,array{0:mixed,1:bool,2:bool}>
     */
    public static function emptiness(): array
    {
        return [
            'missing' => [null, true, false],
            'an empty list' => [[], true, false],
            'one item' => [[['title' => 'A']], true, true],
        ];
    }

    #[DataProvider('emptiness')]
    public function test_a_tree_may_be_left_empty_unless_it_is_required(mixed $value, bool $validWhenOptional, bool $validWhenRequired): void
    {
        $this->assertSame($validWhenOptional, $this->errors(['tree' => $value], $this->tree()) === []);
        $this->assertSame($validWhenRequired, $this->errors(['tree' => $value], $this->tree()->required()) === []);
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function notLists(): array
    {
        return [
            'a string' => ['not a list'],
            'a number' => [5],
            'true' => [true],
        ];
    }

    #[DataProvider('notLists')]
    public function test_a_value_that_is_not_a_list_is_refused_once_and_not_walked(mixed $value): void
    {
        $errors = $this->errors(['tree' => $value], $this->tree());

        $this->assertSame(['tree'], array_keys($errors));
        $this->assertCount(1, $errors['tree']);
        $this->assertStringNotContainsString('Item', $errors['tree'][0]);
    }

    public function test_an_item_without_a_title_is_named_by_its_number(): void
    {
        $tree = [['title' => 'About', 'children' => [['title' => '', 'children' => []]]]];

        $this->assertSame(['tree' => ['Item “#2”: The Title field is required.']], $this->errors(['tree' => $tree], $this->tree()));
    }

    /**
     * @return array<string,array{0:mixed,1:string}>
     */
    public static function failingTitles(): array
    {
        return [
            'a title' => ['Far too long a title', 'Far too long a title'],
            'only spaces' => ['   ', '#1'],
            'a title with spaces around it is named without them' => ['  Far too long a title  ', 'Far too long a title'],
            'a number' => [123456789012345, '123456789012345'],
            'not a string' => [['x'], '#1'],
            'null' => [null, '#1'],
        ];
    }

    #[DataProvider('failingTitles')]
    public function test_a_failing_item_is_named_by_its_title_or_its_number(mixed $title, string $named): void
    {
        $errors = $this->errors(['tree' => [['title' => $title]]], $this->tree());

        $this->assertArrayHasKey('tree', $errors);
        $this->assertStringStartsWith("Item “{$named}”: ", $errors['tree'][0]);
    }

    public function test_an_item_is_named_by_the_title_field_the_tree_was_told_to_use(): void
    {
        $tree = TreeField::make('tree')
            ->fields([TextField::make('label')->required()->maxLength(3), TextField::make('title')])
            ->titleField('label');

        $errors = $this->errors(['tree' => [['label' => 'Too long', 'title' => 'Not this']]], $tree);

        $this->assertStringStartsWith('Item “Too long”: ', $errors['tree'][0]);
    }

    public function test_the_message_tells_which_rule_the_item_broke_in_the_fields_own_words(): void
    {
        $errors = $this->errors(['tree' => [['title' => str_repeat('a', 13)]]], $this->tree());

        $this->assertSame(['tree' => ['Item “'.str_repeat('a', 13).'”: The Title field must not be greater than 12 characters.']], $errors);
    }

    public function test_the_first_broken_item_ends_the_check(): void
    {
        $tree = [
            ['title' => '', 'children' => []],
            ['title' => '', 'children' => []],
        ];

        $this->assertCount(1, $this->errors(['tree' => $tree], $this->tree())['tree']);
    }

    public function test_a_broken_item_deep_in_the_tree_ends_the_check_of_the_whole_tree(): void
    {
        $tree = [
            ['title' => 'A', 'children' => [['title' => '']]],
            ['title' => '', 'children' => []],
        ];

        $errors = $this->errors(['tree' => $tree], $this->tree());

        $this->assertSame(['Item “#2”: The Title field is required.'], $errors['tree']);
    }

    /**
     * A problem deep in the tree, then a different one further on: the check
     * stops at the first, so the second is never reported.
     *
     * @return array<string,array{0:array<int,mixed>,1:int,2:int,3:string}>
     */
    public static function firstProblemOnly(): array
    {
        $broken = 'The list of items is broken. Reload the page and try again.';

        return [
            'a child that is not an item' => [[['title' => 'A', 'children' => ['x']], ['title' => '']], 0, 500, $broken],
            'children that are not a list' => [[['title' => 'A', 'children' => [['title' => 'B', 'children' => 'x']]], ['title' => '']], 0, 500, $broken],
            'one item too many in a branch' => [[['title' => 'A', 'children' => [['title' => 'B']]], 'x'], 0, 1, 'No more than 1 items in total.'],
            'one item too many, and that item broken' => [[['title' => 'A'], ['title' => '']], 0, 1, 'No more than 1 items in total.'],
            'a branch too deep' => [[['title' => 'A', 'children' => [['title' => 'B']]], 'x'], 1, 500, 'Items can be nested no deeper than 1 levels.'],
            'an item too deep, and that item broken' => [[['title' => 'A', 'children' => [['title' => '']]]], 1, 500, 'Items can be nested no deeper than 1 levels.'],
            'a broken item three levels down' => [[['title' => 'A', 'children' => [['title' => 'B', 'children' => [['title' => '']]]]], 'x'], 0, 500, 'Item “#3”: The Title field is required.'],
        ];
    }

    /**
     * @param  array<int,mixed>  $tree
     */
    #[DataProvider('firstProblemOnly')]
    public function test_only_the_first_problem_of_a_tree_is_reported(array $tree, int $maxDepth, int $maxItems, string $message): void
    {
        $this->assertSame(['tree' => [$message]], $this->errors(['tree' => $tree], $this->tree($maxDepth, $maxItems)));
    }

    /**
     * @return array<string,array{0:array<int,mixed>}>
     */
    public static function brokenTrees(): array
    {
        return [
            'an item that is a string' => [['not an item']],
            'an item that is a number' => [[5]],
            'an item that is null' => [[null]],
            'a good item, then a string' => [[['title' => 'A'], 'x']],
            'children that are a string' => [[['title' => 'A', 'children' => 'x']]],
            'children that are a number' => [[['title' => 'A', 'children' => 5]]],
            'a child that is a string' => [[['title' => 'A', 'children' => ['x']]]],
        ];
    }

    /**
     * @param  array<int,mixed>  $tree
     */
    #[DataProvider('brokenTrees')]
    public function test_a_broken_list_is_refused_with_one_message(array $tree): void
    {
        $this->assertSame(
            ['tree' => ['The list of items is broken. Reload the page and try again.']],
            $this->errors(['tree' => $tree], $this->tree()),
        );
    }

    public function test_children_may_be_missing_or_null(): void
    {
        $this->assertSame([], $this->errors(['tree' => [['title' => 'A']]], $this->tree()));
        $this->assertSame([], $this->errors(['tree' => [['title' => 'A', 'children' => null]]], $this->tree()));
    }

    /**
     * @return array<string,array{0:int,1:int,2:bool}>
     */
    public static function depthLimits(): array
    {
        return [
            'flat, one level' => [1, 1, true],
            'flat, two levels' => [1, 2, false],
            'two allowed, two used' => [2, 2, true],
            'two allowed, three used' => [2, 3, false],
            'three allowed, three used' => [3, 3, true],
            'three allowed, four used' => [3, 4, false],
            'any depth, one level' => [0, 1, true],
            'any depth, five levels' => [0, 5, true],
        ];
    }

    #[DataProvider('depthLimits')]
    public function test_a_tree_keeps_to_its_depth(int $maxDepth, int $levels, bool $valid): void
    {
        $errors = $this->errors(['tree' => $this->chain($levels)], $this->tree(maxDepth: $maxDepth));

        $this->assertSame($valid, $errors === []);

        if (! $valid) {
            $this->assertSame(['tree' => ["Items can be nested no deeper than {$maxDepth} levels."]], $errors);
        }
    }

    public function test_a_deep_branch_is_checked_though_the_first_branch_is_shallow(): void
    {
        $tree = [['title' => 'Shallow', 'children' => []], ...$this->chain(3)];

        $this->assertSame([], $this->errors(['tree' => $tree], $this->tree(maxDepth: 3)));
        $this->assertArrayHasKey('tree', $this->errors(['tree' => $tree], $this->tree(maxDepth: 2)));
    }

    /**
     * @return array<string,array{0:int,1:int,2:bool}>
     */
    public static function sizeLimits(): array
    {
        return [
            'one of one' => [1, 1, true],
            'two of one' => [1, 2, false],
            'exactly as many as allowed' => [3, 3, true],
            'one more than allowed' => [3, 4, false],
        ];
    }

    #[DataProvider('sizeLimits')]
    public function test_a_tree_keeps_to_its_size(int $maxItems, int $items, bool $valid): void
    {
        $tree = array_map(static fn (int $n): array => ['title' => "Item {$n}"], range(1, $items));
        $errors = $this->errors(['tree' => $tree], $this->tree(maxItems: $maxItems));

        $this->assertSame($valid, $errors === []);

        if (! $valid) {
            $this->assertSame(['tree' => ["No more than {$maxItems} items in total."]], $errors);
        }
    }

    public function test_the_size_counts_items_at_every_depth(): void
    {
        $tree = [['title' => 'A', 'children' => [['title' => 'B'], ['title' => 'C']]]];

        $this->assertSame([], $this->errors(['tree' => $tree], $this->tree(maxItems: 3)));
        $this->assertSame(['tree' => ['No more than 2 items in total.']], $this->errors(['tree' => $tree], $this->tree(maxItems: 2)));
    }

    public function test_the_size_counts_items_of_every_branch(): void
    {
        $tree = [
            ['title' => 'A', 'children' => [['title' => 'B']]],
            ['title' => 'C', 'children' => [['title' => 'D']]],
        ];

        $this->assertSame([], $this->errors(['tree' => $tree], $this->tree(maxItems: 4)));
        $this->assertArrayHasKey('tree', $this->errors(['tree' => $tree], $this->tree(maxItems: 3)));
    }

    public function test_the_rules_of_a_field_apply_to_every_item_but_a_unique_rule_is_left_out(): void
    {
        $this->makePost('Taken', 'taken');

        $tree = TreeField::make('tree')->fields([
            TextField::make('title')->required(),
            TextField::make('code')->unique('posts', 'code'),
        ]);

        $this->assertSame([], $this->errors(['tree' => [['title' => 'A', 'code' => 'taken']]], $tree), 'a tree is not a table: its items are not unique among the rows of one');
        $this->assertArrayHasKey('tree', $this->errors(['tree' => [['title' => '', 'code' => 'taken']]], $tree));
    }

    public function test_a_rule_that_is_not_text_still_applies_to_every_item(): void
    {
        $noBad = static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === 'bad') {
                $fail('No bad words.');
            }
        };
        $tree = TreeField::make('tree')->fields([TextField::make('title')->rules(['nullable', $noBad])]);

        $this->assertSame([], $this->errors(['tree' => [['title' => 'good']]], $tree));
        $this->assertSame(['tree' => ['Item “bad”: No bad words.']], $this->errors(['tree' => [['title' => 'bad']]], $tree));
    }

    public function test_a_link_inside_an_item_is_checked_like_a_link_anywhere(): void
    {
        $tree = TreeField::make('tree')->fields([
            TextField::make('title')->required(),
            LinkField::make('link')->urlsOnly(),
        ]);

        $this->assertSame([], $this->errors(['tree' => [['title' => 'Home', 'link' => ['type' => 'url', 'url' => '/']]]], $tree));
        $this->assertSame(
            ['tree' => ['Item “Home”: Enter an address starting with /, http://, https://, mailto: or tel:.']],
            $this->errors(['tree' => [['title' => 'Home', 'link' => ['type' => 'url', 'url' => 'javascript:alert(1)']]]], $tree),
        );
    }

    // ---- LinkField: what the form is told ----

    public function test_a_link_tells_the_form_the_modules_it_may_point_into_and_where_to_search(): void
    {
        $props = LinkField::make('link')->toArray();

        $this->assertSame('link', $props['type']);
        $this->assertSame('linkField', $props['component']);
        $this->assertSame([
            ['value' => Post::class, 'label' => 'Posts'],
            ['value' => Story::class, 'label' => 'Stories'],
        ], $props['targets']);
        $this->assertSame('/admin/links/records', $props['searchUrl']);
    }

    public function test_a_link_for_addresses_only_offers_no_modules_and_no_search(): void
    {
        $props = LinkField::make('link')->urlsOnly()->toArray();

        $this->assertSame([], $props['targets']);
        $this->assertNull($props['searchUrl']);
    }

    public function test_without_a_module_with_pages_a_link_offers_no_search(): void
    {
        config()->set('atrium.resources', [Post::class, Note::class]);
        config()->set('atrium.public_urls', []);
        $this->app->forgetInstance(ResourceRegistry::class);

        $props = LinkField::make('link')->toArray();

        $this->assertSame([], $props['targets']);
        $this->assertNull($props['searchUrl']);
    }

    public function test_the_modules_a_link_may_point_into_are_those_with_pages(): void
    {
        $this->assertSame([Post::class => 'Posts', Story::class => 'Stories'], LinkField::make('link')->targets());
    }

    public function test_a_module_that_lists_its_own_pages_needs_no_address_rule_and_one_with_a_rule_needs_no_list(): void
    {
        config()->set('atrium.public_urls', []);
        $this->assertSame([Story::class => 'Stories'], LinkField::make('link')->targets());

        config()->set('atrium.public_urls', [Post::class => ['prefix' => 'posts'], Note::class => ['prefix' => 'notes']]);
        $this->assertSame([Post::class => 'Posts', Story::class => 'Stories', Note::class => 'Notes'], LinkField::make('link')->targets());
    }

    // ---- LinkField: addresses ----

    /**
     * @return array<string,array{0:string,1:bool}>
     */
    public static function addresses(): array
    {
        return [
            'a path' => ['/contacts', true],
            'the home page' => ['/', true],
            'a path with a query and an anchor' => ['/search?q=a#top', true],
            'an anchor' => ['#top', true],
            'a query' => ['?page=2', true],
            'https' => ['https://example.com/a?b=c', true],
            'http' => ['http://example.com', true],
            'a scheme in capitals' => ['HTTPS://EXAMPLE.COM/A', true],
            'mailto' => ['mailto:info@example.com', true],
            'tel' => ['tel:+100200300', true],
            'Cyrillic path' => ['/контакты', true],
            'a script' => ['javascript:alert(1)', false],
            'a script in capitals' => ['JAVASCRIPT:alert(1)', false],
            'a script with a tab' => ["java\tscript:alert(1)", false],
            'a protocol-relative address' => ['//evil.example', false],
            'a backslash after the slash' => ['/\\evil.example', false],
            'two backslashes' => ['\\\\evil.example', false],
            'a path with a backslash later' => ['/a\\b', true],
            'data' => ['data:text/html,x', false],
            'vbscript' => ['vbscript:x', false],
            'file' => ['file:///etc/passwd', false],
            'http without a host' => ['http://', false],
            'https without a host' => ['https://', false],
            'http without its slashes' => ['http:example.com', false],
            'https with a space in the host' => ['https://exa mple.com', false],
            'ftp' => ['ftp://example.com', false],
            'a space before a path' => [' /contacts', false],
            'a path without its slash' => ['contacts', false],
            'a word and an anchor' => ['contacts#top', false],
            'a word and a query' => ['contacts?a=1', false],
            'a host without a scheme' => ['example.com/a', false],
            'nothing' => ['', false],
        ];
    }

    #[DataProvider('addresses')]
    public function test_a_link_takes_safe_addresses_only(string $url, bool $safe): void
    {
        $this->assertSame($safe, LinkField::isSafeUrl($url));
    }

    /**
     * @return array<string,array{0:mixed,1:bool}>
     */
    public static function urlLinks(): array
    {
        return [
            'a path' => [['type' => 'url', 'url' => '/contacts'], true],
            'a path with spaces around it' => [['type' => 'url', 'url' => '  /contacts  '], true],
            'the longest address allowed' => [['type' => 'url', 'url' => '/'.str_repeat('a', 2047)], true],
            'one character too long' => [['type' => 'url', 'url' => '/'.str_repeat('a', 2048)], false],
            'a script' => [['type' => 'url', 'url' => 'javascript:alert(1)'], false],
            'a script with spaces around it' => [['type' => 'url', 'url' => '  javascript:alert(1)  '], false],
            'an empty address' => [['type' => 'url', 'url' => ''], false],
            'only spaces' => [['type' => 'url', 'url' => '    '], false],
            'no address at all' => [['type' => 'url'], false],
            'a null address' => [['type' => 'url', 'url' => null], false],
            'an address that is a list' => [['type' => 'url', 'url' => ['/contacts']], false],
            'an address that is a number' => [['type' => 'url', 'url' => 12345], false],
            'a record sent as an address' => [['type' => 'url', 'model' => Post::class, 'id' => 1], false],
        ];
    }

    #[DataProvider('urlLinks')]
    public function test_a_link_to_an_address_is_checked(mixed $link, bool $valid): void
    {
        $errors = $this->errors(['link' => $link], LinkField::make('link'));

        $this->assertSame($valid, $errors === []);

        if (! $valid) {
            $this->assertSame(['link' => ['Enter an address starting with /, http://, https://, mailto: or tel:.']], $errors);
        }
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function noLinks(): array
    {
        return [
            'nothing' => [null],
            'an empty list' => [[]],
            'no type' => [['url' => 'javascript:alert(1)']],
            'a null type' => [['type' => null, 'url' => 'javascript:alert(1)']],
            'an empty type' => [['type' => '', 'url' => 'javascript:alert(1)']],
        ];
    }

    #[DataProvider('noLinks')]
    public function test_no_link_at_all_is_fine_when_it_is_not_required(mixed $link): void
    {
        $this->assertSame([], $this->errors(['link' => $link], LinkField::make('link')));
    }

    public function test_a_required_link_needs_a_value(): void
    {
        $this->assertArrayHasKey('link', $this->errors(['link' => null], LinkField::make('link')->required()));
        $this->assertArrayHasKey('link', $this->errors(['link' => []], LinkField::make('link')->required()));
        $this->assertSame([], $this->errors(['link' => ['type' => 'url', 'url' => '/']], LinkField::make('link')->required()));
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function notLinks(): array
    {
        return [
            'a string' => ['/contacts'],
            'a number' => [5],
            'true' => [true],
        ];
    }

    #[DataProvider('notLinks')]
    public function test_a_link_that_is_not_a_list_is_refused(mixed $link): void
    {
        $errors = $this->errors(['link' => $link], LinkField::make('link'));

        $this->assertSame(['link'], array_keys($errors));
        $this->assertCount(1, $errors['link']);
    }

    // ---- LinkField: records ----

    /**
     * Placeholders: `{Post}`, `{Story}`, `{Note}` and `{Model}` are classes, `{post}` is the id of a
     * post that exists, `{post-text}` the same id as text, `{post+100}` one that does not, `{story}`
     * the id of a story.
     *
     * @return array<string,array{0:array<string,mixed>,1:bool}>
     */
    public static function recordLinks(): array
    {
        return [
            'a record of a module with pages' => [['type' => 'record', 'model' => '{Post}', 'id' => '{post}'], true],
            'an id sent as text' => [['type' => 'record', 'model' => '{Post}', 'id' => '{post-text}'], true],
            'a record of a module that lists its own pages' => [['type' => 'record', 'model' => '{Story}', 'id' => '{story}'], true],
            'a record that is gone' => [['type' => 'record', 'model' => '{Post}', 'id' => '{post+100}'], false],
            'an id that is not a number' => [['type' => 'record', 'model' => '{Post}', 'id' => 'abc'], false],
            'an id that is a list' => [['type' => 'record', 'model' => '{Post}', 'id' => ['{post}']], false],
            'no id' => [['type' => 'record', 'model' => '{Post}'], false],
            'a null id' => [['type' => 'record', 'model' => '{Post}', 'id' => null], false],
            'a module with no pages' => [['type' => 'record', 'model' => '{Note}', 'id' => '{post}'], false],
            'something that is not a module' => [['type' => 'record', 'model' => '{Model}', 'id' => '{post}'], false],
            'an unknown class' => [['type' => 'record', 'model' => 'No\\Such\\Class', 'id' => '{post}'], false],
            'no module' => [['type' => 'record', 'id' => '{post}'], false],
            'an empty module' => [['type' => 'record', 'model' => '', 'id' => '{post}'], false],
            'a module that is a list' => [['type' => 'record', 'model' => ['{Post}'], 'id' => '{post}'], false],
            'a type nobody knows' => [['type' => 'page', 'model' => '{Post}', 'id' => '{post}'], false],
            'a type in capitals' => [['type' => 'RECORD', 'model' => '{Post}', 'id' => '{post}'], false],
            'an address with a module and an id' => [['type' => 'address', 'url' => '/', 'model' => '{Post}', 'id' => '{post}'], false],
        ];
    }

    /**
     * @param  array<string,mixed>  $link
     */
    #[DataProvider('recordLinks')]
    public function test_a_link_to_a_record_needs_a_module_with_pages_and_a_record_that_exists(array $link, bool $valid): void
    {
        $post = $this->makePost('Hello', 'hello');
        $story = Story::query()->create(['name' => ['en' => 'Story'], 'code' => ['en' => 'story']]);

        $names = ['{Post}' => Post::class, '{Story}' => Story::class, '{Note}' => Note::class, '{Model}' => Model::class];
        $ids = ['{post}' => $post->id, '{post-text}' => (string) $post->id, '{post+100}' => $post->id + 100, '{story}' => $story->id];
        $fill = static fn (mixed $value): mixed => is_string($value) ? ($names[$value] ?? $ids[$value] ?? $value) : $value;

        $link = array_map(static fn (mixed $value): mixed => is_array($value) ? array_map($fill, $value) : $fill($value), $link);

        $errors = $this->errors(['link' => $link], LinkField::make('link'));

        $this->assertSame($valid, $errors === []);

        if (! $valid) {
            $this->assertSame(['link' => ['Choose a record that still exists.']], $errors);
        }
    }

    public function test_a_record_cannot_be_linked_when_the_field_is_for_addresses_only(): void
    {
        $post = $this->makePost('Hello', 'hello');
        $field = LinkField::make('link')->urlsOnly();

        $this->assertSame(
            ['link' => ['Choose a record that still exists.']],
            $this->errors(['link' => ['type' => 'record', 'model' => Post::class, 'id' => $post->id]], $field),
        );
    }

    public function test_a_module_cannot_be_linked_to_once_it_has_no_pages(): void
    {
        $post = $this->makePost('Hello', 'hello');
        $link = ['link' => ['type' => 'record', 'model' => Post::class, 'id' => $post->id]];

        $this->assertSame([], $this->errors($link, LinkField::make('link')));

        config()->set('atrium.public_urls', []);

        $this->assertSame(['link' => ['Choose a record that still exists.']], $this->errors($link, LinkField::make('link')));
    }

    public function test_a_record_of_another_site_cannot_be_linked_to(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        $main = Site::query()->where('code', 'main')->sole();
        $german = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $ours = Story::query()->create(['site_id' => $main->id, 'name' => ['en' => 'Ours'], 'code' => ['en' => 'ours']]);
        $theirs = Story::query()->create(['site_id' => $german->id, 'name' => ['en' => 'Theirs'], 'code' => ['en' => 'theirs']]);

        $link = static fn (Story $story): array => ['link' => ['type' => 'record', 'model' => Story::class, 'id' => $story->id]];

        app(Sites::class)->setCurrent($main);

        $this->assertSame([], $this->errors($link($ours), LinkField::make('link')));
        $this->assertSame(['link' => ['Choose a record that still exists.']], $this->errors($link($theirs), LinkField::make('link')));

        app(Sites::class)->setCurrent($german);

        $this->assertSame([], $this->errors($link($theirs), LinkField::make('link')));
        $this->assertSame(['link' => ['Choose a record that still exists.']], $this->errors($link($ours), LinkField::make('link')));
    }
}
