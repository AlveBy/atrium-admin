<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Unit\Fields;

use Atrium\Admin\Fields\BelongsToField;
use Atrium\Admin\Fields\BelongsToManyField;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Fields\TreeSelectField;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Tests\Fixtures\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Links to other records: one, several, or a place in a tree.
 */
final class RelationFieldsTest extends FieldTestCase
{
    public function test_a_belongs_to_field_without_a_model_offers_nothing(): void
    {
        $field = BelongsToField::make('post_id');
        $array = $field->toArray();

        $this->assertSame('belongsTo', $field->getType());
        $this->assertNull($field->getModelClass());
        $this->assertSame('name', $field->getDisplayColumn());
        $this->assertSame('id', $field->getValueColumn());
        $this->assertNull($array['modelClass']);
        $this->assertSame('name', $array['displayColumn']);
        $this->assertSame('id', $array['valueColumn']);
        $this->assertTrue($array['searchable']);
        $this->assertTrue($array['clearable']);
        $this->assertNull($array['searchRoute']);
        $this->assertSame([], $array['options']);
        $this->assertSame([], $field->getOptions());
        $this->assertSame(['nullable'], $field->getValidationRules());
    }

    public function test_a_belongs_to_field_lists_every_record_of_its_model(): void
    {
        $first = Post::query()->create(['name' => 'Первый']);
        $second = Post::query()->create(['name' => '第二']);

        $field = BelongsToField::make('post_id')->model(Post::class);

        $this->assertSame(Post::class, $field->getModelClass());
        $this->assertSame(Post::class, $field->toArray()['modelClass']);
        $this->assertEqualsCanonicalizing([
            ['value' => $first->id, 'label' => 'Первый'],
            ['value' => $second->id, 'label' => '第二'],
        ], $field->toArray()['options']);
    }

    public function test_the_query_can_narrow_and_order_the_records(): void
    {
        Post::query()->create(['name' => 'B', 'active' => true]);
        Post::query()->create(['name' => 'Hidden', 'active' => false]);
        Post::query()->create(['name' => 'A', 'active' => true]);

        $field = BelongsToField::make('post_id')->model(Post::class)
            ->query(fn (Builder $query): Builder => $query->where('active', true)->orderBy('name'));

        $this->assertSame(['A', 'B'], array_column($field->getOptions(), 'label'));
    }

    public function test_the_shown_and_the_stored_columns_can_be_chosen(): void
    {
        Post::query()->create(['name' => 'Chair', 'sort' => 7]);

        $field = BelongsToField::make('post_sort')->model(Post::class)->displayColumn('name')->valueColumn('sort');

        $this->assertSame('sort', $field->getValueColumn());
        $this->assertSame([['value' => 7, 'label' => 'Chair']], $field->getOptions());
        $this->assertSame(['nullable', 'exists:posts,sort'], $field->getValidationRules());
        $this->assertSame('title', BelongsToField::make('a')->displayColumn('title')->getDisplayColumn());
    }

    public function test_with_a_search_route_the_records_are_not_preloaded(): void
    {
        Post::query()->create(['name' => 'Chair']);

        $searched = BelongsToField::make('post_id')->model(Post::class)->searchRoute('admin.posts.search')->toArray();
        $notSearchable = BelongsToField::make('post_id')->model(Post::class)->searchRoute('admin.posts.search')->searchable(false)->toArray();

        $this->assertSame('admin.posts.search', $searched['searchRoute']);
        $this->assertSame([], $searched['options']);
        $this->assertFalse($notSearchable['searchable']);
        $this->assertCount(1, $notSearchable['options'], 'without search the form needs the list');
        $this->assertFalse(BelongsToField::make('a')->clearable(false)->toArray()['clearable']);
    }

