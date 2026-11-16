<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature;

use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Tests\TestCase;
use Atrium\Admin\Traits\HasAdminSeo;
use Illuminate\Database\Eloquent\Model;

final class SeoMetaTest extends TestCase
{
    public function test_every_field_of_the_seo_tab_is_saved(): void
    {
        $post = SeoPost::query()->create(['name' => 'Hello']);

        $data = [];

        foreach (SeoPost::seoFieldMap() as $column) {
            $data[$column] = str_repeat($column.' ', 80); // past 255 characters
        }

        $post->updateSeoMeta($data);

        $saved = SeoMeta::query()->sole();

        foreach ($data as $column => $value) {
            $this->assertSame($value, $saved->getAttribute($column), $column);
        }
    }
}

final class SeoPost extends Model
{
    use HasAdminSeo;

    protected $table = 'posts';

    protected $fillable = ['name'];
}
