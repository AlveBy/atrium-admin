<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Fields\Field;
use Atrium\Admin\Models\Language;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The language model in exact form: its fields, what saving does to the
 * flags and which caches it empties.
 */
final class LanguageExactnessTest extends TestCase
{
    /**
     * @return array<string,Field>
     */
    private function fields(): array
    {
        $fields = [];

        foreach (Language::getAdministrationFields() as $field) {
            $fields[$field->getName().($field->shouldShowOnEdit() ? '' : ':create')] = $field;
        }

        return $fields;
    }

    public function test_the_fields_and_their_limits(): void
    {
        $fields = $this->fields();

        $this->assertSame(['name', 'code:create', 'code', 'active', 'is_default', 'sort'], array_keys($fields));
        $this->assertTrue($fields['name']->isRequired());
        $this->assertSame(255, $fields['name']->getMaxLength());
        $this->assertContains('string', $fields['code:create']->getValidationRules());
        $this->assertContains('max:12', $fields['code:create']->getValidationRules());
        $this->assertTrue($fields['active']->getDefault());
        $this->assertFalse($fields['is_default']->getDefault());
        $this->assertSame(500, $fields['sort']->getDefault());
    }

    public function test_the_flags_are_booleans(): void
    {
        $language = Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'active' => 1]);

        $this->assertTrue($language->fresh()->active);
        $this->assertTrue(Language::query()->where('code', 'de')->sole()->active);
    }

    public function test_the_default_language_is_always_active(): void
    {
        $language = Language::query()->create(['code' => 'de', 'name' => 'Deutsch', 'active' => false, 'is_default' => true]);

        $this->assertTrue($language->active);
        $this->assertTrue($language->fresh()->active);
        $this->assertFalse(Language::query()->where('code', 'en')->sole()->is_default);
    }

    public function test_the_code_of_a_saved_language_cannot_be_changed(): void
    {
        $language = Language::query()->create(['code' => 'de', 'name' => 'Deutsch']);
        $language->code = 'fr';
        $language->save();

        $this->assertSame('de', $language->code);
        $this->assertSame('de', $language->fresh()->code);
    }

    public function test_saving_and_deleting_a_language_empty_the_caches(): void
    {
        $after = function (callable $change): array {
            app(ContentLanguages::class)->all();
            $before = Cache::get('atrium.sitemap.version');
            DB::table('admin_languages')->where('code', 'en')->update(['name' => 'Changed']);

            $change();

            return [
                (string) app(ContentLanguages::class)->all()->firstWhere('code', 'en')?->name,
                $before === Cache::get('atrium.sitemap.version') ? 'stale' : 'fresh',
            ];
        };

        $this->assertSame(['Changed', 'fresh'], $after(fn () => Language::query()->create(['code' => 'de', 'name' => 'Deutsch'])));

        $language = Language::query()->where('code', 'de')->sole();
        DB::table('admin_languages')->where('code', 'en')->update(['name' => 'English']);
        $this->assertSame(['Changed', 'fresh'], $after(fn () => $language->delete()));
    }

    public function test_hreflang_upcases_everything_after_the_first_dash(): void
    {
        $this->assertSame('pt-BR', (new Language(['code' => 'pt-br']))->hreflang());
        $this->assertSame('zh-HANT-TW', (new Language(['code' => 'zh-hant-tw']))->hreflang());
        $this->assertSame('en', (new Language(['code' => 'en']))->hreflang());
    }
}