    /**
     * @return array<string, array{\Closure(int): mixed, bool}>
     */
    public static function links(): array
    {
        return [
            'an existing record' => [fn (int $id): mixed => $id, true],
            'its id as a string' => [fn (int $id): mixed => (string) $id, true],
            'a missing record' => [fn (int $id): mixed => $id + 1000, false],
            'nothing, the field is optional' => [fn (int $id): mixed => null, true],
        ];
    }

    #[DataProvider('links')]
    public function test_a_link_to_a_record_that_does_not_exist_is_refused(\Closure $value, bool $passes): void
    {
        $post = Post::query()->create(['name' => 'Chair']);
        $field = BelongsToField::make('post_id')->model(Post::class);

        $this->assertSame(['nullable', 'exists:posts,id'], $field->getValidationRules());
        $this->assertSame($passes, $this->passes($field, $value($post->id)));
    }

    public function test_a_belongs_to_many_field_always_loads_the_records(): void
    {
        $post = Post::query()->create(['name' => 'Chair']);

        $field = BelongsToManyField::make('posts')->model(Post::class)->searchRoute('admin.posts.search');
        $array = $field->toArray();

        $this->assertSame('belongsToMany', $field->getType());
        $this->assertSame([['value' => $post->id, 'label' => 'Chair']], $array['options']);
        $this->assertSame(Post::class, $array['model']);
        $this->assertTrue($array['multiple']);
        $this->assertSame(0, $array['maxItems']);
        $this->assertSame(0, $array['minItems']);
        $this->assertFalse($array['sortable']);
        $this->assertSame('admin.posts.search', $array['searchRoute']);
    }

    public function test_a_belongs_to_many_field_without_a_model_offers_nothing(): void
    {
        $array = BelongsToManyField::make('posts')->toArray();

        $this->assertNull($array['model']);
        $this->assertSame([], $array['options']);
    }

