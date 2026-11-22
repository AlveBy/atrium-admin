<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Transfer;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Tests\Fixtures\Parcel;
use Atrium\Admin\Tests\Fixtures\Post;
use Atrium\Admin\Transfer\ImportOption;
use Atrium\Admin\Transfer\Settings;
use Atrium\Admin\Transfer\Targets;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The mapping and the options are checked the same way for the panel and
 * the console.
 */
final class SettingsTest extends TransferTestCase
{
    public function test_a_module_with_a_tree_has_the_separator_of_a_path(): void
    {
        $options = Settings::options(Parcel::class);

        $this->assertCount(1, $options);
        $this->assertSame(
            ['name' => 'separator', 'label' => 'Path separator', 'type' => 'text', 'default' => '/', 'hint' => 'Separates a parent from its child in a path: "Clothes / Jackets".'],
            $options[0]->toArray(),
        );
    }

    public function test_a_module_without_a_tree_has_no_separator(): void
    {
        $this->assertSame([], Settings::options(Post::class));
    }

    public function test_a_module_adds_its_own_options_after_the_separator(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options, string $model): array => $model === Parcel::class
            ? [...$options, new ImportOption('create_aisles', 'Create the aisles', ImportOption::BOOLEAN, false, 'Makes them.')]
            : $options);

        $this->assertSame(['separator', 'create_aisles'], array_map(static fn (ImportOption $option): string => $option->name, Settings::options(Parcel::class)));
        $this->assertSame([], Settings::options(Post::class));
    }

    public function test_the_hook_gets_the_model_and_a_module_may_add_to_a_module_without_a_tree(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options, string $model): array => $model === Post::class
            ? ['unlisted' => new ImportOption('stamp', 'Stamp', ImportOption::TEXT, 'x')]
            : $options);

        $options = Settings::options(Post::class);

        $this->assertSame(['stamp'], array_map(static fn (ImportOption $option): string => $option->name, $options));
        $this->assertSame([0], array_keys($options));
    }

    public function test_an_option_describes_itself(): void
    {
        $this->assertSame(
            ['name' => 'a', 'label' => 'A', 'type' => 'boolean', 'default' => false, 'hint' => ''],
            (new ImportOption('a', 'A'))->toArray(),
        );
        $this->assertSame(
            ['name' => 'b', 'label' => 'B', 'type' => 'text', 'default' => '>', 'hint' => 'Hint'],
            (new ImportOption('b', 'B', ImportOption::TEXT, '>', 'Hint'))->toArray(),
        );
        $this->assertSame(['boolean', 'text'], [ImportOption::BOOLEAN, ImportOption::TEXT]);
    }

    public function test_the_defaults_are_the_mode_and_what_each_option_defaults_to(): void
    {
        $this->assertSame(['mode' => 'both'], Settings::defaults([]));
        $this->assertSame(
            ['mode' => 'both', 'separator' => '/', 'create' => false, 'stamp' => 'x'],
            Settings::defaults([
                new ImportOption('separator', 'S', ImportOption::TEXT, '/'),
                new ImportOption('create', 'C'),
                new ImportOption('stamp', 'T', ImportOption::TEXT, 'x'),
            ]),
        );
    }

    /**
     * @return array<string,array{array<int,string|null>,string}>
     */
    public static function keys(): array
    {
        return [
            'the module\'s first key when it is mapped' => [['name', 'article', 'code'], 'article'],
            'the first of the module\'s keys, not of the columns' => [['id', 'code'], 'code'],
            'the id alone' => [['name', 'id'], 'id'],
            'the first key when no column fills one' => [['name', null], 'article'],
            'the code' => [['code', null, 'name'], 'code'],
        ];
    }

    /**
     * @param  array<int,string|null>  $mapping
     */
    #[DataProvider('keys')]
    public function test_the_key_is_the_first_of_the_modules_keys_a_column_fills(array $mapping, string $expected): void
    {
        $this->assertSame($expected, Settings::key($mapping, Targets::for(Parcel::class)));
    }

    public function test_a_module_with_the_default_keys_is_found_by_its_id_and_code(): void
    {
        $this->assertSame('id', Settings::key(['id', 'name'], Targets::for(Post::class)));
        $this->assertSame('id', Settings::key(['name'], Targets::for(Post::class)));
    }

    public function test_valid_settings_come_back_normalized(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('create_aisles', 'Create the aisles')]);
        $targets = Targets::for(Parcel::class);

        $settings = Settings::validate([
            'mapping' => ['name', null, 'article'],
            'key' => 'article',
            'mode' => 'update',
            'create_aisles' => '1',
            'separator' => '>',
            'extra' => 'ignored',
        ], 3, $targets, Settings::options(Parcel::class));

        $this->assertSame([
            'mapping' => ['name', null, 'article'],
            'options' => ['key' => 'article', 'mode' => 'update', 'separator' => '>', 'create_aisles' => true],
        ], $settings);
    }

    public function test_an_option_left_out_gets_its_default(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('create_aisles', 'Create the aisles'), new ImportOption('stamp', 'Stamp', ImportOption::TEXT, 'x')]);

        $settings = Settings::validate(['mapping' => ['code'], 'key' => 'code', 'mode' => 'both'], 1, Targets::for(Parcel::class), Settings::options(Parcel::class));

        $this->assertSame(['key' => 'code', 'mode' => 'both', 'separator' => '/', 'create_aisles' => false, 'stamp' => 'x'], $settings['options']);
    }

    public function test_an_empty_text_option_is_its_default_and_a_false_one_is_false(): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('create_aisles', 'Create the aisles', ImportOption::BOOLEAN, true)]);

        $settings = Settings::validate(['mapping' => ['code'], 'key' => 'code', 'mode' => 'both', 'separator' => null, 'create_aisles' => false], 1, Targets::for(Parcel::class), Settings::options(Parcel::class));

        $this->assertSame(['key' => 'code', 'mode' => 'both', 'separator' => '/', 'create_aisles' => false], $settings['options']);
    }

    public function test_a_text_option_is_kept_as_text(): void
    {
        $settings = Settings::validate(['mapping' => ['code'], 'key' => 'code', 'mode' => 'create', 'separator' => '→'], 1, Targets::for(Parcel::class), Settings::options(Parcel::class));

        $this->assertSame('→', $settings['options']['separator']);
        $this->assertSame('create', $settings['options']['mode']);
    }

    public function test_the_options_are_not_read_unless_the_module_has_them(): void
    {
        $settings = Settings::validate(['mapping' => ['name', 'id'], 'key' => 'id', 'mode' => 'both', 'separator' => '>'], 2, Targets::for(Post::class));

        $this->assertSame(['key' => 'id', 'mode' => 'both'], $settings['options']);
    }

    /**
     * @return array<string,array{array<string,mixed>,string,string|array<int,string>}>
     */
    public static function invalid(): array
    {
        $valid = ['mapping' => ['name', 'article'], 'key' => 'article', 'mode' => 'both', 'create_aisles' => false, 'separator' => '/'];

        return [
            'no mapping' => [['mapping' => null] + $valid, 'mapping', 'The Columns field is required.'],
            'a column too few' => [['mapping' => ['name']] + $valid, 'mapping', 'The Columns field must contain 2 items.'],
            'a column too many' => [['mapping' => ['name', 'article', 'code']] + $valid, 'mapping', 'The Columns field must contain 2 items.'],
            'not a list' => [['mapping' => [1 => 'name', 2 => 'article']] + $valid, 'mapping', 'The Columns field must be a list.'],
            'an unknown field' => [['mapping' => ['name', 'nonsense']] + $valid, 'mapping.1', 'The selected Columns is invalid.'],
            'a field not a string' => [['mapping' => ['name', 5]] + $valid, 'mapping.1', ['The Columns field must be a string.', 'The selected Columns is invalid.']],
            'a field twice' => [['mapping' => ['article', 'article']] + $valid, 'mapping', 'A field takes one column only; chosen more than once: Article.'],
            'no key' => [['key' => null] + $valid, 'key', 'The Find records by field is required.'],
            'no mode' => [['mode' => null] + $valid, 'mode', 'The What to do field is required.'],
            'an unknown key' => [['key' => 'name'] + $valid, 'key', 'The selected Find records by is invalid.'],
            'the key not mapped' => [['key' => 'code'] + $valid, 'key', 'Records are found by "Code", but no column fills it.'],
            'an unknown mode' => [['mode' => 'replace'] + $valid, 'mode', 'The selected What to do is invalid.'],
            'not a yes or no' => [['create_aisles' => 'maybe'] + $valid, 'create_aisles', 'The Create the aisles field must be true or false.'],
            'a long separator' => [['separator' => str_repeat('-', 256)] + $valid, 'separator', 'The Path separator field must not be greater than 255 characters.'],
            'a separator that is not text' => [['separator' => ['/']] + $valid, 'separator', 'The Path separator field must be a string.'],
        ];
    }

    /**
     * @param  array<string,mixed>  $input
     */
    #[DataProvider('invalid')]
    public function test_invalid_settings_say_what_is_wrong(array $input, string $key, string|array $messages): void
    {
        Hook::filter('atrium.import.options', static fn (array $options): array => [...$options, new ImportOption('create_aisles', 'Create the aisles')]);

        try {
            Settings::validate($input, 2, Targets::for(Parcel::class), Settings::options(Parcel::class));
            $this->fail('The settings were accepted.');
        } catch (ValidationException $invalid) {
            $this->assertSame([$key => (array) $messages], $invalid->errors());
        }
    }

    public function test_a_separator_of_255_characters_is_fine(): void
    {
        $settings = Settings::validate(['mapping' => ['code'], 'key' => 'code', 'mode' => 'both', 'separator' => str_repeat('-', 255)], 1, Targets::for(Parcel::class), Settings::options(Parcel::class));

        $this->assertSame(str_repeat('-', 255), $settings['options']['separator']);
    }

    public function test_a_field_not_mapped_is_not_a_field_twice(): void
    {
        $settings = Settings::validate(['mapping' => [null, null, 'id'], 'key' => 'id', 'mode' => 'both'], 3, Targets::for(Post::class));

        $this->assertSame([null, null, 'id'], $settings['mapping']);
    }

    public function test_every_field_chosen_twice_is_named(): void
    {
        try {
            Settings::validate(['mapping' => ['article', 'name', 'article', 'name', 'code'], 'key' => 'article', 'mode' => 'both', 'separator' => '/'], 5, Targets::for(Parcel::class), Settings::options(Parcel::class));
            $this->fail('The settings were accepted.');
        } catch (ValidationException $invalid) {
            $this->assertSame(['mapping' => ['A field takes one column only; chosen more than once: Article, Name.']], $invalid->errors());
        }
    }

    public function test_a_key_whose_field_the_form_has_lost_is_not_accepted(): void
    {
        Hook::filter('atrium.module.fields', static fn (array $fields): array => array_values(array_filter($fields, static fn ($field): bool => $field->getName() !== 'article')));

        try {
            Settings::validate(['mapping' => ['name'], 'key' => 'article', 'mode' => 'both', 'separator' => '/'], 1, Targets::for(Parcel::class), Settings::options(Parcel::class));
            $this->fail('The settings were accepted.');
        } catch (ValidationException $invalid) {
            $this->assertSame(['key' => ['The selected Find records by is invalid.']], $invalid->errors());
        }
    }
}
