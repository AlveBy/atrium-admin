<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Api\ApiDocumentation;
use Atrium\Admin\Builder\BlockType;
use Atrium\Admin\Builder\BlockTypes;
use Atrium\Admin\Builder\Layout;
use Atrium\Admin\Builder\LayoutValidator;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\FieldContext;
use Atrium\Admin\Fields\LayoutField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\Fixtures\Builder\NoteBlock;
use Atrium\Admin\Tests\Fixtures\Landing;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The part of the site builder every module builds on: the registry of block
 * types, the tree of a page, what is let into it and the field that edits it.
 */
final class SiteBuilderTest extends TestCase
{
    private const BROKEN = 'The page layout is broken. Reload the page and try again.';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.resources', [Landing::class]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Hook::register(BlockTypes::REGISTRY, 'note', new NoteBlock);
    }

    private function german(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'sort' => 200]);
        app(ContentLanguages::class)->forgetActive();
    }

    /**
     * @param  array<int,mixed>  $blocks
     * @return array<string,mixed>
     */
    private static function tree(array $blocks = []): array
    {
        return ['version' => 1, 'sections' => [['id' => 's1', 'columns' => [['id' => 'c1', 'blocks' => $blocks]]]]];
    }

    /**
     * @param  array<array-key,mixed>  $settings
     * @return array<string,mixed>
     */
    private static function note(array $settings = ['title' => ['en' => 'Hi']], string $id = 'b1'): array
    {
        return ['id' => $id, 'type' => 'note', 'settings' => $settings];
    }

    private function problem(mixed $layout, mixed $stored = null): ?string
    {
        return app(LayoutValidator::class)->problem($layout, $stored);
    }

    // ---- block types ----

    public function test_a_type_that_says_nothing_has_no_settings_and_no_files(): void
    {
        $type = new class extends BlockType
        {
            public function label(): string
            {
                return 'Bare';
            }

            public function view(): string
            {
                return 'fixtures::bare';
            }
        };

        $this->assertSame([], $type->fields());
        $this->assertSame([], $type->translatable());
        $this->assertSame(['any' => 1], $type->data(['any' => 1]));
        $this->assertSame([], $type->styles());
        $this->assertSame([], $type->scripts());
        $this->assertTrue($type->available());
    }

    public function test_the_registry_gives_the_types_that_work_lower_priority_first(): void
    {
        $note = new NoteBlock;
        $first = new NoteBlock;
        $gone = new class extends BlockType
        {
            public function label(): string
            {
                return 'Gone';
            }

            public function view(): string
            {
                return 'fixtures::gone';
            }

            public function available(): bool
            {
                return false;
            }
        };

        Hook::register(BlockTypes::REGISTRY, 'note', $note);
        Hook::register(BlockTypes::REGISTRY, 'first', $first, 5);
        Hook::register(BlockTypes::REGISTRY, 'gone', $gone);
        Hook::register(BlockTypes::REGISTRY, 'junk', 'not a type');

        $types = app(BlockTypes::class);

        $this->assertSame(['first' => $first, 'note' => $note], $types->all());
        $this->assertSame($note, $types->get('note'));
        $this->assertNull($types->get('gone'));
        $this->assertNull($types->get('junk'));
        $this->assertNull($types->get('unheard-of'));
        $this->assertSame('atrium.site.blocks', BlockTypes::REGISTRY);
    }

    // ---- reading a stored tree ----

    /**
     * @return array<string,array{0:mixed,1:array<int,array<string,string>>}>
     */
    public static function storedTrees(): array
    {
        $section = ['id' => 's1'];

        return [
            'a tree' => [['version' => 1, 'sections' => [$section]], [$section]],
            'junk among the sections' => [['version' => 1, 'sections' => ['x' => 'junk', 'y' => $section, 'z' => null]], [$section]],
            'no sections' => [['version' => 1], []],
            'sections that are text' => [['version' => 1, 'sections' => 'all'], []],
            'another version' => [['version' => 2, 'sections' => [$section]], []],
            'a version that is text' => [['version' => '1', 'sections' => [$section]], []],
            'no version' => [['sections' => [$section]], []],
            'text' => ['{"version":1}', []],
            'nothing' => [null, []],
        ];
    }

    #[DataProvider('storedTrees')]
    public function test_the_sections_of_a_stored_tree(mixed $layout, array $sections): void
    {
        $this->assertSame($sections, Layout::sections($layout));
    }

    public function test_the_nodes_of_a_list(): void
    {
        $this->assertSame([['id' => 'a'], []], Layout::nodes([3 => ['id' => 'a'], 5 => 'junk', 7 => []]));
        $this->assertSame([], Layout::nodes('a'));
        $this->assertSame([], Layout::nodes(null));
    }

    public function test_sections_and_columns_have_settings_of_their_own(): void
    {
        [$width, $tone] = array_map(static fn (Field $field): array => $field->toArray(), Layout::sectionFields());
        [$span] = array_map(static fn (Field $field): array => $field->toArray(), Layout::columnFields());

        $this->assertSame(['width', 'select', 'Width', 'default'], [$width['name'], $width['type'], $width['label'], $width['default']]);
        $this->assertSame([['value' => 'narrow', 'label' => 'Narrow'], ['value' => 'default', 'label' => 'Standard'], ['value' => 'full', 'label' => 'Full width']], $width['options']);
        $this->assertSame(['tone', 'select', 'Background', 'plain'], [$tone['name'], $tone['type'], $tone['label'], $tone['default']]);
        $this->assertSame([['value' => 'plain', 'label' => 'Plain'], ['value' => 'tinted', 'label' => 'Tinted']], $tone['options']);
        $this->assertSame(['span', 'number', 'Width in columns of 12', 12], [$span['name'], $span['type'], $span['label'], $span['default']]);
        $this->assertSame([1.0, 12.0], [$span['min'], $span['max']]);
    }

    // ---- what is let into a tree ----

    public function test_a_whole_tree_is_let_in(): void
    {
        $this->assertNull($this->problem(['version' => 1, 'sections' => []]));
        $this->assertNull($this->problem(self::tree()));
        $this->assertNull($this->problem(['version' => 1, 'sections' => [[
            'id' => 's1',
            'settings' => ['width' => 'narrow', 'tone' => 'tinted'],
            'columns' => [['id' => 'c1', 'settings' => ['span' => 6], 'blocks' => [self::note(['title' => ['en' => 'Hi'], 'colour' => 'red', 'framed' => true])]]],
        ]]]));
        $this->assertNull($this->problem(['version' => 1, 'sections' => [['id' => 's1', 'settings' => [], 'columns' => []]]]));
    }

    /**
     * @return array<string,array{0:array<array-key,mixed>,1:string}>
     */
    public static function brokenTrees(): array
    {
        $section = static fn (array $node): array => ['version' => 1, 'sections' => [$node]];
        $column = static fn (array $node): array => $section(['id' => 's1', 'columns' => [$node]]);
        $id = str_repeat('a', 41);

        return [
            'an unknown key' => [['version' => 1, 'sections' => [], 'theme' => 'dark'], self::BROKEN],
            'no version' => [['sections' => []], self::BROKEN],
            'another version' => [['version' => 2, 'sections' => []], self::BROKEN],
            'a version that is text' => [['version' => '1', 'sections' => []], self::BROKEN],
            'no sections' => [['version' => 1], self::BROKEN],
            'sections that are text' => [['version' => 1, 'sections' => 'x'], self::BROKEN],
            'sections that are a map' => [['version' => 1, 'sections' => ['a' => ['id' => 's1', 'columns' => []]]], self::BROKEN],
            'a section that is text' => [['version' => 1, 'sections' => ['x']], 'Section 1: '.self::BROKEN],
            'an unknown key of a section' => [$section(['id' => 's1', 'columns' => [], 'blocks' => []]), 'Section 1: '.self::BROKEN],
            'a section without an id' => [$section(['columns' => []]), 'Section 1: '.self::BROKEN],
            'an id that is a number' => [$section(['id' => 7, 'columns' => []]), 'Section 1: '.self::BROKEN],
            'an empty id' => [$section(['id' => '', 'columns' => []]), 'Section 1: '.self::BROKEN],
            'an id with a space' => [$section(['id' => 'a b', 'columns' => []]), 'Section 1: '.self::BROKEN],
            'an id ending in a new line' => [$section(['id' => "s1\n", 'columns' => []]), 'Section 1: '.self::BROKEN],
            'an id too long' => [$section(['id' => $id, 'columns' => []]), 'Section 1: '.self::BROKEN],
            'the same id twice' => [['version' => 1, 'sections' => [['id' => 's1', 'columns' => []], ['id' => 's1', 'columns' => []]]], 'Section 2: The identifier “s1” is used twice.'],
            'a column with the id of its section' => [$column(['id' => 's1', 'blocks' => []]), 'Section 1, column 1: The identifier “s1” is used twice.'],
            'a block with the id of its column' => [$column(['id' => 'c1', 'blocks' => [self::note(id: 'c1')]]), 'Section 1, column 1, block 1 (Note): The identifier “c1” is used twice.'],
            'no columns' => [$section(['id' => 's1']), 'Section 1: '.self::BROKEN],
            'columns that are a map' => [$section(['id' => 's1', 'columns' => ['a' => []]]), 'Section 1: '.self::BROKEN],
            'settings of a section that are text' => [$section(['id' => 's1', 'columns' => [], 'settings' => 'wide']), 'Section 1: '.self::BROKEN],
            'settings of a section that are a list' => [$section(['id' => 's1', 'columns' => [], 'settings' => ['wide']]), 'Section 1: '.self::BROKEN],
            'an unknown setting of a section' => [$section(['id' => 's1', 'columns' => [], 'settings' => ['height' => 1]]), 'Section 1: There is no setting “height”.'],
            'a width nobody offers' => [$section(['id' => 's1', 'columns' => [], 'settings' => ['width' => 'huge']]), 'Section 1: The selected Width is invalid.'],
            'a column that is text' => [$section(['id' => 's1', 'columns' => ['x']]), 'Section 1, column 1: '.self::BROKEN],
            'an unknown key of a column' => [$column(['id' => 'c1', 'blocks' => [], 'type' => 'x']), 'Section 1, column 1: '.self::BROKEN],
            'no blocks' => [$column(['id' => 'c1']), 'Section 1, column 1: '.self::BROKEN],
            'a span of nothing' => [$column(['id' => 'c1', 'blocks' => [], 'settings' => ['span' => 0]]), 'Section 1, column 1: The Width in columns of 12 field must be at least 1.'],
            'a span too wide' => [$column(['id' => 'c1', 'blocks' => [], 'settings' => ['span' => 13]]), 'Section 1, column 1: The Width in columns of 12 field must not be greater than 12.'],
            'half a column' => [$column(['id' => 'c1', 'blocks' => [], 'settings' => ['span' => 1.5]]), 'Section 1, column 1: The Width in columns of 12 field must be an integer.'],
            'the second column' => [$section(['id' => 's1', 'columns' => [['id' => 'c1', 'blocks' => []], ['id' => 'c2']]]), 'Section 1, column 2: '.self::BROKEN],
            'a block that is text' => [$column(['id' => 'c1', 'blocks' => ['x']]), 'Section 1, column 1, block 1 (): '.self::BROKEN],
            'an unknown key of a block' => [self::tree([self::note() + ['blocks' => []]]), 'Section 1, column 1, block 1 (Note): '.self::BROKEN],
            'a block without a type' => [self::tree([['id' => 'b1']]), 'Section 1, column 1, block 1 (): '.self::BROKEN],
            'a type that is a number' => [self::tree([['id' => 'b1', 'type' => 5]]), 'Section 1, column 1, block 1 (): '.self::BROKEN],
            'a type nobody registered' => [self::tree([['id' => 'b1', 'type' => 'price']]), 'Section 1, column 1, block 1 (price): There is no block type “price”.'],
            'an unknown setting of a block' => [self::tree([self::note(['title' => ['en' => 'Hi'], 'size' => 2])]), 'Section 1, column 1, block 1 (Note): There is no setting “size”.'],
            'settings of a block that are text' => [self::tree([['id' => 'b1', 'type' => 'note', 'settings' => 'x']]), 'Section 1, column 1, block 1 (Note): '.self::BROKEN],
            'a title not by language' => [self::tree([self::note(['title' => 'Hi'])]), 'Section 1, column 1, block 1 (Note): '.self::BROKEN],
            'a title as a list' => [self::tree([self::note(['title' => ['Hi']])]), 'Section 1, column 1, block 1 (Note): '.self::BROKEN],
            'a required title left out' => [self::tree([self::note([])]), 'Section 1, column 1, block 1 (Note): The Title field is required.'],
            'a required title empty' => [self::tree([self::note(['title' => ['en' => '']])]), 'Section 1, column 1, block 1 (Note): The Title field is required.'],
            'a title too long' => [self::tree([self::note(['title' => ['en' => str_repeat('a', 11)]])]), 'Section 1, column 1, block 1 (Note): The Title field must not be greater than 10 characters.'],
            'a colour nobody offers' => [self::tree([self::note(['title' => ['en' => 'Hi'], 'colour' => 'green'])]), 'Section 1, column 1, block 1 (Note): The selected Colour is invalid.'],
            'a switch that is a word' => [self::tree([self::note(['title' => ['en' => 'Hi'], 'framed' => 'yes'])]), 'Section 1, column 1, block 1 (Note): The Framed field must be true or false.'],
            // What a node is comes before how many it holds, and that before its settings.
            'a section twice, with too many columns' => [['version' => 1, 'sections' => [['id' => 's1', 'columns' => []], ['id' => 's1', 'columns' => array_fill(0, 13, [])]]], 'Section 2: The identifier “s1” is used twice.'],
            'too many columns in a section badly set up' => [$section(['id' => 's1', 'columns' => array_fill(0, 13, []), 'settings' => ['height' => 1]]), 'Section 1: No more than 12 items here.'],
            'a column with a taken id and too many blocks' => [$column(['id' => 's1', 'blocks' => array_fill(0, 101, [])]), 'Section 1, column 1: The identifier “s1” is used twice.'],
            'too many blocks in a column badly set up' => [$column(['id' => 'c1', 'blocks' => array_fill(0, 101, []), 'settings' => ['span' => 0]]), 'Section 1, column 1: No more than 100 items here.'],
            'the second block' => [self::tree([self::note(), ['id' => 'b2', 'type' => 'price']]), 'Section 1, column 1, block 2 (price): There is no block type “price”.'],
        ];
    }

    /**
     * @param  array<array-key,mixed>  $layout
     */
    #[DataProvider('brokenTrees')]
    public function test_the_first_problem_of_a_tree_is_told_with_its_place(array $layout, string $problem): void
    {
        $this->assertSame($problem, $this->problem($layout));
    }

    public function test_the_longest_id_is_let_in(): void
    {
        $this->assertNull($this->problem(['version' => 1, 'sections' => [['id' => str_repeat('A', 37).'_-9', 'columns' => []]]]));
    }

    public function test_a_tree_has_limits(): void
    {
        $sections = static fn (int $count): array => ['version' => 1, 'sections' => array_map(static fn (int $i): array => ['id' => "s{$i}", 'columns' => []], range(1, $count))];
        $columns = static fn (int $count): array => ['version' => 1, 'sections' => [['id' => 's', 'columns' => array_map(static fn (int $i): array => ['id' => "c{$i}", 'blocks' => []], range(1, $count))]]];
        $blocks = static fn (int $count): array => self::tree(array_map(static fn (int $i): array => self::note(id: "b{$i}"), range(1, $count)));

        $this->assertNull($this->problem($sections(100)));
        $this->assertSame('No more than 100 items here.', $this->problem($sections(101)));
        $this->assertNull($this->problem($columns(12)));
        $this->assertSame('Section 1: No more than 12 items here.', $this->problem($columns(13)));
        $this->assertNull($this->problem($blocks(100)));
        $this->assertSame('Section 1, column 1: No more than 100 items here.', $this->problem($blocks(101)));
    }

    public function test_only_the_default_language_must_be_written(): void
    {
        $this->german();

        $this->assertNull($this->problem(self::tree([self::note(['title' => ['en' => 'Hi']])])));
        $this->assertNull($this->problem(self::tree([self::note(['title' => ['en' => 'Hi', 'de' => '']])])));
        $this->assertNull($this->problem(self::tree([self::note(['title' => ['en' => 'Hi', 'de' => null]])])));
        $this->assertSame(
            'Section 1, column 1, block 1 (Note): The Title (en) field is required.',
            $this->problem(self::tree([self::note(['title' => ['de' => 'Hallo']])])),
        );
        $this->assertSame(
            'Section 1, column 1, block 1 (Note): The Title (de) field must not be greater than 10 characters.',
            $this->problem(self::tree([self::note(['title' => ['en' => 'Hi', 'de' => str_repeat('a', 11)]])])),
        );
    }

    public function test_a_field_without_a_label_is_called_by_its_name_and_told_whether_it_was_sent(): void
    {
        $kind = new class extends TextField
        {
            /** @var array<int,array{0:mixed,1:bool}> */
            public array $seen = [];

            public function getValidationRules(?FieldContext $context = null): array
            {
                $this->seen[] = [$context?->value, (bool) $context?->present];

                return parent::getValidationRules($context);
            }
        };
        $spy = $kind::make('spy')->label('')->required();
        Hook::register(BlockTypes::REGISTRY, 'spy', new class($spy) extends BlockType
        {
            public function __construct(private readonly Field $field) {}

            public function label(): string
            {
                return 'Spy';
            }

            public function view(): string
            {
                return 'fixtures::spy';
            }

            public function fields(): array
            {
                return [$this->field];
            }
        });

        $this->assertSame(
            'Section 1, column 1, block 1 (Spy): The spy field is required.',
            $this->problem(self::tree([['id' => 'b1', 'type' => 'spy']])),
        );
        $this->assertNull($this->problem(self::tree([['id' => 'b1', 'type' => 'spy', 'settings' => ['spy' => 'x']]])));
        $this->assertSame([[null, false], ['x', true]], $spy->seen);
    }

    public function test_the_same_validator_starts_anew_every_time(): void
    {
        $validator = app(LayoutValidator::class);
        $unknown = self::tree([['id' => 'b1', 'type' => 'price']]);

        $this->assertNull($validator->problem(self::tree(), $unknown));
        $this->assertNull($validator->problem(self::tree()));
        $this->assertNull($validator->problem($unknown, $unknown));
        $this->assertNotNull($validator->problem($unknown));
    }

    // ---- blocks that stay as they are stored ----

    public function test_a_block_left_as_it_is_stored_is_not_checked_again(): void
    {
        // The module of "price" was removed, and the note lacks its title.
        $stored = self::tree([
            ['id' => 'old', 'type' => 'price', 'settings' => ['sum' => 5, 'in' => ['b' => 2, 'a' => 1]]],
            ['id' => 'bare', 'type' => 'note'],
        ]);

        $this->assertNull($this->problem($stored, $stored));
        // A database may hand the keys back in another order, and PHP reads {} as [].
        $this->assertNull($this->problem(self::tree([
            ['type' => 'price', 'settings' => ['in' => ['a' => 1, 'b' => 2], 'sum' => 5], 'id' => 'old'],
            ['id' => 'bare', 'type' => 'note', 'settings' => []],
        ]), $stored));
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function changedBlocks(): array
    {
        return [
            'another value' => [['id' => 'old', 'type' => 'price', 'settings' => ['sum' => 6]]],
            'the same number as text' => [['id' => 'old', 'type' => 'price', 'settings' => ['sum' => '5']]],
            'one setting more' => [['id' => 'old', 'type' => 'price', 'settings' => ['sum' => 5, 'new' => 1]]],
            'another type' => [['id' => 'old', 'type' => 'cost', 'settings' => ['sum' => 5]]],
            'another id' => [['id' => 'new', 'type' => 'price', 'settings' => ['sum' => 5]]],
        ];
    }

    /**
     * @param  array<string,mixed>  $block
     */
    #[DataProvider('changedBlocks')]
    public function test_a_changed_block_is_checked(array $block): void
    {
        $stored = self::tree([['id' => 'old', 'type' => 'price', 'settings' => ['sum' => 5]]]);

        $this->assertSame(
            "Section 1, column 1, block 1 ({$block['type']}): There is no block type “{$block['type']}”.",
            $this->problem(self::tree([$block]), $stored),
        );
    }

    public function test_junk_in_the_stored_tree_keeps_nothing(): void
    {
        $unknown = self::tree([['id' => 'b1', 'type' => 'price']]);

        $this->assertNotNull($this->problem($unknown, 'junk'));
        $this->assertNotNull($this->problem($unknown, self::tree([['type' => 'price'], ['id' => ['x'], 'type' => 'price'], 'junk'])));
        $this->assertNotNull($this->problem($unknown, ['version' => 2] + $unknown));
    }

    // ---- the field ----

    public function test_the_field_tells_the_editor_the_types_and_the_limits(): void
    {
        Hook::register(BlockTypes::REGISTRY, '7', new NoteBlock, 20);

        $props = LayoutField::make('layout')->label('Blocks')->toArray();

        $this->assertSame('layout', $props['type']);
        $this->assertSame(['note', '7'], array_column($props['types'], 'type'));
        $this->assertSame('Note', $props['types'][0]['label']);
        $this->assertSame(['title', 'colour', 'framed'], array_column($props['types'][0]['fields'], 'name'));
        $this->assertSame(['text', 'select', 'checkbox'], array_column($props['types'][0]['fields'], 'type'));
        $this->assertTrue($props['types'][0]['fields'][0]['translatable']);
        $this->assertArrayNotHasKey('translatable', $props['types'][0]['fields'][1]);
        $this->assertSame(['width', 'tone'], array_column($props['sectionFields'], 'name'));
        $this->assertSame(['span'], array_column($props['columnFields'], 'name'));
        $this->assertSame([100, 12, 100, 1], [$props['maxSections'], $props['maxColumns'], $props['maxBlocks'], $props['version']]);
    }

    /**
     * @return array<int,string>
     */
    private function errors(mixed $value, LayoutField $field, ?Model $record = null): array
    {
        $context = $record === null ? FieldContext::create($value, true) : FieldContext::edit($record, $value, true);

        return Validator::make(['layout' => $value], ['layout' => $field->getValidationRules($context)])->errors()->get('layout');
    }

    public function test_the_field_keeps_the_rules_a_module_adds(): void
    {
        $rules = LayoutField::make('layout')->rules(['max:2'])->getValidationRules();

        $this->assertSame(['nullable', 'max:2', 'array'], array_slice($rules, 0, 3));
        $this->assertCount(4, $rules);
    }

    public function test_the_field_takes_a_tree_or_nothing(): void
    {
        $field = LayoutField::make('layout');

        $this->assertSame([], $this->errors(null, $field));
        $this->assertSame([], $this->errors(self::tree([self::note()]), $field));
        $this->assertSame(['The layout field must be an array.'], $this->errors('{"version":1}', $field));
        $this->assertSame(['Section 1, column 1, block 1 (price): There is no block type “price”.'], $this->errors(self::tree([['id' => 'b1', 'type' => 'price']]), $field));
        $this->assertSame(['The layout field is required.'], $this->errors(null, LayoutField::make('layout')->required()));
        $this->assertSame([], Validator::make(['layout' => self::tree()], ['layout' => $field->getValidationRules()])->errors()->all());
    }

    public function test_the_field_keeps_what_the_record_holds(): void
    {
        $tree = self::tree([['id' => 'b1', 'type' => 'price']]);
        $record = (new Landing)->forceFill(['layout' => $tree]);

        $this->assertSame([], $this->errors($tree, LayoutField::make('layout'), $record));
        $this->assertNotSame([], $this->errors($tree, LayoutField::make('layout'), new Landing));
    }

    public function test_a_file_of_records_leaves_the_layout_out(): void
    {
        $targets = array_keys(Targets::for(Landing::class)->all());

        $this->assertContains('name', $targets);
        $this->assertNotContains('layout', $targets);
    }

    public function test_the_api_is_told_what_a_layout_is(): void
    {
        $documentation = app(ApiDocumentation::class);
        $fields = array_column($documentation->module('landings')['fields'], null, 'name');

        $this->assertSame(['type' => 'object', 'properties' => [
            'version' => ['type' => 'integer', 'enum' => [1]],
            'sections' => ['type' => 'array', 'items' => ['type' => 'object']],
        ]], $fields['layout']['schema']);
        $this->assertStringStartsWith('The tree of a page built from blocks: {"version": 1, "sections": [', $fields['layout']['note']);
        $this->assertStringContainsString('(docs/site-builder.md)', $fields['layout']['note']);
        $this->assertStringContainsString('| `layout` | object |', $documentation->markdown(['landings']));
    }
}