    public function test_a_belongs_to_many_field_takes_its_counts_and_sorting(): void
    {
        $field = BelongsToManyField::make('posts')->model(Post::class)->minItems(1)->maxItems(2)->sortable();
        $array = $field->toArray();

        $this->assertSame(1, $array['minItems']);
        $this->assertSame(2, $array['maxItems']);
        $this->assertTrue($array['sortable']);
        $this->assertFalse(BelongsToManyField::make('a')->sortable()->sortable(false)->toArray()['sortable']);
        $this->assertSame(['nullable', 'array', 'min:1', 'max:2'], $field->getValidationRules(), 'no exists rule for a list');
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function selections(): array
    {
        return [
            'the fewest allowed' => [[1], true],
            'the most allowed' => [[1, 2], true],
            'none' => [[], false],
            'one too many' => [[1, 2, 3], false],
            'empty, the field is optional' => [null, true],
            'one id, not a list' => [1, false],
        ];
    }

    #[DataProvider('selections')]
    public function test_a_belongs_to_many_field_refuses_too_few_or_too_many_records(mixed $value, bool $passes): void
    {
        $this->assertSame($passes, $this->passes(BelongsToManyField::make('posts')->model(Post::class)->minItems(1)->maxItems(2), $value));
    }

    public function test_a_belongs_to_many_field_keeps_explicit_rules(): void
    {
        $this->assertSame(['required', 'distinct', 'array'], BelongsToManyField::make('posts')->model(Post::class)->required()->rules('distinct')->getValidationRules());
        $this->assertSame(['nullable', 'array', 'max:3'], BelongsToManyField::make('posts')->maxItems(3)->getValidationRules());
    }

    /**
     * @return array<string, array{mixed, array<mixed>}>
     */
    public static function saved(): array
    {
        return [
            'a list' => [[1, 2], [1, 2]],
            'one id' => [5, [5]],
            'one id as a string' => ['5', ['5']],
            'null' => [null, []],
            'an empty list' => [[], []],
        ];
    }

    /**
     * @param  array<mixed>  $expected
     */
    #[DataProvider('saved')]
    public function test_a_belongs_to_many_field_is_always_saved_as_a_list(mixed $value, array $expected): void
    {
        $this->assertSame($expected, BelongsToManyField::make('posts')->transformValue($value));
    }

    public function test_a_tree_select_without_a_model_offers_nothing(): void
    {
        $field = TreeSelectField::make('section_id');
        $array = $field->toArray();

        $this->assertSame('treeSelect', $field->getType());
        $this->assertNull($array['modelClass']);
        $this->assertNull($array['model']);
        $this->assertSame('name', $array['displayColumn']);
        $this->assertSame('id', $array['valueColumn']);
        $this->assertSame('parent_id', $array['parentColumn']);
        $this->assertTrue($array['searchable']);
        $this->assertTrue($array['clearable']);
        $this->assertFalse($array['multiple']);
        $this->assertFalse($array['expandAll']);
        $this->assertNull($array['rootLabel']);
        $this->assertSame([], $array['options']);
        $this->assertSame([], $field->getTreeOptions());
        $this->assertSame(['nullable'], $field->getValidationRules());
    }

    public function test_a_tree_select_takes_its_display_settings(): void
    {
        $array = TreeSelectField::make('a')->searchable(false)->clearable(false)->multiple()->expandAll()->toArray();

        $this->assertFalse($array['searchable']);
        $this->assertFalse($array['clearable']);
        $this->assertTrue($array['multiple']);
        $this->assertTrue($array['expandAll']);
        $this->assertFalse(TreeSelectField::make('a')->expandAll()->expandAll(false)->toArray()['expandAll']);
        $this->assertFalse(TreeSelectField::make('a')->multiple()->multiple(false)->toArray()['multiple']);
    }

    public function test_the_tree_nests_sections_ordered_by_sort_and_name(): void
    {
        $shop = Section::query()->create(['name' => 'Shop', 'sort' => 10]);
        $about = Section::query()->create(['name' => 'About', 'sort' => 20]);
        $chairs = Section::query()->create(['name' => 'Chairs', 'sort' => 500, 'parent_id' => $shop->id]);
        $beds = Section::query()->create(['name' => 'Beds', 'sort' => 500, 'parent_id' => $shop->id]);
        $garden = Section::query()->create(['name' => 'Garden', 'sort' => 1, 'parent_id' => $chairs->id]);

        $field = TreeSelectField::make('section_id')->model(Section::class);

        $expected = [
            ['value' => $shop->id, 'label' => 'Shop', 'children' => [
                ['value' => $beds->id, 'label' => 'Beds'],
                ['value' => $chairs->id, 'label' => 'Chairs', 'children' => [
                    ['value' => $garden->id, 'label' => 'Garden'],
                ]],
            ]],
            ['value' => $about->id, 'label' => 'About'],
        ];

        $this->assertSame($expected, $field->getTreeOptions());
        $this->assertSame($expected, $field->toArray()['options']);
        $this->assertSame(Section::class, $field->toArray()['model']);
    }

    public function test_the_tree_can_be_built_from_other_columns(): void
    {
        $group = Section::query()->create(['name' => 'g', 'title' => 'Группа']);
        $item = Section::query()->create(['name' => 'i', 'title' => 'Элемент', 'group_id' => $group->id]);

        $field = TreeSelectField::make('group_id')->model(Section::class)->displayColumn('title')->parentColumn('group_id');

        $this->assertSame([
            ['value' => $group->id, 'label' => 'Группа', 'children' => [['value' => $item->id, 'label' => 'Элемент']]],
        ], $field->getTreeOptions());
        $this->assertSame('title', $field->toArray()['displayColumn']);
        $this->assertSame('group_id', $field->toArray()['parentColumn']);
        $this->assertSame('name', TreeSelectField::make('a')->valueColumn('name')->toArray()['valueColumn']);
    }

    public function test_the_parent_of_a_section_offers_the_top_level_in_every_language(): void
    {
        foreach (array_keys(config('atrium.locales')) as $locale) {
            app()->setLocale($locale);

            $label = TreeSelectField::make('parent_id')->model(Section::class)->toArray()['rootLabel'];

            $this->assertSame(__('atrium::fields.tree.top_level'), $label, $locale);
            $this->assertStringStartsNotWith('atrium::', (string) $label, $locale);
        }
    }

    /**
     * @return array<string, array{\Closure(): TreeSelectField, string|null}>
     */
    public static function rootLabels(): array
    {
        return [
            'linking an element to a section has no top level' => [fn (): TreeSelectField => TreeSelectField::make('section_id'), null],
            'several parents have no top level' => [fn (): TreeSelectField => TreeSelectField::make('parent_id')->multiple(), null],
            'a parent under another column name' => [fn (): TreeSelectField => TreeSelectField::make('group_id')->parentColumn('group_id'), 'Top level'],
            'an own label' => [fn (): TreeSelectField => TreeSelectField::make('section_id')->rootLabel('Без раздела'), 'Без раздела'],
            'switched off for a parent' => [fn (): TreeSelectField => TreeSelectField::make('parent_id')->rootLabel(null), null],
        ];
    }

    #[DataProvider('rootLabels')]
    public function test_the_top_level_row_is_offered_only_where_it_means_something(\Closure $make, ?string $expected): void
    {
        $this->assertSame($expected, $make()->toArray()['rootLabel']);
    }

    public function test_a_single_tree_select_checks_the_section_exists_and_a_multiple_one_takes_a_list(): void
    {
        $section = Section::query()->create(['name' => 'Shop']);

        $single = TreeSelectField::make('section_id')->model(Section::class);
        $multiple = TreeSelectField::make('sections')->model(Section::class)->multiple()->rules('distinct');

        $this->assertSame(['nullable', 'exists:sections,id'], $single->getValidationRules());
        $this->assertTrue($this->passes($single, $section->id));
        $this->assertFalse($this->passes($single, $section->id + 1));
        $this->assertSame(['nullable', 'distinct', 'array'], $multiple->getValidationRules(FieldContext::edit($section)));
        $this->assertTrue($this->passes($multiple, [$section->id]));
        $this->assertFalse($this->passes($multiple, $section->id));
    }

    /**
     * shop → chairs → garden, and about on its own.
     *
     * @return array{shop: Section, chairs: Section, garden: Section, about: Section}
     */
    private function tree(): array
    {
        $shop = Section::query()->create(['name' => 'Shop']);
        $chairs = Section::query()->create(['name' => 'Chairs', 'parent_id' => $shop->id]);
        $garden = Section::query()->create(['name' => 'Garden', 'parent_id' => $chairs->id]);
        $about = Section::query()->create(['name' => 'About']);

        return compact('shop', 'chairs', 'garden', 'about');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function parents(): array
    {
        return [
            'itself' => ['shop', false],
            'its child' => ['chairs', false],
            'its grandchild' => ['garden', false],
            'another top-level section' => ['about', true],
        ];
    }

    #[DataProvider('parents')]
    public function test_a_section_cannot_be_put_inside_itself_or_its_own_subsection(string $parent, bool $passes): void
    {
        $sections = $this->tree();
        $field = TreeSelectField::make('parent_id')->model(Section::class);
        $context = FieldContext::edit($sections['shop'], $sections[$parent]->id, true);

        $validator = Validator::make(['parent_id' => $sections[$parent]->id], ['parent_id' => $field->getValidationRules($context)]);

        $this->assertSame($passes, $validator->passes());
        $this->assertSame($passes ? [] : [__('atrium::fields.validation.own_ancestor')], $validator->errors()->get('parent_id'));
    }

    public function test_a_subsection_can_move_up_to_the_parent_of_its_parent_or_to_the_top(): void
    {
        $sections = $this->tree();
        $field = TreeSelectField::make('parent_id')->model(Section::class);

        $this->assertTrue($this->passes($field, $sections['shop']->id, FieldContext::edit($sections['garden'])));
        $this->assertTrue($this->passes($field, $sections['about']->id, FieldContext::edit($sections['garden'])));
        $this->assertTrue($this->passes($field, null, FieldContext::edit($sections['garden'])));
        $this->assertFalse($this->passes($field, $sections['garden']->id, FieldContext::edit($sections['garden'])));
    }

    public function test_a_cycle_already_in_the_data_does_not_hang_the_check(): void
    {
        $a = Section::query()->create(['name' => 'A']);
        $b = Section::query()->create(['name' => 'B', 'parent_id' => $a->id]);
        $a->update(['parent_id' => $b->id]);
        $edited = Section::query()->create(['name' => 'Edited']);

        $this->assertTrue($this->passes(TreeSelectField::make('parent_id')->model(Section::class), $a->id, FieldContext::edit($edited)));
    }

    public function test_a_cycle_through_the_edited_section_is_found_however_deep(): void
    {
        $previous = $top = Section::query()->create(['name' => 'Level 0']);

        for ($level = 1; $level <= 6; $level++) {
            $previous = Section::query()->create(['name' => "Level {$level}", 'parent_id' => $previous->id]);
        }

        $this->assertFalse($this->passes(TreeSelectField::make('parent_id')->model(Section::class), $previous->id, FieldContext::edit($top)));
    }

    /**
     * @return array<string, array{\Closure(Section): TreeSelectField, \Closure(Section): FieldContext|null}>
     */
    public static function withoutTheCycleCheck(): array
    {
        $parent = fn (): TreeSelectField => TreeSelectField::make('parent_id')->model(Section::class);

        return [
            'on create' => [$parent, fn (Section $s): ?FieldContext => FieldContext::create($s->id, true)],
            'without a context' => [$parent, fn (Section $s): ?FieldContext => null],
            'a record not saved yet' => [$parent, fn (Section $s): ?FieldContext => FieldContext::edit(new Section(['name' => 'New']), $s->id, true)],
            'not the parent column' => [fn (): TreeSelectField => TreeSelectField::make('section_id')->model(Section::class), fn (Section $s): ?FieldContext => FieldContext::edit($s, $s->id, true)],
        ];
    }

    #[DataProvider('withoutTheCycleCheck')]
    public function test_the_cycle_check_is_only_there_when_editing_the_parent_of_a_saved_section(\Closure $make, \Closure $context): void
    {
        $section = Section::query()->create(['name' => 'Shop']);
        $rules = $make()->getValidationRules($context($section));

        $this->assertSame(['nullable', 'exists:sections,id'], $rules);
    }

    public function test_the_cycle_check_is_added_when_editing_the_parent_of_a_saved_section(): void
    {
        $section = Section::query()->create(['name' => 'Shop']);
        $rules = TreeSelectField::make('parent_id')->model(Section::class)->getValidationRules(FieldContext::edit($section));

        $this->assertCount(3, $rules);
        $this->assertSame(['nullable', 'exists:sections,id'], array_slice($rules, 0, 2));
        $this->assertInstanceOf(\Closure::class, $rules[2]);

        $failures = [];
        $fail = function (string $message) use (&$failures): void {
            $failures[] = $message;
        };

        $rules[2]('parent_id', 0, $fail);
        $rules[2]('parent_id', '0', $fail);
        $this->assertSame([], $failures, 'no parent is never a cycle');

        $rules[2]('parent_id', (string) $section->id, $fail);
        $this->assertSame([__('atrium::fields.validation.own_ancestor')], $failures);

        $this->assertSame(['nullable'], TreeSelectField::make('parent_id')->getValidationRules(FieldContext::edit($section)), 'no model, nothing to walk');
        $this->assertSame(['nullable', 'array'], TreeSelectField::make('parent_id')->model(Section::class)->multiple()->getValidationRules(FieldContext::edit($section)));
    }
}
