<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\ImageField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Traits\HasAdminSeo;
use Illuminate\Database\Eloquent\Model;
use ReflectionMethod;

final class SeoMetaTest extends TestCase
{
    public function test_every_field_of_the_seo_tab_is_saved(): void
    {
        $post = SeoPost::query()->create(['name' => 'Hello']);

        $data = [];

        foreach (SeoPost::getSeoAdminFields() as $field) {
            $column = SeoPost::seoFieldMap()[$field->getName()];
            $limit = $field->toArray()['maxLength'] ?? null;

            $data[$column] = match (true) {
                // A path to the picture, not a text.
                $field instanceof ImageField => 'seo/'.str_repeat('a', 240).'.jpg',
                // As long as the form lets it be: the column holds it on every database.
                is_int($limit) => substr(str_repeat($column.'-', 100), 0, $limit),
                // No limit on the form: the column is TEXT, past 255 characters.
                default => str_repeat($column.' ', 80),
            };
        }

        $post->updateSeoMeta($data);

        $saved = SeoMeta::query()->sole();

        foreach ($data as $column => $value) {
            $this->assertSame($value, $saved->getAttribute($column), $column);
        }
    }

    public function test_a_module_adds_a_field_to_the_seo_tab(): void
    {
        $names = static fn (): array => array_map(static fn (Field $field): string => $field->getName(), SeoPost::getSeoAdminFields());
        $before = $names();

        $this->assertSame('seo_title', $before[0]);
        $this->assertNotContains('seo_robots', $before);

        $heard = null;
        Hook::filter('atrium.seo.fields', static function (array $fields, string $model) use (&$heard): array {
            $heard = $model;

            return [...$fields, TextField::make('seo_robots')];
        });

        $this->assertSame([...$before, 'seo_robots'], $names());
        $this->assertSame(SeoPost::class, $heard);
        // The panel reads the tab from outside the model.
        $this->assertTrue((new ReflectionMethod(SeoPost::class, 'getSeoAdminFields'))->isPublic());
    }
}

final class SeoPost extends Model
{
    use HasAdminSeo;

    protected $table = 'posts';

    protected $fillable = ['name'];
}
