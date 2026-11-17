<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Admin\Tests\Feature\Cache;

use Atrium\Admin\Models\SeoMeta;
use Atrium\Admin\Models\Slug;
use Atrium\Admin\Tests\Fixtures\Cache\Book;
use Atrium\Admin\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The rows that belong to another record through a morph pair (SEO meta, a
 * slug) name that pair for the models that extend them, and name their owner
 * without loading it.
 */
final class OwnerMorphNameTest extends TestCase
{
    /**
     * @return array<string,array{0:class-string<Model>,1:string}>
     */
    public static function owned(): array
    {
        return [
            'SEO meta' => [SeoMeta::class, 'seoable'],
            'a slug' => [Slug::class, 'sluggable'],
        ];
    }

    public function test_a_model_that_extends_the_seo_row_can_read_the_name_of_its_pair(): void
    {
        $row = new class extends SeoMeta
        {
            public function name(): string
            {
                return $this->ownerMorphName();
            }
        };

        $this->assertSame('seoable', $row->name());
    }

    public function test_a_model_that_extends_the_slug_row_can_read_the_name_of_its_pair(): void
    {
        $row = new class extends Slug
        {
            public function name(): string
            {
                return $this->ownerMorphName();
            }
        };

        $this->assertSame('sluggable', $row->name());
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('owned')]
    public function test_a_row_names_its_owner_without_loading_it(string $class, string $name): void
    {
        $row = new $class([$name.'_type' => Book::class, $name.'_id' => 7]);
        $row->forceFill([$name.'_type' => Book::class, $name.'_id' => 7]);

        $touched = $row->cacheTouches();

        $this->assertCount(1, $touched);
        $this->assertInstanceOf(Book::class, $touched[0]);
        $this->assertSame(7, $touched[0]->getKey());
        $this->assertTrue($touched[0]->exists);
    }

    /**
     * @param  class-string<Model>  $class
     */
    #[DataProvider('owned')]
    public function test_a_row_with_no_owner_touches_nothing(string $class, string $name): void
    {
        $row = new $class;
        $this->assertSame([], $row->cacheTouches());

        $row->forceFill([$name.'_type' => Book::class]);
        $this->assertSame([], $row->cacheTouches());

        $row->forceFill([$name.'_type' => 'not a class', $name.'_id' => 7]);
        $this->assertSame([], $row->cacheTouches());
    }
}
